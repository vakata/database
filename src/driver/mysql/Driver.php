<?php

namespace vakata\database\driver\mysql;

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

    public function __construct(array $connection)
    {
        $this->connection = $connection;
        if (!isset($this->connection['port'])) {
            $this->connection['port'] = 3306;
        }
        if (!isset($this->connection['opts'])) {
            $this->connection['opts'] = [];
        }
        if (!isset($this->connection['opts']['charset'])) {
            $this->connection['opts']['charset'] = 'UTF8';
        }
    }
    public function __destruct()
    {
        $this->disconnect();
    }
    protected function connect()
    {
        if ($this->lnk === null) {
            $this->lnk = new \mysqli(
                (isset($this->connection['opts']['persist']) && $this->connection['opts']['persist'] ? 'p:' : '') .
                    $this->connection['host'],
                $this->connection['user'],
                $this->connection['pass'],
                $this->connection['name'],
                $this->connection['port']
            );
            if ($this->lnk->connect_errno) {
                throw new DBException('Connect error: '.$this->lnk->connect_errno);
            }
            if (!$this->lnk->set_charset($this->connection['opts']['charset'])) {
                throw new DBException('Charset error: '.$this->lnk->connect_errno);
            }
            if (isset($this->connection['opts']['timezone'])) {
                $this->lnk->query("SET time_zone = '".addslashes($this->connection['opts']['timezone'])."'");
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
            $this->lnk->close();
        }
    }
    public function prepare(string $sql) : StatementInterface
    {
        $this->connect();
        $temp = $this->lnk->prepare($sql);
        if (!$temp) {
            throw new DBException('Could not prepare : '.$this->lnk->error.' <'.$sql.'>');
        }
        return new Statement($temp);
    }

    public function begin() : bool
    {
        $this->connect();
        return $this->lnk->begin_transaction();
    }
    public function commit() : bool
    {
        $this->connect();
        return $this->lnk->commit();
    }
    public function rollback() : bool
    {
        $this->connect();
        return $this->lnk->rollback();
    }

    public function table(string $table, bool $detectRelations = true) : Table
    {
        static $tables = [];
        if (isset($tables[$table])) {
            return $tables[$table];
        }
        
        $columns = Collection::from($this->query("SHOW FULL COLUMNS FROM {$table}"));
        if (!count($columns)) {
            throw new DBException('Table not found by name');
        }
        $tables[$table] = $definition = (new Table($table))
            ->addColumns(
                $columns
                    ->clone()
                    ->mapKey(function ($v) { return $v['Field']; })
                    ->toArray()
            )
            ->setPrimaryKey(
                $columns
                    ->clone()
                    ->filter(function ($v) { return $v['Key'] === 'PRI'; })
                    ->pluck('Field')
                    ->toArray()
            )
            ->setComment(
                (string)Collection::from($this
                    ->query(
                        "SELECT table_comment FROM information_schema.tables WHERE table_schema = ? AND table_name = ?",
                        [ $this->connection['name'], $table ]
                    ))
                    ->pluck('table_comment')
                    ->value()
            );

        if ($detectRelations) {
            // relations where the current table is referenced
            // assuming current table is on the "one" end having "many" records in the referencing table
            // resulting in a "hasMany" or "manyToMany" relationship (if a pivot table is detected)
            $relations = [];
            foreach (
                $this
                    ->query(
                        "SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_COLUMN_NAME
                         FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                         WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME = ?",
                        [ $this->connection['name'], $this->connection['name'], $table ]
                    ) as $relation
            ) {
                $relations[$relation['CONSTRAINT_NAME']]['table'] = $relation['TABLE_NAME'];
                $relations[$relation['CONSTRAINT_NAME']]['keymap'][$relation['REFERENCED_COLUMN_NAME']] = $relation['COLUMN_NAME'];
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
                                 TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME,
                                 REFERENCED_COLUMN_NAME, REFERENCED_TABLE_NAME
                             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                             WHERE
                                 TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME IN (??) AND
                                 REFERENCED_TABLE_NAME IS NOT NULL",
                            [ $this->connection['name'], $data['table'], $columns ]
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
                    "SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                     FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                     WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL",
                    [ $this->connection['name'], $table ]
                ))
                ->map(function ($v) {
                    $new = [];
                    foreach ($v as $kk => $vv) {
                        $new[strtoupper($kk)] = $vv;
                    }
                    return $new;
                }) as $relation
            ) {
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