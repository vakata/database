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
    protected $lnk = null;
    protected $transaction = false;

    public function __construct(array $connection)
    {
        $temp = explode('://', $connection['orig'], 2)[1];
        $temp = array_pad(explode('?', $temp, 2), 2, '');
        $connection = [];
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
    protected function connect()
    {
        if ($this->lnk === null) {
            try {
                $this->lnk = new \SQLite3($this->connection['name']);
                $this->lnk->exec('PRAGMA encoding = "'.$this->option('charset', 'utf-8'));
            } catch (\Exception $e) {
                throw new DBException('Connect error: '.$this->lnk->lastErrorMsg());
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
        if ($this->lnk !== null) {
            $this->lnk->close();
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

    public function begin()
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
    public function commit()
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
    public function rollback()
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