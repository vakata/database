<?php

namespace vakata\database\driver\oracle;

use \vakata\database\DBException;
use \vakata\database\DriverInterface;
use \vakata\database\DriverAbstract;
use \vakata\database\StatementInterface;
use \vakata\database\schema\Table;
use \vakata\database\schema\TableRelation;
use \vakata\collection\Collection;

class Driver extends DriverAbstract implements DriverInterface
{
    protected $lnk = null;
    protected $transaction = false;

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
            $this->lnk = @call_user_func(
                $this->option('persist') ? '\oci_pconnect' : '\oci_connect',
                $this->connection['user'],
                $this->connection['pass'],
                $this->connection['host'],
                $this->option('charset', 'utf8')
            );
            if ($this->lnk === false) {
                throw new DBException('Connect error');
            }
            $this->query("ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS'");
            if ($timezone = $this->option('timezone')) {
                $this->query("ALTER session SET time_zone = '".addslashes($timezone)."'");
            }
        }
    }
    public function test() : bool
    {
        if ($this->lnk) {
            return true;
        }
        try {
            @$this->connect();
            return true;
        } catch (\Exception $e) {
            $this->lnk = null;
            return false;
        }
    }
    protected function disconnect()
    {
        if ($this->lnk !== null && $this->lnk !== false) {
            \oci_close($this->lnk);
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
        $temp = \oci_parse($this->lnk, $sql);
        if (!$temp) {
            $err = \oci_error();
            if (!is_array($err)) {
                $err = [];
            }
            throw new DBException('Could not prepare : '.implode(', ', $err).' <'.$sql.'>');
        }
        return new Statement($temp, $this);
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
        if (!\oci_commit($this->lnk)) {
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
        if (!\oci_rollback($this->lnk)) {
            return false;
        }
        $this->transaction = false;
        return true;
    }

    public function isTransaction()
    {
        return $this->transaction;
    }

    public function lob()
    {
        return \oci_new_descriptor($this->lnk, \OCI_D_LOB);
    }

    public function table(
        string $table,
        bool $detectRelations = true
    ) : Table
    {
        static $tables = [];
        if (isset($tables[$table])) {
            return $tables[$table];
        }

        $columns = Collection::from($this
            ->query(
                "SELECT * FROM all_tab_cols WHERE table_name = ? AND owner = ?",
                [ strtoupper($table), $this->name() ]
            ))
            ->map(function ($v) {
                $new = [];
                foreach ($v as $kk => $vv) {
                    $new[strtoupper($kk)] = $vv;
                }
                return $new;
            })
            ->mapKey(function ($v) { return $v['COLUMN_NAME']; })
            ->map(function ($v) {
                $v['length'] = null;
                if (!isset($v['DATA_TYPE'])) {
                    return $v;
                }
                $type = strtolower($v['DATA_TYPE']);
                switch ($type) {
                    case 'clob': // unlimited
                        break;
                    default:
                        if (strpos($type, 'char') !== false && strpos($type, '(') !== false) {
                            // extract length from varchar
                            $v['length'] = (int)explode(')', (explode('(', $type)[1] ?? ''))[0];
                            $v['length'] = $v['length'] > 0 ? $v['length'] : null;
                        }
                        break;
                }
                return $v;
            })
            ->toArray();
        if (!count($columns)) {
            throw new DBException('Table not found by name');
        }
        $owner = $this->name(); // used to be the current column's OWNER
        $pkname = Collection::from($this
            ->query(
                "SELECT constraint_name FROM all_constraints
                WHERE table_name = ? AND constraint_type = ? AND owner = ?",
                [ strtoupper($table), 'P', $owner ]
            ))
            ->map(function ($v) {
                $new = [];
                foreach ($v as $kk => $vv) {
                    $new[strtoupper($kk)] = $vv;
                }
                return $new;
            })
            ->pluck('CONSTRAINT_NAME')
            ->value();
        $primary = [];
        if ($pkname) {
            $primary = Collection::from($this
                ->query(
                    "SELECT column_name FROM all_cons_columns
                    WHERE table_name = ? AND constraint_name = ? AND owner = ?",
                    [ strtoupper($table), $pkname, $owner ]
                ))
                ->map(function ($v) {
                    $new = [];
                    foreach ($v as $kk => $vv) {
                        $new[strtoupper($kk)] = $vv;
                    }
                    return $new;
                })
                ->pluck('COLUMN_NAME')
                ->toArray();
        }
        $tables[$table] = $definition = (new Table($table))
            ->addColumns($columns)
            ->setPrimaryKey($primary)
            ->setComment('');

        if ($detectRelations) {
            // relations where the current table is referenced
            // assuming current table is on the "one" end having "many" records in the referencing table
            // resulting in a "hasMany" or "manyToMany" relationship (if a pivot table is detected)
            $relations = [];
            foreach (Collection::from($this
                ->query(
                    "SELECT ac.TABLE_NAME, ac.CONSTRAINT_NAME, cc.COLUMN_NAME, cc.POSITION
                    FROM all_constraints ac
                    LEFT JOIN all_cons_columns cc ON cc.OWNER = ac.OWNER AND cc.CONSTRAINT_NAME = ac.CONSTRAINT_NAME
                    WHERE ac.OWNER = ? AND ac.R_OWNER = ? AND ac.R_CONSTRAINT_NAME = ? AND ac.CONSTRAINT_TYPE = ?
                    ORDER BY cc.POSITION",
                    [ $owner, $owner, $pkname, 'R' ]
                ))
                ->map(function ($v) {
                    $new = [];
                    foreach ($v as $kk => $vv) {
                        $new[strtoupper($kk)] = $vv;
                    }
                    return $new;
                })
                 as $relation
            ) {
                $relations[$relation['CONSTRAINT_NAME']]['table'] = $relation['TABLE_NAME'];
                $relations[$relation['CONSTRAINT_NAME']]['keymap'][$primary[(int)$relation['POSITION']-1]] = $relation['COLUMN_NAME'];
            }
            foreach ($relations as $data) {
                $rtable = $this->table($data['table'], true);
                $columns = [];
                foreach ($rtable->getColumns() as $column) {
                    if (!in_array($column, $data['keymap'])) {
                        $columns[] = $column;
                    }
                }
                $foreign = [];
                $usedcol = [];
                if (count($columns)) {
                    foreach (Collection::from($this
                        ->query(
                            "SELECT
                                cc.COLUMN_NAME, ac.CONSTRAINT_NAME, rc.TABLE_NAME AS REFERENCED_TABLE_NAME, ac.R_CONSTRAINT_NAME
                            FROM all_constraints ac
                            JOIN all_constraints rc ON rc.CONSTRAINT_NAME = ac.R_CONSTRAINT_NAME AND rc.OWNER = ac.OWNER
                            LEFT JOIN all_cons_columns cc ON cc.OWNER = ac.OWNER AND cc.CONSTRAINT_NAME = ac.CONSTRAINT_NAME
                            WHERE
                                ac.OWNER = ? AND ac.R_OWNER = ? AND ac.TABLE_NAME = ? AND ac.CONSTRAINT_TYPE = ? AND
                                cc.COLUMN_NAME IN (??)
                            ORDER BY POSITION",
                            [ $owner, $owner, $data['table'], 'R', $columns ]
                        ))
                        ->map(function ($v) {
                            $new = [];
                            foreach ($v as $kk => $vv) {
                                $new[strtoupper($kk)] = $vv;
                            }
                            return $new;
                        }) as $relation
                    ) {
                        $foreign[$relation['CONSTRAINT_NAME']]['table'] = $relation['REFERENCED_TABLE_NAME'];
                        $foreign[$relation['CONSTRAINT_NAME']]['keymap'][$relation['COLUMN_NAME']] = $relation['R_CONSTRAINT_NAME'];
                        $usedcol[] = $relation['COLUMN_NAME'];
                    }
                }
                if (count($foreign) === 1 && !count(array_diff($columns, $usedcol))) {
                    $foreign = current($foreign);
                    $rcolumns = Collection::from($this
                        ->query(
                            "SELECT COLUMN_NAME FROM all_cons_columns WHERE OWNER = ? AND CONSTRAINT_NAME = ? ORDER BY POSITION",
                            [ $owner, current($foreign['keymap']) ]
                        ))
                        ->map(function ($v) {
                            $new = [];
                            foreach ($v as $kk => $vv) {
                                $new[strtoupper($kk)] = $vv;
                            }
                            return $new;
                        })
                        ->pluck('COLUMN_NAME')
                        ->toArray();
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
                            $this->table($foreign['table'], true),
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
                            $this->table($data['table'], true),
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
            foreach (Collection::from($this
                ->query(
                    "SELECT ac.CONSTRAINT_NAME, cc.COLUMN_NAME, rc.TABLE_NAME AS REFERENCED_TABLE_NAME, ac.R_CONSTRAINT_NAME
                    FROM all_constraints ac
                    JOIN all_constraints rc ON rc.CONSTRAINT_NAME = ac.R_CONSTRAINT_NAME AND rc.OWNER = ac.OWNER
                    LEFT JOIN all_cons_columns cc ON cc.OWNER = ac.OWNER AND cc.CONSTRAINT_NAME = ac.CONSTRAINT_NAME
                    WHERE ac.OWNER = ? AND ac.R_OWNER = ? AND ac.TABLE_NAME = ? AND ac.CONSTRAINT_TYPE = ?
                    ORDER BY cc.POSITION",
                    [ $owner, $owner, strtoupper($table), 'R' ]
                ))
                ->map(function ($v) {
                    $new = [];
                    foreach ($v as $kk => $vv) {
                        $new[strtoupper($kk)] = $vv;
                    }
                    return $new;
                })
                as $relation
            ) {
                $relations[$relation['CONSTRAINT_NAME']]['table'] = $relation['REFERENCED_TABLE_NAME'];
                $relations[$relation['CONSTRAINT_NAME']]['keymap'][$relation['COLUMN_NAME']] = $relation['R_CONSTRAINT_NAME'];
            }
            foreach ($relations as $name => $data) {
                $rcolumns = Collection::from($this
                    ->query(
                        "SELECT COLUMN_NAME FROM all_cons_columns WHERE OWNER = ? AND CONSTRAINT_NAME = ? ORDER BY POSITION",
                        [ $owner, current($data['keymap']) ]
                    ))
                    ->map(function ($v) {
                        $new = [];
                        foreach ($v as $kk => $vv) {
                            $new[strtoupper($kk)] = $vv;
                        }
                        return $new;
                    })
                    ->pluck('COLUMN_NAME')
                    ->toArray();
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
                        $this->table($data['table'], true),
                        $data['keymap'],
                        false
                    )
                );
            }
        }
        return $definition->toLowerCase();
    }
    public function tables() : array
    {
        return Collection::from($this
            ->query(
                "SELECT TABLE_NAME FROM ALL_TABLES where OWNER = ?",
                [$this->connection['name']]
            ))
            ->map(function ($v) {
                $new = [];
                foreach ($v as $kk => $vv) {
                    $new[strtoupper($kk)] = $vv;
                }
                return $new;
            })
            ->pluck('TABLE_NAME')
            ->map(function ($v) {
                return $this->table($v);
            })
            ->toArray();
    }
}
