<?php

namespace vakata\database\driver\db2;

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
        $this->connection = $connection;
    }
    public function __destruct()
    {
        $this->disconnect();
    }
    public function connect(): void
    {
        $this->lnk = call_user_func(
            $this->option('persist') ? '\db2_pconnect' : '\db2_connect',
                implode(";", array_filter([
                    'HOSTNAME='.$this->connection['host'],
                    'UID='.$this->connection['user'],
                    ($this->connection['pass'] ? 'PWD='.$this->connection['pass'] : null),
                    ($this->connection['port'] ? 'PORT='.$this->connection['port'] : null),
                    'DATABASE='.$this->connection['name'],
            ])),
            null,
            null
        );
        if ($this->lnk === false) {
            throw new DBException('Connect error: '.\db2_conn_errormsg());
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
            \db2_close($this->lnk);
        }
    }
    public function raw(string $sql): mixed
    {
        $this->connect();
        $this->softDetect($sql);
        return \db2_exec($this->lnk, $sql);
    }
    public function prepare(string $sql, ?string $name = null, ?array $map = null): StatementInterface
    {
        $this->connect();
        $this->softDetect($sql);
        $statement = \db2_prepare($this->lnk, $sql);
        if ($statement === false) {
            throw new DBException('Prepare error');
        }
        return new Statement(
            $statement,
            $this->lnk,
            $map
        );
    }

    public function begin() : bool
    {
        $this->connect();
        if ($this->softTransaction === 1) {
            $this->softTransaction = 0;
        }
        if ($this->transaction) {
            return false;
        }
        \db2_set_option($this->lnk, ['autocommit' => \DB2_AUTOCOMMIT_OFF ], 1);
        $this->transaction = true;
        return true;
    }
    public function commit() : bool
    {
        $this->connect();
        if ($this->softTransaction) {
            $this->softTransaction = 0;
        }
        if (!$this->transaction) {
            return false;
        }
        if (!\db2_commit($this->lnk)) {
            return false;
        }
        \db2_set_option($this->lnk, ['autocommit' => \DB2_AUTOCOMMIT_ON ], 1);
        $this->transaction = false;

        return true;
    }
    public function rollback() : bool
    {
        $this->connect();
        if ($this->softTransaction) {
            $this->softTransaction = 0;
        }
        if (!$this->transaction) {
            return false;
        }
        if (!\db2_rollback($this->transaction)) {
            return false;
        }
        \db2_set_option($this->lnk, ['autocommit' => \DB2_AUTOCOMMIT_ON ], 1);
        $this->transaction = false;

        return true;
    }

    public function isTransaction(): bool
    {
        return $this->transaction;
    }
}
