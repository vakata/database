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
    protected static $mappers = [];
    protected $mapper;

    public function __construct(DBInterface $db, $table)
    {
        parent::__construct($db, $table);
        if (!isset(static::$mappers[spl_object_hash($db)])) {
            static::$mappers[spl_object_hash($db)] = new Mapper($db);
        }
        $this->mapper = static::$mappers[spl_object_hash($db)];
    }
    public function iterator(array $fields = null) : \Iterator
    {
        return $this->mapper->collection(parent::iterator($fields), $this->definition);
    }
}