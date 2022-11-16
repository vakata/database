<?php

namespace vakata\database;

use \vakata\collection\Collection;
use \vakata\database\schema\Table;
use vakata\database\schema\TableQuery;

interface DBInterface
{
    public function prepare(string $sql, ?string $name = null): StatementInterface;
    public function query(string $sql, mixed $par = null, bool $buff = true): ResultInterface;
    public function get(
        string $sql,
        mixed $par = null,
        string $key = null,
        bool $skip = false,
        bool $opti = true,
        bool $buff = true
    ) : Collection;
    public function one(string $sql, mixed $par = null, bool $opti = true): mixed;
    public function all(string $sql, mixed $par = null, string $key = null, bool $skip = false, bool $opti = true): array;
    public function unbuffered(
        string $sql,
        mixed $par = null,
        string $key = null,
        bool $skip = false,
        bool $opti = true
    ) : Collection;

    public function begin() : DBInterface;
    public function commit() : DBInterface;
    public function rollback() : DBInterface;
    public function driver() : DriverInterface;
    public function driverName() : string;
    public function driverOption(string $key, mixed $default = null): mixed;

    public function definition(string $table, bool $detectRelations = true): Table;
    public function parseSchema(): static;
    public function getSchema(bool $asPlainArray = true): array;
    public function setSchema(array $data): static;
    public function table(string $table, bool $mapped = false, bool $findRelations = false): TableQuery;
    public function findRelation(string $start, string $end): array;
}
