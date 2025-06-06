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

        /**
         * @var array<string,array<string,mixed>>
         */
        $columns = Collection::from($this
            ->query(
                "SELECT * FROM information_schema.columns
                 WHERE table_name = ? AND table_schema = ? AND table_catalog = ?",
                [ $table, $schema, $catalog ]
            ))
            ->mapKey(function ($v): string {
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
                $v['hidden'] = $v['is_identity'] !== 'YES' && $v['is_generated'] === 'ALWAYS';
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
            $duplicated = [];
            foreach ($relationsT[$schema . '.' . $table] ?? [] as $relation) {
                $t = $relation['referenced_table_schema'] . '.' . $relation['referenced_table_name'];
                $duplicated[$t] = isset($duplicated[$t]);
            }
            foreach ($relationsR[$schema . '.' . $table] ?? [] as $relation) {
                $t = $relation['table_schema'] . '.' . $relation['table_name'];
                $duplicated[$t] = isset($duplicated[$t]);
            }
            // pivot relations where the current table is referenced
            // assuming current table is on the "one" end having "many" records in the referencing table
            // resulting in a "hasMany" or "manyToMany" relationship (if a pivot table is detected)
            $relations = [];
            foreach ($relationsR[$schema . '.' . $table] ?? [] as $relation) {
                $relations[$relation['constraint_name']]['table'] = $relation['table_schema'] . '.' .
                    $relation['table_name'];
                $relations[$relation['constraint_name']]['keymap'][$relation['referenced_column_name']] =
                    $relation['column_name'];
            }
            ksort($relations);
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
                        $foreign[$relation['constraint_name']]['table'] = $relation['referenced_table_schema'] . '.' .
                            $relation['referenced_table_name'];
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
                    while ($definition->hasRelation($relname) ||
                        $definition->getName() == $relname ||
                        $definition->getColumn($relname)
                    ) {
                        $relname = $orig . '_' . (++ $cntr);
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
            foreach ($relationsT[$schema . '.' . $table] ?? [] as $relation) {
                $relations[$relation['constraint_name']]['table'] = $relation['referenced_table_schema'] . '.' .
                    $relation['referenced_table_name'];
                $relations[$relation['constraint_name']]['keymap'][$relation['column_name']] =
                    $relation['referenced_column_name'];
            }
            ksort($relations);
            foreach ($relations as $name => $data) {
                $relname = $data['table'];
                $temp = explode('.', $relname, 2);
                if ($temp[0] == $main) {
                    $relname = $temp[1];
                }
                if ($duplicated[$data['table']] ||
                    $definition->hasRelation($relname) ||
                    $definition->getName() == $relname ||
                    $definition->getColumn($relname)
                ) {
                    $relname = array_keys($data['keymap'])[0] . '_' . $relname;
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
            foreach ($relationsR[$schema . '.' . $table] ?? [] as $relation) {
                $relations[$relation['constraint_name']]['table'] = $relation['table_schema'] . '.' .
                    $relation['table_name'];
                $relations[$relation['constraint_name']]['keymap'][$relation['referenced_column_name']] =
                    $relation['column_name'];
            }
            ksort($relations);
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
                        $foreign[$relation['constraint_name']]['table'] = $relation['referenced_table_schema'] . '.' .
                            $relation['referenced_table_name'];
                        $foreign[$relation['constraint_name']]['keymap'][$relation['column_name']] =
                            $relation['referenced_column_name'];
                        $usedcol[] = $relation['column_name'];
                    }
                }
                if (count($foreign) !== 1 || count(array_diff($columns, $usedcol))) {
                    $relname = $data['table'];
                    $temp = explode('.', $relname, 2);
                    if ($temp[0] == $main) {
                        $relname = $temp[1];
                    }
                    if ($duplicated[$data['table']] ||
                        $definition->hasRelation($relname) ||
                        $definition->getName() == $relname ||
                        $definition->getColumn($relname)
                    ) {
                        $relname .= '_' . array_values($data['keymap'])[0];
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
    public function view(string $table) : Table
    {
        $schema = $this->connection['opts']['schema'] ?? 'public';
        if (strpos($table, '.')) {
            $temp = explode('.', $table, 2);
            $schema = $temp[0];
            $table = $temp[1];
        }

        /**
         * @var array<string,array<string,mixed>>
         */
        $columns = Collection::from($this
            ->query(
                "SELECT
                    attr.attname as column_name,
                    format_type(attr.atttypid, attr.atttypmod) as data_type
                 from pg_catalog.pg_attribute as attr
                 join pg_catalog.pg_class as cls on cls.oid = attr.attrelid
                 join pg_catalog.pg_namespace as ns on ns.oid = cls.relnamespace
                 join pg_catalog.pg_type as tp on tp.typelem = attr.atttypid
                 where
                    cls.relname = ? and
                    ns.nspname = ? and
                    not attr.attisdropped and
                    cast(tp.typanalyze as text) = 'array_typanalyze' and
                    attr.attnum > 0
                 order by
                    attr.attnum",
                [ $table, $schema ]
            ))
            ->mapKey(function ($v): string {
                return $v['column_name'];
            })
            ->map(function ($v) {
                $v['length'] = null;
                return $v;
            })
            ->toArray();
        if (!count($columns)) {
            throw new DBException('View not found by name: ' . implode('.', [$schema,$table]));
        }
        $tables[$schema . '.' .$table] = $definition = (new Table($table, $schema))
            ->addColumns($columns)
            ->setComment('');
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
        // materialized views
        $views = Collection::from($this
            ->query(
                "SELECT cls.oid::regclass::text as table_name
                 FROM pg_catalog.pg_class cls
                 join pg_catalog.pg_namespace as ns on ns.oid = cls.relnamespace
                 WHERE cls.relkind = 'm' and ns.nspname = ?",
                [ $this->connection['opts']['schema'] ?? 'public' ]
            ))
            ->mapKey(function ($v) {
                return strtolower($v['table_name']);
            })
            ->pluck('table_name')
            ->map(function ($v) {
                return $this->view($v)->toLowerCase();
            })
            ->toArray();
        $tables = array_merge($views, $tables);
        foreach (array_keys($tables) as $k) {
            $tables[($this->connection['opts']['schema'] ?? 'public') . '.' . $k] = &$tables[$k];
        }
        return $tables;
    }
}
