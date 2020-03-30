<?php

namespace vakata\database;

use \vakata\database\schema\Table;

interface DriverInterface
{
    public function prepare(string $sql) : StatementInterface;
    public function query(string $sql, $par = null, bool $buff = true) : ResultInterface;
    public function begin() : bool;
    public function commit() : bool;
    public function rollback() : bool;

    public function connect();
    public function disconnect();

    public function name() : string;
    public function option(string $key, $default = null);

    public function table(string $table, bool $detectRelations = true) : Table;
    public function tables() : array;

    public function test() : bool;
}
