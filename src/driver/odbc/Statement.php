<?php

namespace vakata\database\driver\odbc;

use \vakata\database\DBException;
use \vakata\database\DriverInterface;
use \vakata\database\StatementInterface;
use \vakata\database\ResultInterface;

class Statement implements StatementInterface
{
    protected mixed $statement;
    protected string $sql;
    protected mixed $driver;
    protected ?string $charIn;
    protected ?string $charOut;
    protected ?array $map = null;

    public function __construct(
        string $statement,
        mixed $driver,
        ?string $charIn = null,
        ?string $charOut = null,
        ?array $map = null
    )
    {
        $this->sql = $statement;
        $this->driver = $driver;
        $this->charIn = $charIn;
        $this->charOut = $charOut;
        $this->map = $map;
        $this->statement = \odbc_prepare($this->driver, $statement);
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
        $data = $this->convert($data);
        $temp = \odbc_execute($this->statement, $data);
        if (!$temp) {
            throw new DBException('Could not execute query : '.\odbc_errormsg($this->driver));
        }
        $iid = null;
        if (preg_match('@^\s*(INSERT|REPLACE)\s+INTO@i', $this->sql)) {
            $iid = \odbc_exec($this->driver, 'SELECT @@IDENTITY');
            if ($iid !== false && \odbc_fetch_row($iid)) {
                $iid = \odbc_result($iid, 1);
            } else {
                $iid = null;
            }
        }
        return new Result($this->statement, $data, $iid, $this->charIn, $this->charOut);
    }
    protected function convert(mixed $data): mixed
    {
        if (!is_callable("\iconv") || !isset($this->charIn) || !isset($this->charOut)) {
            return $data;
        }
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = $this->convert($v);
            }
            return $data;
        }
        if (is_string($data)) {
            return \iconv($this->charOut, $this->charIn, $data);
        }
        return $data;
    }
}
