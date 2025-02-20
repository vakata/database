<?php

namespace vakata\database\driver\oracle;

use \vakata\database\DBException;
use \vakata\database\DriverInterface;
use \vakata\database\StatementInterface;
use \vakata\database\ResultInterface;

class Statement implements StatementInterface
{
    protected mixed $statement;
    protected Driver $driver;
    protected string $sql;
    protected ?array $map = null;

    public function __construct(mixed $statement, Driver $driver, string $sql = '', ?array $map = null)
    {
        $this->statement = $statement;
        $this->driver = $driver;
        $this->sql = $sql;
        $this->map = $map;
    }
    public function execute(array $data = [], bool $buff = true) : ResultInterface
    {
        if (isset($this->map)) {
            $par = [];
            foreach ($this->map as $key) {
                $par[] = $data[$key] ?? throw new DBException('Missing param ' . $key);
            }
            $data = $par;
        }
        $data = array_values($data);
        $lob = null;
        $ldt = null;
        foreach ($data as $i => $v) {
            switch (gettype($v)) {
                case 'boolean':
                case 'integer':
                    $data[$i] = (int) $v;
                    \oci_bind_by_name($this->statement, 'f'.$i, $data[$i], -1, \SQLT_INT);
                    break;
                default:
                    // keep in mind oracle needs a transaction when inserting LOBs, aside from the specific syntax:
                    // INSERT INTO table (column, lobcolumn) VALUES (?, ?, EMPTY_BLOB()) RETURNING lobcolumn INTO ?
                    if (is_resource($v) && get_resource_type($v) === 'stream') {
                        $ldt = $v;
                        $lob = $this->driver->lob();
                        \oci_bind_by_name($this->statement, 'f'.$i, $lob, -1, \OCI_B_BLOB);
                        break;
                    }
                    if (!is_string($data[$i]) && !is_null($data[$i])) {
                        $data[$i] = serialize($data[$i]);
                    }
                    \oci_bind_by_name($this->statement, 'f'.$i, $data[$i]);
                    break;
            }
        }
        $log = $this->driver->option('log_file');
        if ($log) {
            $tm = microtime(true);
        }
        try {
            $temp = \oci_execute(
                $this->statement,
                $this->driver->isTransaction() ? \OCI_NO_AUTO_COMMIT : \OCI_COMMIT_ON_SUCCESS
            );
        } catch (\Exception $e) {
            $temp = false;
        }
        if (!$temp) {
            $err = \oci_error($this->statement);
            if (!is_array($err)) {
                $err = [];
            }
            if ($log && (int)$this->driver->option('log_errors', 1)) {
                @file_put_contents(
                    $log,
                    '--' . date('Y-m-d H:i:s') . ' ERROR: ' . implode(',', $err) . "\r\n" .
                    $this->sql . "\r\n" .
                    "\r\n",
                    FILE_APPEND
                );
            }
            throw new DBException('Could not execute query : '.implode(',', $err));
        }
        if ($log) {
            $tm = microtime(true) - $tm;
            if ($tm >= (float)$this->driver->option('log_slow', 0)) {
                @file_put_contents(
                    $log,
                    '--' . date('Y-m-d H:i:s') . ' ' . sprintf('%01.6f', $tm) . "s\r\n" .
                    $this->sql . "\r\n" .
                    "\r\n",
                    FILE_APPEND
                );
            }
        }
        if ($lob) {
            while ($ldt !== null && !feof($ldt) && ($ltmp = fread($ldt, 8192)) !== false) {
                $lob->write($ltmp);
                $lob->flush();
            }
            $lob->free();
        }
        return new Result($this->statement);
    }
}
