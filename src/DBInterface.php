<?php

namespace vakata\database;

use \vakata\collection\Collection;

interface DBInterface
{
    public static function getDriver(string $connectionString);
    public function prepare(string $sql) : StatementInterface;
    public function query(string $sql, $par = null);
    public function get(string $sql, $par = null, string $key = null, bool $skip = false, bool $opti = true) : Collection;
    public function one(string $sql, $par = null, bool $opti = true);
    public function all(string $sql, $par = null, string $key = null, bool $skip = false, bool $opti = true) : array;

    public function begin() : DBInterface;
    public function commit() : DBInterface;
    public function rollback() : DBInterface;
    public function driver() : string;
}
