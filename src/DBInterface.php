<?php

namespace vakata\database;

use \vakata\collection\Collection;
use \vakata\database\schema\Table;
use vakata\database\schema\TableQuery;
use vakata\database\schema\TableQueryMapped;

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
    public function all(
        string $sql,
        mixed $par = null,
        string $key = null,
        bool $skip = false,
        bool $opti = true
    ): array;
    public function unbuffered(
        string $sql,
        mixed $par = null,
        string $key = null,
        bool $skip = false,
        bool $opti = true
    ) : Collection;

    public function begin(bool $soft = false) : DBInterface;
    public function commit(bool $soft = false) : DBInterface;
    public function rollback(bool $soft = false) : DBInterface;
    public function driver() : DriverInterface;
    public function driverName() : string;
    public function driverOption(string $key, mixed $default = null): mixed;

    public function definition(string $table, bool $detectRelations = true): Table;
    public function parseSchema(): static;
    public function getSchema(): Schema;
    public function setSchema(Schema $schema): static;
    public function table(string $table, bool $findRelations = false): TableQuery;
    public function tableMapped(string $table, bool $findRelations = false): TableQueryMapped;
    public function findRelation(string $start, string $end): array;

    /**
     * get a single row
     *
     * @param string $sql
     * @param array $par
     * @return ?array<string,scalar|null>
     */
    public function row(string $sql, array $par = []): ?array;
    public function rows(string $sql, array $par = []): Collection;
    /**
     * get a column
     *
     * @param string $sql
     * @param array $par
     * @param string|null $key
     * @return array<scalar|null>
     */
    public function col(string $sql, array $par = []): array;
    /**
     * get a single value
     *
     * @param string $sql
     * @param array $par
     * @return scalar|null
     */
    public function val(string $sql, array $par = []): mixed;
}
