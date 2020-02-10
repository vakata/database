<?php

namespace vakata\database;

use \vakata\collection\Collection;
use \vakata\database\schema\Table;

interface DBInterface
{
    public function prepare(string $sql) : StatementInterface;
    public function query(string $sql, $par = null);
    public function get(
        string $sql,
        $par = null,
        string $key = null,
        bool $skip = false,
        bool $opti = true
    ) : Collection;
    public function one(string $sql, $par = null, bool $opti = true);
    public function all(string $sql, $par = null, string $key = null, bool $skip = false, bool $opti = true) : array;

    public function begin() : DBInterface;
    public function commit() : DBInterface;
    public function rollback() : DBInterface;
    public function driverName() : string;
    public function driverOption(string $key, $default = null);

    public function definition(string $table, bool $detectRelations = true) : Table;
    public function parseSchema();
    public function getSchema($asPlainArray = true);
    public function setSchema(array $data);
    public function table(string $table, bool $mapped = false);
}
