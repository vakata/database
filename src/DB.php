<?php

namespace vakata\database;

/**
 * A database abstraction with support for various drivers (mySQL, postgre, oracle, msSQL, sphinx, and even PDO).
 */
class DB implements DatabaseInterface
{
    protected $drv;
    protected $tables;
    protected $settings;

    /**
     * Create an instance.
     *
     *
     * @throws \vakata\database\DatabaseException if invalid settings are provided
     *
     * @param string $options a connection string (like `"mysqli://user:pass@host/database?option=value"`)
     */
    public function __construct($options)
    {
        $this->settings = new Settings((string)$options);
        try {
            $tmp = '\\vakata\\database\\driver\\'.ucfirst($this->settings->type);
            $drv = new $tmp($this->settings);
        } catch (\Exception $e) {
            throw new DatabaseException('Could not create database driver');
        }
        if (!($drv instanceof driver\DriverInterface)) {
            throw new DatabaseException('Invalid database driver');
        }
        $this->drv = $drv;
    }

    protected function expand($sql, $data)
    {
        $new = '';
        if (!is_array($data)) {
            $data = [ $data ];
        }
        $data = array_values($data);
        if (substr_count($sql, '?') === 2 && !is_array($data[0])) {
            $data = [ $data ];
        }
        $parts = explode('??', $sql);
        $index = 0;
        foreach ($parts as $part) {
            $tmp = explode('?', $part);
            $new .= $part;
            $index += count($tmp) - 1;
            if (isset($data[$index])) {
                if (!is_array($data[$index])) {
                    $data[$index] = [ $data[$index] ];
                }
                $params = $data[$index];
                array_splice($data, $index, 1, $params);
                $index += count($params);
                $new .= implode(',', array_fill(0, count($params), '?'));
            }
        }
        return [ $new, $data ];
    }

