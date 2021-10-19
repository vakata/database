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
    use Schema;
    
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
            $this->connection['opts']['charset'] = 'utf8mb4';
        }
    }
    public function __destruct()
    {
        $this->disconnect();
    }
    public function connect()
    {
        if ($this->lnk === null) {
            $this->lnk = new \mysqli(
                (isset($this->connection['opts']['persist']) && $this->connection['opts']['persist'] ? 'p:' : '') .
                    $this->connection['host'],
                $this->connection['user'],
                $this->connection['pass'],
                $this->connection['name'],
                isset($this->connection['opts']['socket']) ? null : $this->connection['port'],
                $this->connection['opts']['socket'] ?? null
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
            if (isset($this->connection['opts']['sql_mode'])) {
                $this->lnk->query("SET sql_mode = '".addslashes($this->connection['opts']['sql_mode'])."'");
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
    public function disconnect()
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
    public function raw(string $sql)
    {
        $this->connect();
        return $this->lnk->query($sql);
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
}
