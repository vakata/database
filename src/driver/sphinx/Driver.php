<?php

namespace vakata\database\driver\sphinx;

use \vakata\database\DBException;
use \vakata\database\DriverInterface;
use \vakata\database\StatementInterface;
use vakata\database\driver\mysql\Driver as MysqlDriver;

class Driver extends MysqlDriver implements DriverInterface
{
    public function __construct(array $connection)
    {
        $this->connection = $connection;
        if (!isset($this->connection['port'])) {
            $this->connection['port'] = 9306;
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
    public function connect(): void
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
    public function disconnect(): void
    {
        if ($this->lnk !== null && $this->lnk !== false) {
            $this->lnk->close();
        }
    }
    public function prepare(string $sql, ?string $name = null, ?array $map = null) : StatementInterface
    {
        $this->connect();
        $this->softDetect($sql);
        return new Statement($this->lnk, $sql, $map);
    }
    public function raw(string $sql): mixed
    {
        $this->connect();
        $this->softDetect($sql);
        $log = $this->option('log_file');
        if ($log) {
            $tm = microtime(true);
        }
        $res = $this->lnk->query($sql);
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
    public function begin() : bool
    {
        $this->connect();
        if ($this->softTransaction === 1) {
            $this->softTransaction = 0;
        }
        return $this->lnk->begin_transaction();
    }
    public function commit() : bool
    {
        $this->connect();
        if ($this->softTransaction) {
            $this->softTransaction = 0;
        }
        return $this->lnk->commit();
    }
    public function rollback() : bool
    {
        $this->connect();
        if ($this->softTransaction) {
            $this->softTransaction = 0;
        }
        return $this->lnk->rollback();
    }
}
