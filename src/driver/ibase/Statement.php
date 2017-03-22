<?php

namespace vakata\database\driver\ibase;

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
        foreach ($data as $i => $v) {
            switch (gettype($v)) {
                case 'boolean':
                case 'integer':
                    $data[$i] = (int) $v;
                    break;
                case 'array':
                    $data[$i] = implode(',', $v);
                    break;
                case 'object':
                    $data[$i] = serialize($data[$i]);
                    break;
                case 'resource':
                    if (is_resource($v) && get_resource_type($v) === 'stream') {
                        $data[$i] = stream_get_contents($data[$i]);
                    } else {
                        $data[$i] = serialize($data[$i]);
                    }
                    break;
            }
        }
        array_unshift($data, $this->statement);
        $temp = call_user_func_array("\ibase_execute", $data);
        if (!$temp) {
            throw new DBException('Could not execute query : '.\ibase_errmsg());
        }
        return new Result($temp, $data, $this->driver);
    }
}