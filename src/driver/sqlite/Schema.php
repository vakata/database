<?php

namespace vakata\database\driver\sqlite;

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

    public function table(string $table, bool $detectRelations = true, array $tbls = []) : Table
    {
        static $tables = [];

        $catalog = $this->connection['name'];
        $main = $this->connection['opts']['schema'] ?? 'main';
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
            foreach ($tbls as $tn) {
                foreach ($this->query("PRAGMA foreign_key_list(".$tn.")")->toArray() as $row) {
                    $row['referenced_table'] = $row['table'];
                    $row['table'] = $tn;
                    $row['constraint_name'] = md5(
                        implode(
                            '_',
                            [
                                $row['table'],
                                $row['referenced_table'],
                                $row['from'],
                                $row['to']
                            ]
                        )
                    );
                    $relationsT['main.' . $row['table']][] = $row;
                    $relationsR['main.' . $row['referenced_table']][] = $row;
                }
            }
        }

        $columns = Collection::from($this
            ->query("PRAGMA table_info(".$table.")"))
            ->mapKey(function ($v) {
                return $v['name'];
            })
            ->map(function ($v) {
                $v['length'] = null;
                if (!isset($v['type'])) {
                    return $v;
                }
                $type = strtolower($v['type']);
                switch ($type) {
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
            ->toArray();
        if (!count($columns)) {
            throw new DBException('Table not found by name: ' . implode('.', [$schema,$table]));
        }
        $primary = [];
        foreach ($columns as $column) {
            if ((int)$column['pk']) {
                $primary[] = $column['name'];
            }
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
            foreach ($relationsR[$schema . '.' . $table] ?? [] as $k => $relation) {
                $relations[$relation['constraint_name']]['table'] = 'main.' . $relation['table'];
                $relations[$relation['constraint_name']]['keymap'][$relation['to']] = $relation['from'];
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
                            return in_array($v['from'], $columns);
                        }) as $relation
                    ) {
                        $foreign[$relation['constraint_name']]['table'] = 'main.' .
                            $relation['referenced_table'];
                        $foreign[$relation['constraint_name']]['keymap'][$relation['from']] = $relation['to'];
                        $usedcol[] = $relation['from'];
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
                    while ($definition->hasRelation($relname) ||
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
                    while ($definition->hasRelation($relname) ||
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
                $relations[$relation['constraint_name']]['table'] = 'main.' . $relation['referenced_table'];
                $relations[$relation['constraint_name']]['keymap'][$relation['from']] = $relation['to'];
            }
            foreach ($relations as $name => $data) {
                $relname = $data['table'];
                $temp = explode('.', $relname, 2);
                if ($temp[0] == $main) {
                    $relname = $temp[1];
                }
                $orig = $relname;
                $cntr = 1;
                while ($definition->hasRelation($relname) ||
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
                "SELECT tbl_name
                 FROM sqlite_schema
                 WHERE (type = ? OR type = ?) AND tbl_name NOT LIKE 'sqlite_%';",
                [ 'table', 'view' ]
            ))
            ->mapKey(function ($v) {
                return strtolower($v['tbl_name']);
            })
            ->pluck('tbl_name')
            ->toArray();
        foreach ($tables as $k => $v) {
            $tables[$k] = $this->table($v, true, array_keys($tables))->toLowerCase();
        }
        foreach (array_keys($tables) as $k) {
            $tables[($this->connection['opts']['schema'] ?? 'main') . '.' . $k] = &$tables[$k];
        }
        return $tables;
    }
}
