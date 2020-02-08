<?php

namespace vakata\database\driver\mysql;

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
    protected $connection;
    abstract public function query(string $sql, $par = null) : ResultInterface;

    public function table(string $table, bool $detectRelations = true) : Table
    {
        static $tables = [];
        if (isset($tables[$table])) {
            return $tables[$table];
        }

        static $comments = null;
        if (!isset($comments)) {
            $comments = Collection::from(
                $this->query(
                    "SELECT TABLE_NAME, TABLE_COMMENT FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ?",
                    [ $this->connection['opts']['schema'] ?? $this->connection['name'] ]
                )
            )
            ->mapKey(function ($v) {
                return $v['TABLE_NAME'];
            })
            ->pluck('TABLE_COMMENT')
            ->toArray();
        }

        static $relationsT = null;
        static $relationsR = null;
        if (!isset($relationsT) || !isset($relationsR)) {
            $relationsT = [];
            $relationsR = [];
            $col = Collection::from(
                $this->query(
                    "SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                     FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                     WHERE
                        TABLE_SCHEMA = ? AND TABLE_NAME IS NOT NULL AND
                        REFERENCED_TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL",
                    [
                        $this->connection['opts']['schema'] ?? $this->connection['name'],
                        $this->connection['opts']['schema'] ?? $this->connection['name']
                    ]
                )
            )->toArray();
            foreach ($col as $row) {
                $relationsT[$row['TABLE_NAME']][] = $row;
                $relationsR[$row['REFERENCED_TABLE_NAME']][] = $row;
            }
        }

        
        $columns = Collection::from($this->query("SHOW FULL COLUMNS FROM {$table}"));
        if (!count($columns)) {
            throw new DBException('Table not found by name');
        }
        $tables[$table] = $definition = (new Table($table))
            ->addColumns(
                $columns
                    ->clone()
                    ->mapKey(function ($v) {
                        return $v['Field'];
                    })
                    ->map(function ($v) {
                        $v['length'] = null;
                        if (!isset($v['Type'])) {
                            return $v;
                        }
                        $type = strtolower($v['Type']);
                        switch ($type) {
                            case 'tinytext':
                                $v['length'] = 255;
                                break;
                            case 'text':
                                $v['length'] = 65535;
                                break;
                            case 'mediumtext':
                                $v['length'] = 16777215;
                                break;
                            case 'longtext':
                                // treat this as no limit
                                break;
                            default:
                                if (strpos($type, 'char') !== false && strpos($type, '(') !== false) {
                                    // extract length from varchar
                                    $v['length'] = (int)explode(')', explode('(', $type)[1])[0];
                                    $v['length'] = $v['length'] > 0 ? $v['length'] : null;
                                }
                                break;
                        }
                        return $v;
                    })
                    ->toArray()
            )
            ->setPrimaryKey(
                $columns
                    ->clone()
                    ->filter(function ($v) {
                        return $v['Key'] === 'PRI';
                    })
                    ->pluck('Field')
                    ->toArray()
            )
            ->setComment($comments[$table] ?? '');

        if ($detectRelations) {
            // relations where the current table is referenced
            // assuming current table is on the "one" end having "many" records in the referencing table
            // resulting in a "hasMany" or "manyToMany" relationship (if a pivot table is detected)
            $relations = [];
            foreach ($relationsR[$table] ?? [] as $relation) {
                $relations[$relation['CONSTRAINT_NAME']]['table'] = $relation['TABLE_NAME'];
                $relations[$relation['CONSTRAINT_NAME']]['keymap'][$relation['REFERENCED_COLUMN_NAME']] =
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
                    foreach (Collection::from($relationsT[$data['table']] ?? [])
                        ->filter(function ($v) use ($columns) {
                            return in_array($v['COLUMN_NAME'], $columns);
                        })
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
                            $relation['REFERENCED_COLUMN_NAME'];
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
            foreach (Collection::from($relationsT[$table] ?? [])
                ->map(function ($v) {
                    $new = [];
                    foreach ($v as $kk => $vv) {
                        $new[strtoupper($kk)] = $vv;
                    }
                    return $new;
                }) as $relation
            ) {
                $relations[$relation['CONSTRAINT_NAME']]['table'] = $relation['REFERENCED_TABLE_NAME'];
                $relations[$relation['CONSTRAINT_NAME']]['keymap'][$relation['COLUMN_NAME']] =
                    $relation['REFERENCED_COLUMN_NAME'];
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
                "SELECT table_name FROM information_schema.tables where table_schema = ?",
                [$this->connection['opts']['schema'] ?? $this->connection['name']]
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
