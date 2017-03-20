<?php

namespace vakata\database\mysql;

use \vakata\database\DBException;
use \vakata\database\DriverInterface;
use \vakata\database\StatementInterface;

class Driver implements DriverInterface
{
    protected $connection;
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
        return $this->lnk->begin_transaction();
    }
    public function commit() : bool
    {
        return $this->lnk->commit();
    }
    public function rollback() : bool
    {
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
}