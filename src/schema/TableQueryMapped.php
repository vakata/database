<?php
namespace vakata\database\schema;

use vakata\collection\Collection;
use vakata\database\DBInterface;
use vakata\database\DBException;
use vakata\database\ResultInterface;

/**
 * A database query class with mapping
 * @template T of Entity
 */
class TableQueryMapped extends TableQuery
{
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
    /**
     * Perform the actual fetch
     * @param  array|null $fields optional array of columns to select (related columns can be used too)
     * @return Collection<int,T>               the query result as a mapped Collection
     */
    public function iterator(array $fields = null, array $collectionKey = null): Collection
    {
        return $this->mapper->collection(parent::iterator($fields, $collectionKey), $this->definition);
    }
    /**
     * @param array|null $fields
     * @return Collection<int,T>
     */
    public function collection(?array $fields = null): Collection
    {
        return parent::collection($fields);
    }
    /**
     * Create an empty entity for the queried table.
     *
     * @param array $data optional array of data to populate with
     * @return T
     */
    public function create(array $data = []): object
    {
        return $this->mapper->entity($this->definition, $data, true);
    }
}
