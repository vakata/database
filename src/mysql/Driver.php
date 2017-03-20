<?php

namespace vakata\database\mysql;

use \vakata\database\DBException;
use \vakata\database\DriverInterface;
use \vakata\database\StatementInterface;
use \vakata\database\Table;

class Driver implements DriverInterface
{
    protected $connection;
    protected $lnk = null;
    protected $tables = [];

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
                @$this->lnk->query("SET time_zone = '".addslashes($this->connection['opts']['timezone'])."'");
            }
        }
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

    public function name() : string
    {
        return $this->connection['name'];
    }
    public function option($key, $default = null)
    {
        return isset($this->connection['opts'][$key]) ? $this->connection['opts'][$key] : $default;
    }

    public function table(string $table, bool $detectRelations = true, bool $lowerCase = true) : Table
    {
        if (isset($this->tables[$table])) {
            return $this->tables[$table];
        }
        $definition = new Table($table);
        $columns = [];
        $primary = [];
        $comment = '';
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
        if (!count($columns)) {
            throw new DatabaseException('Table not found by name');
        }
        $definition
            ->addColumns($columns)
            ->setPrimaryKey($primary)
            ->setComment($comment);
        $this->tables[$table] = $definition;

        if ($detectRelations) {
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
                $rtable = $this->table($data['table'], true, $lowerCase); // ?? $this->addTableByName($data['table'], false);
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
                            $this->table($foreign['table'], true, $lowerCase),
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
                            $this->table($data['table'], true, $lowerCase),
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
                        $this->table($data['table'], true, $lowerCase),
                        $data['keymap'],
                        false
                    )
                );
            }
        }
        if ($lowerCase) {
            $definition->toLowerCase();
        }
        return $definition;
    }
}