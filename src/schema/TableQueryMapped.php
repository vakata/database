<?php
namespace vakata\database\schema;

use vakata\collection\Collection;
use vakata\database\DBInterface;
use vakata\database\DBException;
use vakata\database\ResultInterface;

/**
 * A database query class with mapping
 */
class TableQueryMapped extends TableQuery
{
    protected static array $mappers = [];
    protected MapperInterface $mapper;

    public function __construct(DBInterface $db, Table|string $table, bool $findRelations = false)
    {
        parent::__construct($db, $table, $findRelations);
        if (!isset(static::$mappers[spl_object_hash($db)])) {
            static::$mappers[spl_object_hash($db)] = new Mapper($db);
        }
        $this->mapper = static::$mappers[spl_object_hash($db)];
    }
    /**
     * Perform the actual fetch
     * @param  array|null $fields optional array of columns to select (related columns can be used too)
     * @return Collection               the query result as a mapped Collection
     */
    public function iterator(array $fields = null, array $collectionKey = null): Collection
    {
        return $this->mapper->collection(parent::iterator($fields, $collectionKey), $this->definition);
    }
    /**
     * Create an empty entity for the queried table.
     *
     * @param array $data optional array of data to populate with
     * @return object
     */
    public function create(array $data = []): object
    {
        return $this->mapper->entity($this->definition, $data, true);
    }
}
