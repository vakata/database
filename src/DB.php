<?php

namespace vakata\database;

use \vakata\collection\Collection;

/**
 * A database abstraction with support for various drivers (mySQL, postgre, oracle, msSQL, sphinx, and even PDO).
 */
class DB implements DBInterface
{
    /**
     * @var DriverInterface
     */
    protected $driver;
    /**
     * @var Table[]
     */
    protected $tables = [];

    /**
     * Create an instance.
     *
     * @param DriverInterface|string $driver a driver instance or a connection string
     */
    public function __construct($driver) {
        $this->driver = $driver instanceof DriverInterface ? $driver : static::getDriver($driver);
    }
    /**
     * Create a driver instance from a connection string
     * @param string $connectionString the connection string
     * @return DriverInterface
     */
    public static function getDriver(string $connectionString)
    {
        $connection = [
            'orig' => $connectionString,
            'type' => null,
            'user' => null,
            'pass' => null,
            'host' => null,
            'port' => null,
            'name' => null,
            'opts' => []
        ];
        $connectionString = array_pad(explode('://', $connectionString, 2), 2, '');
        $connection['type'] = $connectionString[0];
        $connectionString = $connectionString[1];
        $connectionString = array_pad(explode('?', $connectionString, 2), 2, '');
        parse_str($connectionString[1], $connection['opts']);
        $connectionString = $connectionString[0];
        if (strpos($connectionString, '@') !== false) {
            $connectionString = array_pad(explode('@', $connectionString, 2), 2, '');
            list($connection['user'], $connection['pass']) = array_pad(explode(':', $connectionString[0], 2), 2, '');
            $connectionString = $connectionString[1];
        }
        $connectionString = array_pad(explode('/', $connectionString, 2), 2, '');
        $connection['name'] = $connectionString[1];
        list($connection['host'], $connection['port']) = array_pad(explode(':', $connectionString[0], 2), 2, null);
        $tmp = '\\vakata\\database\\'.$connection['type'].'\\Driver';
        return new $tmp($connection);
    }
    /**
     * Prepare a statement.
     * Use only if you need a single query to be performed multiple times with different parameters.
     *
     * @param string $sql the query to prepare - use `?` for arguments
     * @return StatementInterface the prepared statement
     */
    public function prepare(string $sql) : StatementInterface
    {
        return $this->driver->prepare($sql);
    }
    protected function expand(string $sql, $par = null) : array
    {
        $new = '';
        $par = array_values($par);
        if (substr_count($sql, '?') === 2 && !is_array($par[0])) {
            $par = [ $par ];
        }
        $parts = explode('??', $sql);
        $index = 0;
        foreach ($parts as $part) {
            $tmp = explode('?', $part);
            $new .= $part;
            $index += count($tmp) - 1;
            if (isset($par[$index])) {
                if (!is_array($par[$index])) {
                    $par[$index] = [ $par[$index] ];
                }
                $params = $par[$index];
                array_splice($par, $index, 1, $params);
                $index += count($params);
                $new .= implode(',', array_fill(0, count($params), '?'));
            }
        }
        return [ $new, $par ];
    }
    /**
     * Run a query (prepare & execute).
     * @param string $sql  SQL query
     * @param array  $data parameters (optional)
     * @return ResultInterface the result of the execution
     */
    public function query(string $sql, $par = null) : ResultInterface
    {
        $par = isset($par) ? (is_array($par) ? $par : [$par]) : [];
        if (strpos($sql, '??') && count($par)) {
            list($sql, $par) = $this->expand($sql, $par);
        }
        return $this->driver->prepare($sql)->execute($par);
    }
    /**
     * Run a SELECT query and get an array-like result.
     * When using `get` the data is kept in the database client and fetched as needed (not in PHP memory as with `all`)
     *
     * @param string   $sql      SQL query
     * @param array    $par      parameters
     * @param string   $key      column name to use as the array index
     * @param bool     $skip     do not include the column used as index in the value (defaults to `false`)
     * @param callable $keys     an optional mutator to pass each row's keys through (the column names)
     * @param bool     $opti     if a single column is returned - do not use an array wrapper (defaults to `true`)
     *
     * @return Collection the result of the execution
     */
    public function get(string $sql, $par = null, string $key = null, bool $skip = false, callable $keys = null, bool $opti = true) : Collection
    {
        $keys = isset($keys) ? $keys : $this->driver->option('mode');
        $coll = $this->query($sql, $par)->collection();
        if ($keys) {
            $coll->map(function ($v) use ($keys) {
                $new = [];
                foreach ($v as $k => $vv) {
                    $new[call_user_func($keys, $k)] = $vv;
                }
                return $new;
            });
        }
        if ($key) {
            $coll->mapKey(function ($v) use ($key) { return $v[$key]; });
        }
        if ($skip) {
            $coll->map(function ($v) use ($key) { unset($v[$key]); return $v; });
        }
        if ($opti) {
            $coll->map(function ($v) { return count($v) === 1 ? current($v) : $v; });
        }
        if ($keys) {
            $coll->map(function ($v) use ($key) { unset($v[$key]); return $v; });
        }
        return $coll;
    }
    /**
     * Run a SELECT query and get a single row
     * @param string   $sql      SQL query
     * @param array    $par      parameters
     * @param callable $keys     an optional mutator to pass each row's keys through (the column names)
     * @param bool     $opti     if a single column is returned - do not use an array wrapper (defaults to `true`)
     * @return Collection the result of the execution
     */
    public function one(string $sql, $par = null, callable $keys = null, bool $opti = true)
    {
        return $this->get($sql, $par, null, false, $keys, $opti)->value();
    }
    /**
     * Run a SELECT query and get an array
     * @param string   $sql      SQL query
     * @param array    $par      parameters
     * @param string   $key      column name to use as the array index
     * @param bool     $skip     do not include the column used as index in the value (defaults to `false`)
     * @param callable $keys     an optional mutator to pass each row's keys through (the column names)
     * @param bool     $opti     if a single column is returned - do not use an array wrapper (defaults to `true`)
     * @return Collection the result of the execution
     */
    public function all(string $sql, $par = null, string $key = null, bool $skip = false, callable $keys = null, bool $opti = true) : array
    {
        return $this->get($sql, $par, $key, $skip, $keys, $opti)->toArray();
    }
    /**
     * Begin a transaction.
     * @return $this
     */
    public function begin() : DBInterface
    {
        if (!$this->driver->begin()) {
            throw new DBException('Could not begin');
        }
        return $this;
    }
    /**
     * Commit a transaction.
     * @return $this
     */
    public function commit() : DBInterface
    {
        if (!$this->driver->commit()) {
            throw new DBException('Could not commit');
        }
        return $this;
    }
    /**
     * Rollback a transaction.
     * @return $this
     */
    public function rollback() : DBInterface
    {
        if (!$this->driver->rollback()) {
            throw new DBException('Could not rollback');
        }
        return $this;
    }
    /**
     * Get the current driver name (`"mysql"`, `"postgre"`, etc).
     * @return string the current driver name
     */
    public function driver() : string
    {
        return array_reverse(explode('\\', get_class($this->driver)))[1];
    }
    /**
     * Get the current database name.
     * @return string the current database name
     */
    public function name() : string
    {
        return $this->driver->name();
    }

