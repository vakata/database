<?php

namespace vakata\database\driver\odbc;

use \vakata\database\DBException;
use \vakata\database\DriverInterface;
use \vakata\database\DriverAbstract;
use \vakata\database\StatementInterface;
use \vakata\database\schema\Table;
use \vakata\database\schema\TableRelation;

class Driver extends DriverAbstract implements DriverInterface
{
    protected mixed $lnk = null;
    protected bool $transaction = false;

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
                $this->option('persist') ? '\odbc_pconnect' : '\odbc_connect',
                $this->connection['dsn'],
                isset($this->connection['user']) ? $this->connection['user'] : '',
                isset($this->connection['pass']) ? $this->connection['pass'] : ''
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
    public function disconnect(): void
    {
        if (is_resource($this->lnk)) {
            \odbc_close($this->lnk);
        }
    }
    public function prepare(string $sql, ?string $name = null, ?array $map = null) : StatementInterface
    {
        $this->connect();
        $this->softDetect($sql);
        return new Statement(
            $sql,
            $this->lnk,
            $this->connection['opts']['charset_in'] ?? null,
            $this->connection['opts']['charset_out'] ?? null,
            $map
        );
    }
    public function raw(string $sql): mixed
    {
        $this->connect();
        $this->softDetect($sql);
        return \odbc_exec($this->lnk, $sql);
    }
    public function begin() : bool
    {
        $this->connect();
        if ($this->softTransaction === 1) {
            $this->softTransaction = 0;
        }
        $this->transaction = true;
        \odbc_autocommit($this->lnk, false);
        return true;
    }
    public function commit() : bool
    {
        $this->connect();
        if ($this->softTransaction) {
            $this->softTransaction = 0;
        }
        $this->transaction = false;
        $res = \odbc_commit($this->lnk);
        \odbc_autocommit($this->lnk, false);
        return $res;
    }
    public function rollback() : bool
    {
        $this->connect();
        if ($this->softTransaction) {
            $this->softTransaction = 0;
        }
        $this->transaction = false;
        $res = \odbc_rollback($this->lnk);
        \odbc_autocommit($this->lnk, false);
        return $res;
    }
    public function isTransaction(): bool
    {
        return $this->transaction;
    }
}
