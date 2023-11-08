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
    
    protected mixed $lnk = null;
    protected bool $transaction = false;

    public function __construct(array $connection)
    {
        $this->connection = $connection;
    }
    public function __destruct()
    {
        $this->disconnect();
    }
    public function connect(): void
    {
        if ($this->lnk === null) {
            $this->lnk = call_user_func(
                $this->option('persist') ? '\pg_pconnect' : '\pg_connect',
                implode(" ", array_filter([
                    'host='.$this->connection['host'],
                    'user='.$this->connection['user'],
                    ($this->connection['pass'] ? 'password='.$this->connection['pass'] : null),
                    ($this->connection['port'] ? 'port='.$this->connection['port'] : null),
                    'dbname='.$this->connection['name'],
                    //"options='--client_encoding=".$this->option('charset', 'utf8')."'"
                ]))
            );
            if ($this->lnk === false) {
                throw new DBException('Connect error');
            }
            if (isset($this->connection['opts']['search_path'])) {
                @\pg_query(
                    $this->lnk,
                    "SET search_path TO " . pg_escape_string($this->connection['opts']['search_path'])
                );
            }
            if (!isset($this->connection['opts']['search_path']) && isset($this->connection['opts']['schema'])) {
                @\pg_query($this->lnk, "SET search_path TO " . pg_escape_string($this->connection['opts']['schema']));
            }
            if (isset($this->connection['opts']['timezone'])) {
                @\pg_query($this->lnk, "SET TIME ZONE '".pg_escape_string($this->connection['opts']['timezone'])."'");
            }
            @\pg_query($this->lnk, 'SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL REPEATABLE READ');
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
    public function disconnect(): void
    {
        // if (isset($this->lnk)) {
        //     @\pg_close($this->lnk);
        // }
    }
    public function raw(string $sql): mixed
    {
        $this->connect();
        $this->softDetect($sql);
        $log = $this->option('log_file');
        if ($log) {
            $tm = microtime(true);
        }
        $res = \pg_query($this->lnk, $sql);
        if ($log) {
            $tm = microtime(true) - $tm;
            if ($tm >= (float)$this->option('log_slow', 0)) {
                @file_put_contents(
                    $log,
                    '--' . date('Y-m-d H:i:s') . ' ' . sprintf('%01.6f', $tm) . "s\r\n" .
                    $sql . "\r\n" .
                    "\r\n",
                    FILE_APPEND
                );
            }
        }
        return $res;
    }
    public function prepare(string $sql, ?string $name = null) : StatementInterface
    {
        $this->connect();
        $this->softDetect($sql);
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
        return new Statement($sql, $this->lnk, $this, $name);
    }

    public function begin() : bool
    {
        $this->connect();
        if ($this->softTransaction === 1) {
            $this->softTransaction = 0;
        }
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
        if ($this->softTransaction) {
            $this->softTransaction = 0;
        }
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
        if ($this->softTransaction) {
            $this->softTransaction = 0;
        }
        $this->transaction = false;
        try {
            $this->query('ROLLBACK');
        } catch (DBException $e) {
            return false;
        }

        return true;
    }
    public function isTransaction(): bool
    {
        return $this->transaction;
    }
}