    public function definition(
        string $table,
        bool $detectRelations = true,
        bool $lowerCase = true
    ) : Table
    {
        if (isset($this->tables[$table])) {
            return $this->tables[$table];
        }
        $definition = new Table($table);
        $columns = [];
        $primary = [];
        $comment = '';
        switch ($this->driver()) {
            case 'mysql':
            case 'mysqli':
                foreach ($this->all("SHOW FULL COLUMNS FROM {$table}", null, null, false, null) as $data) {
                    $columns[$data['Field']] = $data;
                    if ($data['Key'] == 'PRI') {
                        $primary[] = $data['Field'];
                    }
                }
                $comment = (string)$this->one(
                    "SELECT table_comment FROM information_schema.tables WHERE table_schema = ? AND table_name = ?",
                    [ $this->name(), $table ]
                );
                break;
            case 'postgre':
                $columns = $this->all(
                    "SELECT * FROM information_schema.columns WHERE table_schema = ? AND table_name = ?",
                    [ $this->name(), $table ],
                    'column_name',
                    false,
                    'strtolower'
                );
                $pkname = $this->one(
                    "SELECT constraint_name FROM information_schema.table_constraints
                    WHERE table_schema = ? AND table_name = ? AND constraint_type = ?",
                    [ $this->name(), $table, 'PRIMARY KEY' ]
                );
                if ($pkname) {
                    $primary = $this->all(
                        "SELECT column_name FROM information_schema.key_column_usage
                        WHERE table_schema = ? AND table_name = ? AND constraint_name = ?",
                        [ $this->name(), $table, $pkname ]
                    );
                }
                break;
            case 'oracle':
                $columns = $this->all(
                    "SELECT * FROM all_tab_cols WHERE table_name = ? AND owner = ?",
                    [ strtoupper($table), $this->name() ],
                    'COLUMN_NAME',
                    false,
                    'strtoupper'
                );
                $owner = $this->name(); // current($columns)['OWNER'];
                $pkname = $this->one(
                    "SELECT constraint_name FROM all_constraints
                    WHERE table_name = ? AND constraint_type = ? AND owner = ?",
                    [ strtoupper($table), 'P', $owner ]
                );
                if ($pkname) {
                    $primary = $this->all(
                        "SELECT column_name FROM all_cons_columns
                        WHERE table_name = ? AND constraint_name = ? AND owner = ?",
                        [ strtoupper($table), $pkname, $owner ]
                    );
                }
                break;
            //case 'ibase':
            //    $columns = $this->all(
            //        "SELECT * FROM rdb$relation_fields WHERE rdb$relation_name = ? ORDER BY rdb$field_position",
            //        [ strtoupper($table) ],
            //        'FIELD_NAME',
            //        false,
            //        'strtoupper'
            //    );
            //    break;
            //case 'mssql':
            //    break;
            //case 'sqlite':
            //    break;
            default:
                throw new DatabaseException('Driver is not supported: '.$this->driver(), 500);
        }
        if (!count($columns)) {
            throw new DatabaseException('Table not found by name');
        }
        $definition
            ->addColumns($columns)
            ->setPrimaryKey($primary)
            ->setComment($comment);
        $this->tables[$table] = $definition;

        if ($detectRelations) {
            switch ($this->driver()) {
                case 'mysql':
                case 'mysqli':
                    // relations where the current table is referenced
                    // assuming current table is on the "one" end having "many" records in the referencing table
                    // resulting in a "hasMany" or "manyToMany" relationship (if a pivot table is detected)
                    $relations = [];
                    foreach ($this->all(
                        "SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_COLUMN_NAME
                        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                        WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME = ?",
                        [ $this->name(), $this->name(), $table ],
                        null,
                        false,
                        'strtoupper'
                    ) as $relation) {
                        $relations[$relation['CONSTRAINT_NAME']]['table'] = $relation['TABLE_NAME'];
                        $relations[$relation['CONSTRAINT_NAME']]['keymap'][$relation['REFERENCED_COLUMN_NAME']] = $relation['COLUMN_NAME'];
                    }
                    foreach ($relations as $data) {
                        $rtable = $this->definition($data['table'], true, $lowerCase); // ?? $this->addTableByName($data['table'], false);
                        $columns = [];
                        foreach ($rtable->getColumns() as $column) {
                            if (!in_array($column, $data['keymap'])) {
                                $columns[] = $column;
                            }
                        }
                        $foreign = [];
                        $usedcol = [];
                        if (count($columns)) {
                            foreach ($this->all(
                                "SELECT
                                    TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME,
                                    REFERENCED_COLUMN_NAME, REFERENCED_TABLE_NAME
                                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                                WHERE
                                    TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME IN (??) AND
                                    REFERENCED_TABLE_NAME IS NOT NULL",
                                [ $this->name(), $data['table'], $columns ],
                                null,
                                false,
                                'strtoupper'
                            ) as $relation) {
                                $foreign[$relation['CONSTRAINT_NAME']]['table'] = $relation['REFERENCED_TABLE_NAME'];
                                $foreign[$relation['CONSTRAINT_NAME']]['keymap'][$relation['COLUMN_NAME']] = $relation['REFERENCED_COLUMN_NAME'];
                                $usedcol[] = $relation['COLUMN_NAME'];
                            }
                        }
                        if (count($foreign) === 1 && !count(array_diff($columns, $usedcol))) {
                            $foreign = current($foreign);
                            $relname = $foreign['table'];
                            $cntr = 1;
                            while ($definition->hasRelation($relname) || $definition->getName() == $relname) {
                                $relname = $foreign['table'] . '_' . (++ $cntr);
                            }
                            $definition->addRelation(
                                new TableRelation(
                                    $relname,
                                    $this->definition($foreign['table'], true, $lowerCase),
                                    $data['keymap'],
                                    true,
                                    $rtable,
                                    $foreign['keymap']
                                )
                            );
                        } else {
                            $relname = $data['table'];
                            $cntr = 1;
                            while ($definition->hasRelation($relname) || $definition->getName() == $relname) {
                                $relname = $data['table'] . '_' . (++ $cntr);
                            }
                            $definition->addRelation(
                                new TableRelation(
                                    $relname,
                                    $this->definition($data['table'], true, $lowerCase),
                                    $data['keymap'],
                                    true
                                )
                            );
                        }
                    }
                    // relations where the current table references another table
                    // assuming current table is linked to "one" record in the referenced table
                    // resulting in a "belongsTo" relationship
                    $relations = [];
                    foreach ($this->all(
                        "SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL",
                        [ $this->name(), $table ],
                        null,
                        false,
                        'strtoupper'
                    ) as $relation) {
                        $relations[$relation['CONSTRAINT_NAME']]['table'] = $relation['REFERENCED_TABLE_NAME'];
                        $relations[$relation['CONSTRAINT_NAME']]['keymap'][$relation['COLUMN_NAME']] = $relation['REFERENCED_COLUMN_NAME'];
                    }
                    foreach ($relations as $name => $data) {
                        $relname = $data['table'];
                        $cntr = 1;
                        while ($definition->hasRelation($relname) || $definition->getName() == $relname) {
                            $relname = $data['table'] . '_' . (++ $cntr);
                        }
                        $definition->addRelation(
                            new TableRelation(
                                $relname,
                                $this->definition($data['table'], true, $lowerCase),
                                $data['keymap'],
                                false
                            )
                        );
                    }
                    break;
                case 'oracle':
                    // relations where the current table is referenced
                    // assuming current table is on the "one" end having "many" records in the referencing table
                    // resulting in a "hasMany" or "manyToMany" relationship (if a pivot table is detected)
                    $relations = [];
                    foreach ($this->all(
                        "SELECT ac.TABLE_NAME, ac.CONSTRAINT_NAME, cc.COLUMN_NAME, cc.POSITION
                        FROM all_constraints ac
                        LEFT JOIN all_cons_columns cc ON cc.OWNER = ac.OWNER AND cc.CONSTRAINT_NAME = ac.CONSTRAINT_NAME
                        WHERE ac.OWNER = ? AND ac.R_OWNER = ? AND ac.R_CONSTRAINT_NAME = ? AND ac.CONSTRAINT_TYPE = ?
                        ORDER BY cc.POSITION",
                        [ $owner, $owner, $pkname, 'R' ],
                        null,
                        false,
                        'strtoupper'
                    ) as $k => $relation) {
                        $relations[$relation['CONSTRAINT_NAME']]['table'] = $relation['TABLE_NAME'];
                        $relations[$relation['CONSTRAINT_NAME']]['keymap'][$primary[(int)$relation['POSITION']-1]] = $relation['COLUMN_NAME'];
                    }
                    foreach ($relations as $data) {
                        $rtable = $this->definition($data['table'], true, $lowerCase); // ?? $this->addTableByName($data['table'], false);
                        $columns = [];
                        foreach ($rtable->getColumns() as $column) {
                            if (!in_array($column, $data['keymap'])) {
                                $columns[] = $column;
                            }
                        }
                        $foreign = [];
                        $usedcol = [];
                        if (count($columns)) {
                            foreach ($this->all(
                                "SELECT
                                    cc.COLUMN_NAME, ac.CONSTRAINT_NAME, rc.TABLE_NAME AS REFERENCED_TABLE_NAME, ac.R_CONSTRAINT_NAME
                                FROM all_constraints ac
                                JOIN all_constraints rc ON rc.CONSTRAINT_NAME = ac.R_CONSTRAINT_NAME AND rc.OWNER = ac.OWNER
                                LEFT JOIN all_cons_columns cc ON cc.OWNER = ac.OWNER AND cc.CONSTRAINT_NAME = ac.CONSTRAINT_NAME
                                WHERE
                                    ac.OWNER = ? AND ac.R_OWNER = ? AND ac.TABLE_NAME = ? AND ac.CONSTRAINT_TYPE = ? AND
                                    cc.COLUMN_NAME IN (??)
                                ORDER BY POSITION",
                                [ $owner, $owner, $data['table'], 'R', $columns ],
                                null,
                                false,
                                'strtoupper'
                            ) as $k => $relation) {
                                $foreign[$relation['CONSTRAINT_NAME']]['table'] = $relation['REFERENCED_TABLE_NAME'];
                                $foreign[$relation['CONSTRAINT_NAME']]['keymap'][$relation['COLUMN_NAME']] = $relation['R_CONSTRAINT_NAME'];
                                $usedcol[] = $relation['COLUMN_NAME'];
                            }
                        }
                        if (count($foreign) === 1 && !count(array_diff($columns, $usedcol))) {
                            $foreign = current($foreign);
                            $rcolumns = $this->all(
                                "SELECT COLUMN_NAME FROM all_cons_columns WHERE OWNER = ? AND CONSTRAINT_NAME = ? ORDER BY POSITION",
                                [ $owner, current($foreign['keymap']) ],
                                null, false, 'strtoupper'
                            );
                            foreach ($foreign['keymap'] as $column => $related) {
                                $foreign['keymap'][$column] = array_shift($rcolumns);
                            }
                            $relname = $foreign['table'];
                            $cntr = 1;
                            while ($definition->hasRelation($relname) || $definition->getName() == $relname) {
                                $relname = $foreign['table'] . '_' . (++ $cntr);
                            }
                            $definition->addRelation(
                                new TableRelation(
                                    $relname,
                                    $this->definition($foreign['table'], true, $lowerCase),
                                    $data['keymap'],
                                    true,
                                    $rtable,
                                    $foreign['keymap']
                                )
                            );
                        } else {
                            $relname = $data['table'];
                            $cntr = 1;
                            while ($definition->hasRelation($relname) || $definition->getName() == $relname) {
                                $relname = $data['table'] . '_' . (++ $cntr);
                            }
                            $definition->addRelation(
                                new TableRelation(
                                    $relname,
                                    $this->definition($data['table'], true, $lowerCase),
                                    $data['keymap'],
                                    true
                                )
                            );
                        }
                    }
                    // relations where the current table references another table
                    // assuming current table is linked to "one" record in the referenced table
                    // resulting in a "belongsTo" relationship
                    $relations = [];
                    foreach ($this->all(
                        "SELECT ac.CONSTRAINT_NAME, cc.COLUMN_NAME, rc.TABLE_NAME AS REFERENCED_TABLE_NAME, ac.R_CONSTRAINT_NAME
                        FROM all_constraints ac
                        JOIN all_constraints rc ON rc.CONSTRAINT_NAME = ac.R_CONSTRAINT_NAME AND rc.OWNER = ac.OWNER
                        LEFT JOIN all_cons_columns cc ON cc.OWNER = ac.OWNER AND cc.CONSTRAINT_NAME = ac.CONSTRAINT_NAME
                        WHERE ac.OWNER = ? AND ac.R_OWNER = ? AND ac.TABLE_NAME = ? AND ac.CONSTRAINT_TYPE = ?
                        ORDER BY cc.POSITION",
                        [ $owner, $owner, strtoupper($table), 'R' ],
                        null, false, 'strtoupper'
                    ) as $relation) {
                        $relations[$relation['CONSTRAINT_NAME']]['table'] = $relation['REFERENCED_TABLE_NAME'];
                        $relations[$relation['CONSTRAINT_NAME']]['keymap'][$relation['COLUMN_NAME']] = $relation['R_CONSTRAINT_NAME'];
                    }
                    foreach ($relations as $name => $data) {
                        $rcolumns = $this->all(
                            "SELECT COLUMN_NAME FROM all_cons_columns WHERE OWNER = ? AND CONSTRAINT_NAME = ? ORDER BY POSITION",
                            [ $owner, current($data['keymap']) ],
                            null, false, 'strtoupper'
                        );
                        foreach ($data['keymap'] as $column => $related) {
                            $data['keymap'][$column] = array_shift($rcolumns);
                        }
                        $relname = $data['table'];
                        $cntr = 1;
                        while ($definition->hasRelation($relname) || $definition->getName() == $relname) {
                            $relname = $data['table'] . '_' . (++ $cntr);
                        }
                        $definition->addRelation(
                            new TableRelation(
                                $relname,
                                $this->definition($data['table'], true, $lowerCase),
                                $data['keymap'],
                                false
                            )
                        );
                    }
                    break;
                default:
                    // throw new DatabaseException('Relations discovery is not supported: '.$this->driver(), 500);
                    break;
            }
        }
        if ($lowerCase) {
            $definition->toLowerCase();
        }
        return $definition;
    }
    /**
     * Initialize a table query
     * @param string $table the table to query
     * @return TableQuery
     */
    public function table($table)
    {
        return new TableQuery($this, $this->definition($table));
    }
    /**
     * Parse all tables from the database.
     * @return $this
     */
    public function parseSchema()
    {
        $tables = [];
        switch ($this->driver()) {
            case 'mysql':
            case 'mysqli':
            case 'postgre':
                $tables = $this->all(
                    "SELECT table_name FROM information_schema.tables where table_schema = ?",
                    $this->name()
                );
                break;
            case 'oracle':
                $tables = $this->all(
                    "SELECT TABLE_NAME FROM ALL_TABLES where OWNER = ?",
                    $this->name()
                );
                break;
            case 'sqlite':
                $tables = $this->all("SELECT name FROM sqlite_master WHERE type='table'");
                break;
            case 'ibase':
                $tables = $this->all(
                    'SELECT RDB$RELATION_NAME FROM RDB$RELATIONS
                     WHERE RDB$SYSTEM_FLAG = 0 AND RDB$VIEW_BLR IS NULL
                     ORDER BY RDB$RELATION_NAME'
                );
                break;
            case 'mssql':
                $tables = $this->all(
                    "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
                     WHERE TABLE_TYPE='BASE TABLE' AND TABLE_CATALOG = ?",
                    $this->name()
                );
                break;
            default:
                //throw new DatabaseException('Unsupported driver');
                break;
        }
        foreach ($tables as $table) {
            $this->definition($table);
        }
        return $this;
    }
    public function __call($method, $args)
    {
        return $this->table($method);
    }
    /**
     * Get the full schema as an array that you can serialize and store
     * @return array
     */
    public function getSchema()
    {
        return array_map(function ($table) {
            return [
                'name' => $table->getName(),
                'pkey' => $table->getPrimaryKey(),
                'comment' => $table->getComment(),
                'columns' => array_map(function ($column) {
                    return [
                        'name' => $column->getName(),
                        'type' => $column->getType(),
                        'comment' => $column->getComment(),
                        'values' => $column->getValues(),
                        'default' => $column->getDefault(),
                        'nullable' => $column->isNullable()
                    ];
                }, $table->getFullColumns()),
                'relations' => array_map(function ($rel) {
                    $relation = clone $rel;
                    $relation->table = $relation->table->getName();
                    if ($relation->pivot) {
                        $relation->pivot = $relation->pivot->getName();
                    }
                    return (array)$relation;
                }, $table->getRelations())
            ];
        }, $this->tables);
    }
    /**
     * Load the schema data from a schema definition array (obtained from getSchema)
     * @param  array        $data the schema definition
     * @return $this
     */
    public function setSchema(array $data)
    {
        foreach ($data as $tableData) {
            $this->tables[$tableData['name']] = (new Table($tableData['name']))
                        ->setPrimaryKey($tableData['pkey'])
                        ->setComment($tableData['comment'])
                        ->addColumns($tableData['columns']);
        }
        foreach ($data as $tableData) {
            $table = $this->definition($tableData['name']);
            foreach ($tableData['relations'] as $relationName => $relationData) {
                $relationData['table'] = $this->definition($relationData['table']);
                if ($relationData['pivot']) {
                    $relationData['pivot'] = $this->definition($relationData['pivot']);
                }
                $table->addRelation(new TableRelation(
                    $relationData['name'],
                    $relationData['table'],
                    $relationData['keymap'],
                    $relationData['many'],
                    $relationData['pivot'] ?? null,
                    $relationData['pivot_keymap'],
                    $relationData['sql'],
                    $relationData['par']
                ));
            }
        }
        return $this;
    }
}