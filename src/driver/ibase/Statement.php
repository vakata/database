<?php

namespace vakata\database\driver\ibase;

use \vakata\database\DBException;
use \vakata\database\DriverInterface;
use \vakata\database\StatementInterface;
use \vakata\database\ResultInterface;

class Statement implements StatementInterface
{
    protected mixed $statement;
    protected mixed $driver;
    protected ?array $map = null;

    public function __construct(mixed $statement, mixed $driver, ?array $map = null)
    {
        $this->statement = $statement;
        $this->driver = $driver;
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
