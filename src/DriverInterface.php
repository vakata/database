<?php

namespace vakata\database;

interface DriverInterface
{
    public function prepare(string $sql) : StatementInterface;
    public function begin() : bool;
    public function commit() : bool;
    public function rollback() : bool;

    public function name() : string;
    public function option($key, $default = null);
}