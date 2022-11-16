<?php

namespace vakata\database\driver\sqlite;

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
        $connection['name'] = $temp[0];
        if (!is_file($connection['name']) && is_file('/'.$connection['name'])) {
            $connection['name'] = '/'.$connection['name'];
        }
        $this->connection = $connection;
    }
    public function __destruct()
    {
        $this->disconnect();
    }
    public function connect(): void
    {
        if ($this->lnk === null) {
            try {
                $this->lnk = new \SQLite3($this->connection['name']);
                $this->lnk->exec('PRAGMA encoding = "'.$this->option('charset', 'utf-8'));
            } catch (\Exception $e) {
                if ($this->lnk !== null) {
                    throw new DBException('Connect error: '.$this->lnk->lastErrorMsg());
                } else {
                    throw new DBException('Connect error');
                }
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
    public function raw(string $sql): mixed
    {
        $this->connect();
        return $this->lnk->query($sql);
    }
    public function prepare(string $sql, ?string $name = null) : StatementInterface
    {
        $this->connect();
        $binder = '?';
        if (strpos($sql, $binder) !== false) {
            $tmp = explode($binder, $sql);
            $sql = '';
            foreach ($tmp as $i => $v) {
                $sql .= $v;
                if (isset($tmp[($i + 1)])) {
                    $sql .= ':i'.$i;
                }
            }
        }
        $temp = $this->lnk->prepare($sql);
        if (!$temp) {
            throw new DBException('Could not prepare : '.$this->lnk->lastErrorMsg().' <'.$sql.'>');
        }
        return new Statement($temp, $this->lnk);
    }

    public function begin() : bool
    {
        $this->connect();
        try {
            $this->transaction = true;
            $this->query('BEGIN TRANSACTION');
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
    public function isTransaction(): bool
    {
        return $this->transaction;
    }
}
