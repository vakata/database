<?php

namespace vakata\database;

use \vakata\database\schema\Table;

interface DriverInterface
{
    public function prepare(string $sql) : StatementInterface;
    public function query(string $sql, $par = null) : ResultInterface;
    public function begin() : bool;
    public function commit() : bool;
    public function rollback() : bool;

    public function name() : string;
    public function option($key, $default = null);

    public function table(string $table, bool $detectRelations = true) : Table;
    public function tables() : array;
}