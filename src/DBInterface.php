<?php

namespace vakata\database;

use \vakata\collection\Collection;
use vakata\database\schema\Entity;
use vakata\database\schema\MapperInterface;
use \vakata\database\schema\Table;
use vakata\database\schema\TableQuery;
use vakata\database\schema\TableQueryMapped;

interface DBInterface
{
    public function prepare(string $sql, ?string $name = null): StatementInterface;
    public function query(string $sql, mixed $par = null, bool $buff = true): ResultInterface;
    /**
     * @param string $sql
     * @param mixed $par
     * @param string|null $key
     * @param boolean $skip
     * @param boolean $opti
     * @param boolean $buff
     * @return Collection<array-key,mixed>
     */
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
    /**
     * @param string $sql
     * @param mixed $par
     * @param string|null $key
     * @param boolean $skip
     * @param boolean $opti
     * @return Collection<array-key,mixed>
     */
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
    public function findRelation(string $start, string $end): array;

    /**
     * get a single row
     *
     * @param string $sql
     * @param array $par
     * @return ?array<string,scalar|null>
     */
    public function row(string $sql, array $par = []): ?array;
    /**
     * @param string $sql
     * @param array $par
     * @return Collection<int,array<array-key,scalar|null>>
     */
    public function rows(string $sql, array $par = []): Collection;
    /**
     * get a column
     *
     * @param string $sql
     * @param array $par
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

    public function entity(string $class): Entity;
    public function entities(string $class): TableQueryMapped;
    public function delete(Entity $entity): void;
    public function save(?Entity $entity = null): void;

    public function getMapper(Table|string $table): MapperInterface;
    public function setMapper(Table|string $table, MapperInterface $mapper, ?string $class = null): static;
    public function tableMapped(
        string $table,
        bool $findRelations = false,
        ?MapperInterface $mapper = null
    ): TableQueryMapped;
}
