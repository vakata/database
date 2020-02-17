<?php

namespace vakata\database\driver\pdo;

use \vakata\database\DBException;
use \vakata\database\DriverInterface;
use \vakata\database\DriverAbstract;
use \vakata\database\StatementInterface;
use \vakata\database\schema\Table;
use \vakata\database\schema\TableRelation;

class Driver extends DriverAbstract implements DriverInterface
{
    use \vakata\database\driver\mysql\Schema,
        \vakata\database\driver\oracle\Schema,
        \vakata\database\driver\postgre\Schema
    {
        \vakata\database\driver\mysql\Schema::table as mtable;
        \vakata\database\driver\mysql\Schema::tables as mtables;
        \vakata\database\driver\oracle\Schema::table as otable;
        \vakata\database\driver\oracle\Schema::tables as otables;
        \vakata\database\driver\postgre\Schema::table as ptable;
        \vakata\database\driver\postgre\Schema::tables as ptables;
    }

    protected $lnk = null;
    protected $drv = null;

    public function __construct(array $connection)
    {
        $temp = explode('://', $connection['orig'], 2)[1];
        $temp = array_pad(explode('?', $temp, 2), 2, '');
        $connection = [];
        $connection['opts'] = [];
        parse_str($temp[1], $connection['opts']);
        $temp = $temp[0];
        if (strpos($temp, '@') !== false) {
            $temp = array_pad(explode('@', $temp, 2), 2, '');
            list($connection['user'], $connection['pass']) = array_pad(explode(':', $temp[0], 2), 2, '');
            $temp = $temp[1];
        }
        $connection['dsn'] = $temp;
        $connection['name'] = '';
        $this->drv = explode(':', $temp)[0];
        $this->connection = $connection;
    }
    public function __destruct()
    {
        $this->disconnect();
    }
    public function connect()
    {
        if ($this->lnk === null) {
            try {
                $this->lnk = new \PDO(
                    $this->connection['dsn'],
                    isset($this->connection['user']) ? $this->connection['user'] : '',
                    isset($this->connection['pass']) ? $this->connection['pass'] : '',
                    isset($this->connection['opts']) ? $this->connection['opts'] : []
                );
            } catch (\PDOException $e) {
                throw new DBException('Connect error: ' . $e->getMessage());
            }
        }
    }
    public function test() : bool
    {
        if ($this->lnk) {
            return true;
        }
        try {
            $this->connect();
            return true;
        } catch (\Exception $e) {
            $this->lnk = null;
            return false;
        }
    }
    public function disconnect()
    {
        $this->lnk = null;
    }
    public function prepare(string $sql) : StatementInterface
    {
        $this->connect();
        try {
            return new Statement($this->lnk->prepare($sql), $this->lnk);
        } catch (\PDOException $e) {
            throw new DBException($e->getMessage());
        }
    }

    public function begin() : bool
    {
        $this->connect();
        return $this->lnk->beginTransaction();
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
        switch ($this->drv) {
            case 'mysql':
                return $this->mtable($table, $detectRelations);
            case 'oci':
                return $this->otable($table, $detectRelations);
            case 'pgsql':
                return $this->ptable($table, $detectRelations);
            default:
                return parent::table($table, $detectRelations);
        }
    }
    public function tables() : array
    {
        switch ($this->drv) {
            case 'mysql':
                return $this->mtables();
            case 'oci':
                return $this->otables();
            case 'pgsql':
                return $this->ptables();
            default:
                return parent::tables();
        }
    }
}
