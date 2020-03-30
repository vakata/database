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
    protected $connection;
    abstract public function query(string $sql, $par = null, bool $buff = true) : ResultInterface;

    public function table(string $table, bool $detectRelations = true) : Table
    {
        static $tables = [];
        if (isset($tables[$table])) {
            return $tables[$table];
        }

        static $relationsT = null;
        static $relationsR = null;
        if (!isset($relationsT) || !isset($relationsR)) {
            $relationsT = [];
            $relationsR = [];
            $col = Collection::from(
                $this->query(
                    "SELECT
                        kc.table_name,
                        kc.column_name,
                        kc.constraint_name,
                        ct.table_name AS referenced_table_name,
                        (SELECT column_name
                         FROM information_schema.constraint_column_usage
                         WHERE constraint_name = kc.constraint_name AND table_name = ct.table_name
                         LIMIT 1 OFFSET kc.position_in_unique_constraint - 1
                        ) AS referenced_column_name
                     FROM information_schema.key_column_usage kc
                     JOIN information_schema.constraint_table_usage ct ON
                        kc.constraint_name = ct.constraint_name AND ct.table_schema = kc.table_schema
                     WHERE
                        kc.table_schema = ? AND
                        kc.table_name IS NOT NULL AND
                        kc.position_in_unique_constraint IS NOT NULL",
                    [ $this->connection['opts']['schema'] ?? $this->connection['name'] ]
                )
            )->toArray();
            foreach ($col as $row) {
                $relationsT[$row['table_name']][] = $row;
                $relationsR[$row['referenced_table_name']][] = $row;
            }
        }

        $columns = Collection::from($this
            ->query(
                "SELECT * FROM information_schema.columns WHERE table_name = ? AND table_schema = ?",
                [ $table, $this->connection['opts']['schema'] ?? $this->connection['name'] ]
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
            throw new DBException('Table not found by name');
        }
        $pkname = Collection::from($this
            ->query(
                "SELECT constraint_name FROM information_schema.table_constraints
                WHERE table_name = ? AND constraint_type = ? AND table_schema = ?",
                [ $table, 'PRIMARY KEY', $this->connection['opts']['schema'] ?? $this->connection['name'] ]
            ))
            ->pluck('constraint_name')
            ->value();
        $primary = [];
        if ($pkname) {
            $primary = Collection::from($this
                ->query(
                    "SELECT column_name FROM information_schema.constraint_column_usage
                     WHERE table_name = ? AND constraint_name = ? AND table_schema = ?",
                    [ $table, $pkname, $this->connection['opts']['schema'] ?? $this->connection['name'] ]
                ))
                ->pluck('column_name')
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
            foreach ($relationsR[$table] ?? [] as $relation) {
                $relations[$relation['constraint_name']]['table'] = $relation['table_name'];
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
                        $foreign[$relation['constraint_name']]['table'] = $relation['referenced_table_name'];
                        $foreign[$relation['constraint_name']]['keymap'][$relation['column_name']] =
                            $relation['referenced_column_name'];
                        $usedcol[] = $relation['column_name'];
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
            foreach ($relationsT[$table] ?? [] as $relation) {
                $relations[$relation['constraint_name']]['table'] = $relation['referenced_table_name'];
                $relations[$relation['constraint_name']]['keymap'][$relation['column_name']] =
                    $relation['referenced_column_name'];
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
                [ $this->connection['opts']['schema'] ?? $this->connection['name'] ]
            ))
            ->pluck('table_name')
            ->map(function ($v) {
                return $this->table($v);
            })
            ->toArray();
    }
}
