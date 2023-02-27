<?php

namespace vakata\database\driver\postgre;

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

    public function table(string $table, bool $detectRelations = true) : Table
    {
        static $tables = [];

        $catalog = $this->connection['name'];
        $main = $this->connection['opts']['schema'] ?? 'public';
        $schema = $main;
        if (strpos($table, '.')) {
            $temp = explode('.', $table, 2);
            $schema = $temp[0];
            $table = $temp[1];
        }

        if (isset($tables[$schema . '.' . $table])) {
            return $tables[$schema . '.' . $table];
        }

        static $relationsT = null;
        static $relationsR = null;
        if (!isset($relationsT) || !isset($relationsR)) {
            $relationsT = [];
            $relationsR = [];
            $additional = [];
            $col = Collection::from(
                $this->query(
                    "SELECT
                        kc.table_schema,
                        kc.table_name,
                        kc.column_name,
                        kc.constraint_name,
                        ct.table_name AS referenced_table_name,
                        ct.table_schema AS referenced_table_schema,
                        (SELECT column_name
                        FROM information_schema.constraint_column_usage
                        WHERE constraint_name = kc.constraint_name AND table_name = ct.table_name
                        LIMIT 1 OFFSET kc.position_in_unique_constraint - 1
                        ) AS referenced_column_name
                    FROM information_schema.key_column_usage kc
                    JOIN information_schema.constraint_table_usage ct ON
                        kc.constraint_name = ct.constraint_name AND ct.table_catalog = kc.table_catalog
                    WHERE
                        kc.table_catalog = ? AND
                        (kc.table_schema = ? OR ct.table_schema = ?) AND
                        kc.table_name IS NOT NULL AND
                        kc.position_in_unique_constraint IS NOT NULL",
                    [ $catalog, $main, $main ]
                )
            )->toArray();
            foreach ($col as $row) {
                if ($row['table_schema'] !== $main) {
                    $additional[] = $row['table_schema'];
                }
                if ($row['referenced_table_schema'] !== $main) {
                    $additional[] = $row['referenced_table_schema'];
                }
                $relationsT[$row['table_schema'] . '.' . $row['table_name']][] = $row;
                $relationsR[$row['referenced_table_schema'] . '.' . $row['referenced_table_name']][] = $row;
            }
            foreach (array_filter(array_unique($additional)) as $s) {
                $col = Collection::from(
                    $this->query(
                        "SELECT
                            kc.table_schema,
                            kc.table_name,
                            kc.column_name,
                            kc.constraint_name,
                            ct.table_name AS referenced_table_name,
                            ct.table_schema AS referenced_table_schema,
                            (SELECT column_name
                            FROM information_schema.constraint_column_usage
                            WHERE constraint_name = kc.constraint_name AND table_name = ct.table_name
                            LIMIT 1 OFFSET kc.position_in_unique_constraint - 1
                            ) AS referenced_column_name
                        FROM information_schema.key_column_usage kc
                        JOIN information_schema.constraint_table_usage ct ON
                            kc.constraint_name = ct.constraint_name AND ct.table_catalog = kc.table_catalog
                        WHERE
                            kc.table_catalog = ? AND
                            (kc.table_schema = ? AND ct.table_schema = ?) AND
                            kc.table_name IS NOT NULL AND
                            kc.position_in_unique_constraint IS NOT NULL",
                        [ $catalog, $s, $s ]
                    )
                )->toArray();
                foreach ($col as $row) {
                    $relationsT[$row['table_schema'] . '.' . $row['table_name']][] = $row;
                    $relationsR[$row['referenced_table_schema'] . '.' . $row['referenced_table_name']][] = $row;
                }
            }
        }

        $columns = Collection::from($this
            ->query(
                "SELECT * FROM information_schema.columns WHERE table_name = ? AND table_schema = ? AND table_catalog = ?",
                [ $table, $schema, $catalog ]
            ))
            ->mapKey(function ($v) {
                return $v['column_name'];
            })
            ->map(function ($v) {
                $v['length'] = null;
                if (!isset($v['data_type'])) {
                    return $v;
                }
                switch ($v['data_type']) {
                    case 'character':
                    case 'character varying':
                        $v['length'] = (int)$v['character_maximum_length'];
                        break;
                }
                return $v;
            })
            ->toArray();
        if (!count($columns)) {
            throw new DBException('Table not found by name: ' . implode('.', [$schema,$table]));
        }
        $pkname = Collection::from($this
            ->query(
                "SELECT constraint_name FROM information_schema.table_constraints
                WHERE table_name = ? AND constraint_type = ? AND table_schema = ? AND table_catalog = ?",
                [ $table, 'PRIMARY KEY', $schema, $catalog ]
            ))
            ->pluck('constraint_name')
            ->value();
        $primary = [];
        if ($pkname) {
            $primary = Collection::from($this
                ->query(
                    "SELECT column_name FROM information_schema.constraint_column_usage
                     WHERE table_name = ? AND constraint_name = ? AND table_schema = ? AND table_catalog = ?",
                    [ $table, $pkname, $schema, $catalog ]
                ))
                ->pluck('column_name')
                ->toArray();
        }
        $tables[$schema . '.' .$table] = $definition = (new Table($table, $schema))
            ->addColumns($columns)
            ->setPrimaryKey($primary)
            ->setComment('');

        if ($detectRelations) {
            // relations where the current table is referenced
            // assuming current table is on the "one" end having "many" records in the referencing table
            // resulting in a "hasMany" or "manyToMany" relationship (if a pivot table is detected)
            $relations = [];
            foreach ($relationsR[$schema . '.' . $table] ?? [] as $relation) {
                $relations[$relation['constraint_name']]['table'] = $relation['table_schema'] . '.' . $relation['table_name'];
                $relations[$relation['constraint_name']]['keymap'][$relation['referenced_column_name']] =
                    $relation['column_name'];
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
                            return in_array($v['column_name'], $columns);
                        }) as $relation
                    ) {
                        $foreign[$relation['constraint_name']]['table'] = $relation['referenced_table_schema'] . '.' . $relation['referenced_table_name'];
                        $foreign[$relation['constraint_name']]['keymap'][$relation['column_name']] =
                            $relation['referenced_column_name'];
                        $usedcol[] = $relation['column_name'];
                    }
                }
                if (count($foreign) === 1 && !count(array_diff($columns, $usedcol))) {
                    $foreign = current($foreign);
                    $relname = $foreign['table'];
                    $temp = explode('.', $relname, 2);
                    if ($temp[0] == $main) {
                        $relname = $temp[1];
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
                    $temp = explode('.', $relname, 2);
                    if ($temp[0] == $main) {
                        $relname = $temp[1];
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
            foreach ($relationsT[$schema . '.' . $table] ?? [] as $relation) {
                $relations[$relation['constraint_name']]['table'] = $relation['referenced_table_schema'] . '.' . $relation['referenced_table_name'];
                $relations[$relation['constraint_name']]['keymap'][$relation['column_name']] =
                    $relation['referenced_column_name'];
            }
            foreach ($relations as $name => $data) {
                $relname = $data['table'];
                $temp = explode('.', $relname, 2);
                if ($temp[0] == $main) {
                    $relname = $temp[1];
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
                        $relname,
                        $this->table($data['table'], true),
                        $data['keymap'],
                        false
                    )
                );
            }
        }
        return $definition;
    }
    public function tables() : array
    {
        $tables = Collection::from($this
            ->query(
                "SELECT table_name FROM information_schema.tables where table_schema = ? AND table_catalog = ?",
                [ $this->connection['opts']['schema'] ?? 'public', $this->connection['name'] ]
            ))
            ->mapKey(function ($v) {
                return strtolower($v['table_name']);
            })
            ->pluck('table_name')
            ->map(function ($v) {
                return $this->table($v)->toLowerCase();
            })
            ->toArray();

        foreach (array_keys($tables) as $k) {
            $tables[($this->connection['opts']['schema'] ?? 'public') . '.' . $k] = &$tables[$k];
        }
        return $tables;
    }
}
