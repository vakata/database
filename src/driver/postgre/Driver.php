<?php

namespace vakata\database\driver\postgre;

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
    protected $transaction = false;

    public function __construct(array $connection)
    {
        $this->connection = $connection;
    }
    public function __destruct()
    {
        $this->disconnect();
    }
    protected function connect()
    {
        if ($this->lnk === null) {
            $this->lnk = call_user_func(
                $this->option('persist') ? '\pg_pconnect' : '\pg_connect',
                implode(" ", [
                    'host='.$this->connection['host'],
                    'user='.$this->connection['user'],
                    'password='.$this->connection['pass'],
                    'dbname='.$this->connection['name'],
                    "options='--client_encoding=".$this->option('charset', 'utf8')."'"
                ])
            );
            if ($this->lnk === false) {
                throw new DBException('Connect error');
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
        if (is_resource($this->lnk)) {
            \pg_close($this->lnk);
        }
    }
    public function prepare(string $sql) : StatementInterface
    {
        $this->connect();
        $binder = '?';
        if (strpos($sql, $binder) !== false) {
            $tmp = explode($binder, $sql);
            $sql = '';
            foreach ($tmp as $i => $v) {
                $sql .= $v;
                if (isset($tmp[($i + 1)])) {
                    $sql .= '$'.($i + 1);
                }
            }
        }
        return new Statement($sql, $this->lnk);
    }

    public function begin() : bool
    {
        $this->connect();
        try {
            $this->transaction = true;
            $this->query('BEGIN');
        } catch (DBException $e) {
            $this->transaction = false;

            return false;
        }

        return true;
    }
    public function commit() : bool
    {
        $this->connect();
        $this->transaction = false;
        try {
            $this->query('COMMIT');
        } catch (DBException $e) {
            return false;
        }

        return true;
    }
    public function rollback() : bool
    {
        $this->connect();
        $this->transaction = false;
        try {
            $this->query('ROLLBACK');
        } catch (DBException $e) {
            return false;
        }

        return true;
    }
    public function isTransaction()
    {
        return $this->transaction;
    }
}
