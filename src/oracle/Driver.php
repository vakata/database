<?php

namespace vakata\database\oracle;

use \vakata\database\DBException;
use \vakata\database\DriverInterface;
use \vakata\database\StatementInterface;

class Driver implements DriverInterface
{
    protected $connection;
    protected $lnk = null;

    public function __construct(array $connection)
    {
        $this->connection = $connection;
    }
    public function __destruct()
    {
        $this->disconnect();
    }
    protected function connect()
    {
        if ($this->lnk === null) {
            $this->lnk = call_user_func(
                $this->option('persist') ? 'oci_pconnect' : 'oci_connect',
                $this->connection['user'],
                $this->connection['pass'],
                $this->connection['name'],
                $this->option('charset')
            );
            if ($this->lnk === false) {
                throw new DBException('Connect error');
            }
            $this->real("ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS'");
            if ($this->option('timezone')) {
                $this->real("ALTER session SET time_zone = '".addslashes($this->option('timezone'))."'");
            }
        }
    }
    protected function real($sql)
    {
        $this->connect();
        $temp = oci_parse($this->lnk, $sql);
        if (!$temp || !oci_execute($temp, $this->transaction ? OCI_NO_AUTO_COMMIT : OCI_COMMIT_ON_SUCCESS)) {
            throw new DatabaseException('Could not execute real query : '.oci_error($temp));
        }
        return $temp;
    }
    protected function disconnect()
    {
        if ($this->lnk !== null) {
            @$this->lnk->close();
        }
    }
    public function prepare(string $sql) : StatementInterface
    {
        $this->connect();
        $binder = '?';
        if (strpos($sql, $binder) !== false) {
            $tmp = explode($binder, $sql);
            $sql = '';
            foreach ($tmp as $i => $v) {
                $sql .= $v;
                if (isset($tmp[($i + 1)])) {
                    $sql .= ':f'.$i;
                }
            }
        }
        $temp = oci_parse($this->lnk, $sql);
        if (!$temp) {
            $err = oci_error();
            if (!$err) {
                $err = [];
            }
            throw new DBException('Could not prepare : '.implode(', ', $err).' <'.$sql.'>');
        }
        return new Statement($temp);
    }

    public function begin() : bool
    {
         return $this->transaction = true;
    }
    public function commit() : bool
    {
        $this->connect();
        if (!$this->transaction) {
            return false;
        }
        if (!oci_commit($this->lnk)) {
            return false;
        }
        $this->transaction = false;
        return true;
    }
    public function rollback() : bool
    {
        $this->connect();
        if (!$this->transaction) {
            return false;
        }
        if (!oci_rollback($this->lnk)) {
            return false;
        }
        $this->transaction = false;
        return true;
    }

    public function name() : string
    {
        return $this->connection['name'];
    }
    public function option($key, $default = null)
    {
        return isset($this->connection['opts'][$key]) ? $this->connection['opts'][$key] : $default;
    }

    public function isTransaction()
    {
        return $this->transaction;
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
}