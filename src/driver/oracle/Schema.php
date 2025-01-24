<?php

namespace vakata\database\driver\oracle;

use \vakata\database\DBException;
use \vakata\database\DriverInterface;
use \vakata\database\DriverAbstract;
use \vakata\database\StatementInterface;
use \vakata\database\ResultInterface;
use \vakata\database\schema\Table;
use \vakata\database\schema\TableRelation;
use \vakata\collection\Collection;

trait Schema
{
    protected array $connection;
    abstract public function query(string $sql, $par = null, bool $buff = true) : ResultInterface;
    abstract public function name() : string;

    public function table(
        string $table,
        bool $detectRelations = true
    ) : Table {
        static $tables = [];
        if (isset($tables[$table])) {
            return $tables[$table];
        }

        /**
         * @var array<string,array<string,mixed>>
         */
        $columns = Collection::from($this
            ->query(
                "SELECT * FROM user_tab_cols WHERE UPPER(table_name) = ?",
                [ strtoupper($table) ]
            ))
            ->map(function ($v) {
                $new = [];
                foreach ($v as $kk => $vv) {
                    $new[strtoupper($kk)] = $vv;
                }
                return $new;
            })
            ->mapKey(function ($v): string {
                return $v['COLUMN_NAME'];
            })
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
                $v['hidden'] = $v['HIDDEN_COLUMN'] !== 'NO';
                return $v;
            })
            ->toArray();
        if (!count($columns)) {
            throw new DBException('Table not found by name');
        }
        $owner = strtoupper($this->connection['opts']['schema'] ?? $this->name());
        $pkname = Collection::from($this
            ->query(
                "SELECT constraint_name FROM user_constraints
                WHERE table_name = ? AND constraint_type = ?",
                [ strtoupper($table), 'P' ]
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
                    "SELECT column_name FROM user_cons_columns
                    WHERE table_name = ? AND constraint_name = ?",
                    [ strtoupper($table), $pkname ]
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
            $relationsT = Collection::from($this
                ->query(
                    "SELECT
                        ac.CONSTRAINT_NAME,
                        cc.COLUMN_NAME,
                        rc.TABLE_NAME AS REFERENCED_TABLE_NAME,
                        ac.R_CONSTRAINT_NAME
                    FROM user_constraints ac
                    JOIN user_constraints rc ON rc.CONSTRAINT_NAME = ac.R_CONSTRAINT_NAME
                    LEFT JOIN user_cons_columns cc ON cc.CONSTRAINT_NAME = ac.CONSTRAINT_NAME
                    WHERE ac.TABLE_NAME = ? AND ac.CONSTRAINT_TYPE = ?
                    ORDER BY cc.POSITION",
                    [ strtoupper($table), 'R' ]
                ))
                ->map(function ($v) {
                    $new = [];
                    foreach ($v as $kk => $vv) {
                        $new[strtoupper($kk)] = $vv;
                    }
                    return $new;
                })
                ->toArray();
            $relationsR = Collection::from($this
                ->query(
                    "SELECT ac.TABLE_NAME, ac.CONSTRAINT_NAME, cc.COLUMN_NAME, cc.POSITION
                    FROM user_constraints ac
                    LEFT JOIN user_cons_columns cc ON cc.CONSTRAINT_NAME = ac.CONSTRAINT_NAME
                    WHERE
                        ac.R_CONSTRAINT_NAME = ? AND ac.CONSTRAINT_TYPE = ?
                    ORDER BY cc.POSITION",
                    [ $pkname, 'R' ]
                ))
                ->map(function ($v) {
                    $new = [];
                    foreach ($v as $kk => $vv) {
                        $new[strtoupper($kk)] = $vv;
                    }
                    return $new;
                })
                ->toArray();
            $duplicated = [];
            foreach ($relationsT as $relation) {
                $t = $relation['REFERENCED_TABLE_NAME'];
                $duplicated[$t] = isset($duplicated[$t]);
            }
            foreach ($relationsR as $relation) {
                $t = $relation['TABLE_NAME'];
                $duplicated[$t] = isset($duplicated[$t]);
            }
            // pivot relations where the current table is referenced
            // assuming current table is on the "one" end having "many" records in the referencing table
            // resulting in a "hasMany" or "manyToMany" relationship (if a pivot table is detected)
            $relations = [];
            foreach ($relationsR as $relation) {
                $relations[$relation['CONSTRAINT_NAME']]['table'] = $relation['TABLE_NAME'];
                $relations[$relation['CONSTRAINT_NAME']]['keymap'][$primary[(int)$relation['POSITION']-1]] =
                    $relation['COLUMN_NAME'];
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
                                cc.COLUMN_NAME,
                                ac.CONSTRAINT_NAME,
                                rc.TABLE_NAME AS REFERENCED_TABLE_NAME,
                                ac.R_CONSTRAINT_NAME
                            FROM user_constraints ac
                            JOIN user_constraints rc ON
                                rc.CONSTRAINT_NAME = ac.R_CONSTRAINT_NAME
                            LEFT JOIN user_cons_columns cc ON
                                cc.CONSTRAINT_NAME = ac.CONSTRAINT_NAME
                            WHERE
                                ac.TABLE_NAME = ? AND ac.CONSTRAINT_TYPE = ? AND
                                cc.COLUMN_NAME IN (??)
                            ORDER BY POSITION",
                            [ $data['table'], 'R', $columns ]
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
                        $foreign[$relation['CONSTRAINT_NAME']]['keymap'][$relation['COLUMN_NAME']] =
                            $relation['R_CONSTRAINT_NAME'];
                        $usedcol[] = $relation['COLUMN_NAME'];
                    }
                }
                if (count($foreign) === 1 && !count(array_diff($columns, $usedcol))) {
                    $foreign = current($foreign);
                    $rcolumns = Collection::from($this
                        ->query(
                            "SELECT COLUMN_NAME FROM user_cons_columns
                            WHERE CONSTRAINT_NAME = ? ORDER BY POSITION",
                            [ current($foreign['keymap']) ]
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
                            $definition,
                            $relname,
                            $this->table($foreign['table'], true),
                            $data['keymap'],
                            true,
                            $rtable,
                            $foreign['keymap'],
                            null,
                            null,
                            true
                        )
                    );
                }
            }
            // relations where the current table references another table
            // assuming current table is linked to "one" record in the referenced table
            // resulting in a "belongsTo" relationship
            $relations = [];
            foreach ($relationsT as $relation) {
                $relations[$relation['CONSTRAINT_NAME']]['table'] = $relation['REFERENCED_TABLE_NAME'];
                $relations[$relation['CONSTRAINT_NAME']]['keymap'][$relation['COLUMN_NAME']] =
                    $relation['R_CONSTRAINT_NAME'];
            }
            foreach ($relations as $name => $data) {
                $rcolumns = Collection::from($this
                    ->query(
                        "SELECT COLUMN_NAME FROM user_cons_columns
                         WHERE CONSTRAINT_NAME = ? ORDER BY POSITION",
                        [ current($data['keymap']) ]
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
                if ($duplicated[$data['table']] ||
                    $definition->hasRelation($relname) ||
                    $definition->getName() == $relname ||
                    $definition->getColumn($relname)
                ) {
                    $relname = array_keys($data['keymap'])[0] . '_' . $relname;
                }
                $orig = $relname;
                $cntr = 1;
                while (
                    $definition->hasRelation($relname) ||
                    $definition->getName() == $relname ||
                    $definition->getColumn($relname)
                ) {
                    $relname = $orig . '_' . (++ $cntr);
                }
                $definition->addRelation(
                    new TableRelation(
                        $definition,
                        $relname,
                        $this->table($data['table'], true),
                        $data['keymap'],
                        false
                    )
                );
            }
            // non-pivot relations where the current table is referenced
            // assuming current table is on the "one" end having "many" records in the referencing table
            // resulting in a "hasMany" or "manyToMany" relationship (if a pivot table is detected)
            $relations = [];
            foreach ($relationsR as $relation) {
                $relations[$relation['CONSTRAINT_NAME']]['table'] = $relation['TABLE_NAME'];
                $relations[$relation['CONSTRAINT_NAME']]['keymap'][$primary[(int)$relation['POSITION']-1]] =
                    $relation['COLUMN_NAME'];
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
                                cc.COLUMN_NAME,
                                ac.CONSTRAINT_NAME,
                                rc.TABLE_NAME AS REFERENCED_TABLE_NAME,
                                ac.R_CONSTRAINT_NAME
                            FROM user_constraints ac
                            JOIN user_constraints rc ON
                                rc.CONSTRAINT_NAME = ac.R_CONSTRAINT_NAME
                            LEFT JOIN user_cons_columns cc ON
                                cc.CONSTRAINT_NAME = ac.CONSTRAINT_NAME
                            WHERE
                                ac.TABLE_NAME = ? AND ac.CONSTRAINT_TYPE = ? AND
                                cc.COLUMN_NAME IN (??)
                            ORDER BY POSITION",
                            [ $data['table'], 'R', $columns ]
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
                        $foreign[$relation['CONSTRAINT_NAME']]['keymap'][$relation['COLUMN_NAME']] =
                            $relation['R_CONSTRAINT_NAME'];
                        $usedcol[] = $relation['COLUMN_NAME'];
                    }
                }
                if (count($foreign) !== 1 || count(array_diff($columns, $usedcol))) {
                    $relname = $data['table'];
                    if ($duplicated[$data['table']] ||
                        $definition->hasRelation($relname) ||
                        $definition->getName() == $relname ||
                        $definition->getColumn($relname)
                    ) {
                        $relname .= '_' . array_values($data['keymap'])[0];
                    }
                    $orig = $relname;
                    $cntr = 1;
                    while (
                        $definition->hasRelation($relname) ||
                        $definition->getName() == $relname ||
                        $definition->getColumn($relname)
                    ) {
                        $relname = $orig . '_' . (++ $cntr);
                    }
                    $definition->addRelation(
                        new TableRelation(
                            $definition,
                            $relname,
                            $this->table($data['table'], true),
                            $data['keymap'],
                            true,
                            null,
                            null,
                            null,
                            null,
                            true
                        )
                    );
                }
            }
        }
        return $definition;
    }
    public function tables() : array
    {
        return Collection::from($this
            ->query(
                "SELECT TABLE_NAME AS TABLE_NAME FROM USER_TABLES
                 UNION
                 SELECT VIEW_NAME AS TABLE_NAME FROM USER_VIEWS",
                []
            ))
            ->map(function ($v) {
                $new = [];
                foreach ($v as $kk => $vv) {
                    $new[strtoupper($kk)] = $vv;
                }
                return $new;
            })
            ->mapKey(function ($v) {
                return strtolower($v['TABLE_NAME']);
            })
            ->pluck('TABLE_NAME')
            ->map(function ($v) {
                return $this->table($v)->toLowerCase();
            })
            ->toArray();
    }
}
