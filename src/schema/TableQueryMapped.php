<?php
namespace vakata\database\schema;

use vakata\collection\Collection;
use vakata\database\DBInterface;

/**
 * A database query class with mapping
 * @template T of Entity
 * @implements \ArrayAccess<?int,T>
 * @implements \IteratorAggregate<int,T>
 */
class TableQueryMapped extends TableQuery implements \ArrayAccess, \IteratorAggregate
{
    /**
     * @var MapperInterface<T>
     */
    protected MapperInterface $mapper;

    public function __construct(
        DBInterface $db,
        Table|string $table,
        bool $findRelations = false,
        ?MapperInterface $mapper = null
    ) {
        parent::__construct($db, $table, $findRelations);
        $this->mapper = $mapper ?? $db->getMapper($this->definition);
    }
    public function columns(array $fields, bool $addPrimary = true, bool $alias = true): static
    {
        return parent::columns($fields, $addPrimary, $alias);
    }
    /**
     * Perform the actual fetch
     * @param  array|null $fields optional array of columns to select (related columns can be used too)
     * @return Collection<int,T>               the query result as a mapped Collection
     */
    public function iterator(?array $fields = null, ?array $collectionKey = null): Collection
    {
        return Collection::from(parent::iterator($fields, $collectionKey))
            ->map(function ($v) {
                return $this->mapper->entity($v);
            })
            ->values();
    }
    /**
     * @param array|null $fields
     * @return Collection<int,T>
     */
    public function collection(?array $fields = null): Collection
    {
        return new Collection($this->iterator($fields));
    }
    /**
     * Create an empty entity for the queried table.
     *
     * @param array $data optional array of data to populate with
     * @return T
     */
    public function create(array $data = []): object
    {
        return $this->mapper->entity($data, true);
    }
    /**
     * Create an empty entity for the queried table.
     *
     * @param array $data optional array of data to populate with
     * @return T
     */
    public function entity(array $data = []): object
    {
        return $this->mapper->entity($data, true);
    }
    /**
     * @param mixed $primary
     * @return ?T
     */
    public function find(mixed $primary): mixed
    {
        return parent::find($primary);
    }
}
