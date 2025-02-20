<?php

namespace vakata\database;

use \vakata\database\schema\Table;

interface DriverInterface
{
    public function prepare(string $sql, ?string $name = null, ?array $map = null): StatementInterface;
    public function query(string $sql, mixed $par = null, bool $buff = true): ResultInterface;
    public function raw(string $raw): mixed;

    public function begin() : bool;
    public function commit() : bool;
    public function rollback() : bool;

    public function softBegin() : void;
    public function softCommit() : void;
    public function softRollback() : void;

    public function connect(): void;
    public function disconnect(): void;

    public function name(): string;
    public function option(string $key, mixed $default = null): mixed;

    public function table(string $table, bool $detectRelations = true) : Table;
    public function tables(): array;

    public function test(): bool;
}
