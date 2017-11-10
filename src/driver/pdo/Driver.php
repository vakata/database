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
    protected $lnk = null;

    public function __construct(array $connection)
    {
        $temp = explode('://', $connection['orig'], 2)[1];
        $temp = array_pad(explode('?', $temp, 2), 2, '');
        $connection = [];
        parse_str($temp[1], $connection['opts']);
        $temp = $temp[0];
        if (strpos($temp, '@') !== false) {
            $temp = array_pad(explode('@', $temp, 2), 2, '');
            list($connection['user'], $connection['pass']) = array_pad(explode(':', $temp[0], 2), 2, '');
            $temp = $temp[1];
        }
        $connection['dsn'] = $temp;
        $this->connection = $connection;
    }
    public function __destruct()
    {
        $this->disconnect();
    }
    protected function connect()
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
            return false;
        }
    }
    protected function disconnect()
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
}