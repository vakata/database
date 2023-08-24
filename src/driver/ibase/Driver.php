<?php

namespace vakata\database\driver\ibase;

use \vakata\database\DBException;
use \vakata\database\DriverInterface;
use \vakata\database\DriverAbstract;
use \vakata\database\StatementInterface;
use \vakata\database\schema\Table;
use \vakata\database\schema\TableRelation;

class Driver extends DriverAbstract implements DriverInterface
{
    protected mixed $lnk = null;
    protected mixed $transaction = null;

    public function __construct(array $connection)
    {
        $this->connection = $connection;
        if (!is_file($connection['name']) && is_file('/'.$connection['name'])) {
            $this->connection['name'] = '/'.$connection['name'];
        }
        $this->connection['host'] = ($connection['host'] === 'localhost' || $connection['host'] === '') ?
                '' : $connection['host'].':';
    }
    public function __destruct()
    {
        $this->disconnect();
    }
    public function connect(): void
    {
        $this->lnk = call_user_func(
            $this->option('persist') ? '\ibase_pconnect' : '\ibase_connect',
            $this->connection['host'].$this->connection['name'],
            $this->connection['user'],
            $this->connection['pass'],
            strtoupper($this->option('charset', 'utf8'))
        );
        if ($this->lnk === false) {
            throw new DBException('Connect error: '.\ibase_errmsg());
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
            \ibase_close($this->lnk);
        }
    }
    public function raw(string $sql): mixed
    {
        $this->connect();
        $this->softDetect($sql);
        return \ibase_query($this->lnk, $sql);
    }
    public function prepare(string $sql, ?string $name = null): StatementInterface
    {
        $this->connect();
        $this->softDetect($sql);
        $statement = \ibase_prepare($this->transaction !== null ? $this->transaction : $this->lnk, $sql);
        if ($statement === false) {
            throw new DBException('Prepare error: ' . \ibase_errmsg());
        }
        return new Statement(
            $statement,
            $this->lnk
        );
    }

    public function begin() : bool
    {
        $this->connect();
        if ($this->softTransaction === 1) {
            $this->softTransaction = 0;
        }
        $this->transaction = \ibase_trans($this->lnk);
        if ($this->transaction === false) {
            $this->transaction === null;
        }
        return ($this->transaction !== null);
    }
    public function commit() : bool
    {
        $this->connect();
        if ($this->softTransaction) {
            $this->softTransaction = 0;
        }
        if ($this->transaction === null) {
            return false;
        }
        if (!\ibase_commit($this->transaction)) {
            return false;
        }
        $this->transaction = null;

        return true;
    }
    public function rollback() : bool
    {
        $this->connect();
        if ($this->softTransaction) {
            $this->softTransaction = 0;
        }
        if ($this->transaction === null) {
            return false;
        }
        if (!\ibase_rollback($this->transaction)) {
            return false;
        }
        $this->transaction = null;

        return true;
    }

    public function isTransaction(): bool
    {
        return $this->transaction !== null;
    }
}