    /**
     * Prepare a statement.
     * Use only if you need a single query to be performed multiple times with different parameters.
     *
     *
     * @param string $sql the query to prepare - use `?` for arguments
     *
     * @return \vakata\database\Query the prepared statement
     */
    public function prepare($sql)
    {
        try {
            return new Query($this->drv, $sql);
        } catch (\Exception $e) {
            throw new DatabaseException($e->getMessage(), 2);
        }
    }
    /**
     * Run a query (prepare & execute).
     *
     *
     * @param string $sql  SQL query
     * @param array  $data parameters
     *
     * @return \vakata\database\QueryResult the result of the execution
     */
    public function query($sql, $data = null)
    {
        try {
            if (strpos($sql, '??') && $data !== null) {
                list($sql, $data) = $this->expand($sql, $data);
            }
            return $this->prepare($sql)->execute($data);
        } catch (\Exception $e) {
            throw new DatabaseException($e->getMessage(), 4);
        }
    }
    /**
     * Run a SELECT query and get an array-like result.
     * When using `get` the data is kept in the database client and fetched as needed (not in PHP memory as with `all`)
     *
     *
     * @param string $sql      SQL query
     * @param array  $data     parameters
     * @param string $key      column name to use as the array index
     * @param bool   $skip     do not include the column used as index in the value (defaults to `false`)
     * @param string $mode     result mode - `"assoc"` by default, could be `"num"`, `"both"`, `"assoc_ci"`, `"assoc_lc"`, `"assoc_uc"`
     * @param bool   $opti     if a single column is returned - do not use an array wrapper (defaults to `true`)
     *
     * @return \vakata\database\Result the result of the execution - use as a normal array
     */
    public function get($sql, $data = null, $key = null, $skip = false, $mode = null, $opti = true)
    {
        if ($mode === null) {
            $mode = isset($this->settings->options['mode']) ? $this->settings->options['mode'] : 'assoc';
        }
        if (strpos($sql, '??') && $data !== null) {
            list($sql, $data) = $this->expand($sql, $data);
        }
        return (new Query($this->drv, $sql))->execute($data)->result($key, $skip, $mode, $opti);
    }
    /**
     * Run a SELECT query and get an array result.
     *
     *
     * @param string $sql      SQL query
     * @param array  $data     parameters
     * @param string $key      column name to use as the array index
     * @param bool   $skip     do not include the column used as index in the value (defaults to `false`)
     * @param string $mode     result mode - `"assoc"` by default, could be `"num"`, `"both"`, `"assoc_ci"`, `"assoc_lc"`, `"assoc_uc"`
     * @param bool   $opti     if a single column is returned - do not use an array wrapper (defaults to `true`)
     *
     * @return array the result of the execution
     */
    public function all($sql, $data = null, $key = null, $skip = false, $mode = null, $opti = true)
    {
        return $this->get($sql, $data, $key, $skip, $mode, $opti)->get();
    }
    /**
     * Run a SELECT query and get the first row.
     *
     *
     * @param string $sql      SQL query
     * @param array  $data     parameters
     * @param string $mode     result mode - `"assoc"` by default, could be `"num"`, `"both"`, `"assoc_ci"`, `"assoc_lc"`, `"assoc_uc"`
     * @param bool   $opti     if a single column is returned - do not use an array wrapper (defaults to `true`)
     *
     * @return mixed the result of the execution
     */
    public function one($sql, $data = null, $mode = null, $opti = true)
    {
        return $this->get($sql, $data, null, false, $mode, $opti)->one();
    }
    /**
     * Run a raw SQL query
     *
     *
     * @param string $sql      SQL query
     *
     * @return mixed the result of the execution
     */
    public function raw($sql, $data = null, $mode = 'assoc', $opti = true)
    {
        return $this->drv->real($sql);
    }
    /**
     * Get the current driver name (`"mysqli"`, `"postgre"`, etc).
     *
     *
     * @return string the current driver name
     */
    public function driver()
    {
        return $this->settings->type;
    }
    /**
     * Get the current database name.
     *
     *
     * @return string the current database name
     */
    public function name()
    {
        return $this->settings->database;
    }
    /**
     * Get the current settings object
     *
     *
     * @return \vakata\database\Settings the current settings
     */
    public function settings()
    {
        return $this->settings;
    }
    /**
     * Begin a transaction.
     *
     *
     * @return bool `true` if a transaction was opened, `false` otherwise
     */
    public function begin()
    {
        if ($this->drv->isTransaction()) {
            return false;
        }

        return $this->drv->begin();
    }
    /**
     * Commit a transaction.
     *
     *
     * @return bool was the commit successful
     */
    public function commit($isTransaction = true)
    {
        return $isTransaction && $this->drv->isTransaction() && $this->drv->commit();
    }
    /**
     * Rollback a transaction.
     *
     *
     * @return bool was the rollback successful
     */
    public function rollback($isTransaction = true)
    {
        return $isTransaction && $this->drv->isTransaction() && $this->drv->rollback();
    }
    /**
     * Check if a transaciton is currently open.
     *
     *
     * @return bool is a transaction currently open
     */
    public function isTransaction()
    {
        return $this->drv->isTransaction();
    }
    /**
     * Get a table definition
     * @param  string            $table the table to analyze
     * @param  bool      $detectRelations should relations be extracted - defaults to `true`
     * @param  bool      $lowerCase should the table fields be converted to lowercase - defaults to `true`
     * @return  the newly added definition
     */
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
                foreach ($this->all("SHOW FULL COLUMNS FROM {$table}", null, null, false, 'assoc') as $data) {
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
                    'assoc_lc'
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
                    'assoc_uc'
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
            //        'assoc_uc'
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
                        'assoc_uc'
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
                                'assoc_uc'
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
                                $relname,
                                [
                                    'name' => $relname,
                                    'table' => $this->definition($foreign['table'], true, $lowerCase),
                                    'keymap' => $data['keymap'],
                                    'many' => true,
                                    'pivot' => $rtable,
                                    'pivot_keymap' => $foreign['keymap'],
                                    'sql' => null,
                                    'par' => []
                                ]
                            );
                        } else {
                            $relname = $data['table'];
                            $cntr = 1;
                            while ($definition->hasRelation($relname) || $definition->getName() == $relname) {
                                $relname = $data['table'] . '_' . (++ $cntr);
                            }
                            $definition->addRelation(
                                $relname,
                                [
                                    'name' => $relname,
                                    'table' => $this->definition($data['table'], true, $lowerCase),
                                    'keymap' => $data['keymap'],
                                    'many' => true,
                                    'pivot' => null,
                                    'pivot_keymap' => [],
                                    'sql' => null,
                                    'par' => []
                                ]
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
                        'assoc_uc'
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
                            $relname,
                            [
                                'name' => $relname,
                                'table' => $this->definition($data['table'], true, $lowerCase),
                                'keymap' => $data['keymap'],
                                'many' => false,
                                'pivot' => null,
                                'pivot_keymap' => [],
                                'sql' => null,
                                'par' => []
                            ]
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
                        'assoc_uc'
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
                                'assoc_uc'
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
                                null, false, 'assoc_uc'
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
                                $relname,
                                [
                                    'name' => $relname,
                                    'table' => $this->definition($foreign['table'], true, $lowerCase),
                                    'keymap' => $data['keymap'],
                                    'many' => true,
                                    'pivot' => $rtable,
                                    'pivot_keymap' => $foreign['keymap'],
                                    'sql' => null,
                                    'par' => []
                                ]
                            );
                        } else {
                            $relname = $data['table'];
                            $cntr = 1;
                            while ($definition->hasRelation($relname) || $definition->getName() == $relname) {
                                $relname = $data['table'] . '_' . (++ $cntr);
                            }
                            $definition->addRelation(
                                $relname,
                                [
                                    'name' => $relname,
                                    'table' => $this->definition($data['table'], true, $lowerCase),
                                    'keymap' => $data['keymap'],
                                    'many' => true,
                                    'pivot' => null,
                                    'pivot_keymap' => [],
                                    'sql' => null,
                                    'par' => []
                                ]
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
                        null, false, 'assoc_uc'
                    ) as $relation) {
                        $relations[$relation['CONSTRAINT_NAME']]['table'] = $relation['REFERENCED_TABLE_NAME'];
                        $relations[$relation['CONSTRAINT_NAME']]['keymap'][$relation['COLUMN_NAME']] = $relation['R_CONSTRAINT_NAME'];
                    }
                    foreach ($relations as $name => $data) {
                        $rcolumns = $this->all(
                            "SELECT COLUMN_NAME FROM all_cons_columns WHERE OWNER = ? AND CONSTRAINT_NAME = ? ORDER BY POSITION",
                            [ $owner, current($data['keymap']) ],
                            null, false, 'assoc_uc'
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
                            $relname,
                            [
                                'name' => $relname,
                                'table' => $this->definition($data['table'], true, $lowerCase),
                                'keymap' => $data['keymap'],
                                'many' => false,
                                'pivot' => null,
                                'pivot_keymap' => [],
                                'sql' => null,
                                'par' => []
                            ]
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
    public function table($table)
    {
        return new TableQuery($this, $this->definition($table));
    }
    /**
     * Parse all tables from the database.
     * @return self
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
                'relations' => array_map(function ($relation) {
                    $relation['table'] = $relation['table']->getName();
                    if ($relation['pivot']) {
                        $relation['pivot'] = $relation['pivot']->getName();
                    }
                    return $relation;
                }, $table->getRelations())
            ];
        }, $this->tables);
    }
    /**
     * Load the schema data from a schema definition array (obtained from getSchema)
     * @param  array        $data the schema definition
     * @return self
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
                $table->addRelation($relationName, $relationData);
            }
        }
        return $this;
    }
}
