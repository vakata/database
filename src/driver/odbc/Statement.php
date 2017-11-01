<?php

namespace vakata\database\driver\odbc;

use \vakata\database\DBException;
use \vakata\database\DriverInterface;
use \vakata\database\StatementInterface;
use \vakata\database\ResultInterface;

class Statement implements StatementInterface
{
    protected $statement;
    protected $sql;
    protected $driver;

    public function __construct(string $statement, $driver)
    {
        $this->sql = $statement;
        $this->driver = $driver;
        $this->statement = \odbc_prepare($this->driver, $statement);
    }
    public function execute(array $data = []) : ResultInterface
    {
        if (!is_array($data)) {
            $data = array();
        }
        $temp = \odbc_execute($this->statement, $data);
        if (!$temp) {
            throw new DBException('Could not execute query : '.\odbc_errormsg($this->driver));
        }
        $iid = null;
        if (preg_match('@^\s*(INSERT|REPLACE)\s+INTO@i', $this->sql)) {
            $iid = \odbc_exec($this->driver, 'SELECT @@IDENTITY');
            if ($iid && \odbc_fetch_row($iid)) {
                $iid = odbc_result($result, 1);
            } else {
                $iid = null;
            }
        }
        return new Result($this->statement, $data, $iid);
    }
}