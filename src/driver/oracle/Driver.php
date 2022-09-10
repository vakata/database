<?php

namespace vakata\database\driver\oracle;

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
    public function connect()
    {
        if ($this->lnk === null) {
            $this->lnk = @call_user_func(
                $this->option('persist') ? '\oci_pconnect' : '\oci_connect',
                $this->connection['user'],
                $this->connection['pass'],
                $this->connection['host'],
                $this->option('charset', 'utf8')
            );
            if ($this->lnk === false) {
                throw new DBException('Connect error');
            }
            $this->query("ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS'");
            if ($timezone = $this->option('timezone')) {
                $this->query("ALTER session SET time_zone = '".addslashes($timezone)."'");
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
            \oci_close($this->lnk);
        }
    }
    public function raw(string $sql)
    {
        $this->connect();
        $log = $this->option('log_file');
        if ($log) {
            $tm = microtime(true);
        }
        $res = \oci_execute(\oci_parse($this->lnk, $sql));
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
        $binder = '?';
        if (strpos($sql, $binder) !== false) {
            $tmp = explode($binder, $sql);
            $sql = '';
            foreach ($tmp as $i => $v) {
                $sql .= $v;
                if (isset($tmp[($i + 1)])) {
                    $sql .= ':f'.$i;
                }
            }
        }
        $temp = \oci_parse($this->lnk, $sql);
        if (!$temp) {
            $err = \oci_error();
            if (!is_array($err)) {
                $err = [];
            }
            throw new DBException('Could not prepare : '.implode(', ', $err).' <'.$sql.'>');
        }
        return new Statement($temp, $this, $sql);
    }

    public function begin() : bool
    {
         return $this->transaction = true;
    }
    public function commit() : bool
    {
        $this->connect();
        if (!$this->transaction) {
            return false;
        }
        if (!\oci_commit($this->lnk)) {
            return false;
        }
        $this->transaction = false;
        return true;
    }
    public function rollback() : bool
    {
        $this->connect();
        if (!$this->transaction) {
            return false;
        }
        if (!\oci_rollback($this->lnk)) {
            return false;
        }
        $this->transaction = false;
        return true;
    }

    public function isTransaction()
    {
        return $this->transaction;
    }

    public function lob()
    {
        return \oci_new_descriptor($this->lnk, \OCI_D_LOB);
    }
}
