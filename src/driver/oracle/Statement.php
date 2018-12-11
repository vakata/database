<?php

namespace vakata\database\driver\oracle;

use \vakata\database\DBException;
use \vakata\database\DriverInterface;
use \vakata\database\StatementInterface;
use \vakata\database\ResultInterface;

class Statement implements StatementInterface
{
    protected $statement;
    protected $driver;

    public function __construct($statement, Driver $driver)
    {
        $this->statement = $statement;
        $this->driver = $driver;
    }
    public function execute(array $data = []) : ResultInterface
    {
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
        $temp = \oci_execute($this->statement, $this->driver->isTransaction() ? \OCI_NO_AUTO_COMMIT : \OCI_COMMIT_ON_SUCCESS);
        if (!$temp) {
            $err = \oci_error($this->statement);
            if (!is_array($err)) {
                $err = [];
            }
            throw new DBException('Could not execute query : '.implode(',', $err));
        }
        if ($lob) {
            while (!feof($ldt) && ($ltmp = fread($ldt, 8192)) !== false) {
                $lob->write($ltmp);
                $lob->flush();
            }
            $lob->free();
        }
        return new Result($this->statement);
    }
}