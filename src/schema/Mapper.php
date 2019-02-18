<?php
namespace vakata\database\schema;

use vakata\collection\Collection;
use vakata\database\DBInterface;

/**
 * A basic mapper to enable relation traversing and basic create / update / delete functionality
 */
class Mapper
{
    protected $db;
    protected $objects;

    public function __construct(DBInterface $db)
    {
        $this->db = $db;
    }
    /**
     * Create an entity from an array of data
     *
     * @param Table $definition
     * @param array $data
     * @param boolean $empty
     * @return object
     */
    public function entity(Table $definition, array $data, bool $empty = false)
    {
        if (!$empty) {
            $primary = [];
            foreach ($definition->getPrimaryKey() as $column) {
                $primary[$column] = $data[$column];
            }
            if (isset($this->objects[$definition->getName()][base64_encode(serialize($primary))])) {
                return $this->objects[$definition->getName()][base64_encode(serialize($primary))];
            }
        }
        $entity = new class ($this, $definition, $data, $empty) extends \StdClass {
            protected $mapper;
            protected $empty;
            protected $definition;
            protected $initial = [];
            protected $changed = [];
            protected $fetched = [];

            public function __construct($mapper, $definition, array $data = [])
            {
                $this->mapper = $mapper;
                $this->definition = $definition;
                $this->initial = $data;
            }
            public function __lazyProperty(string $property, $resolve)
            {
                $this->fetched[$property] = $resolve;
                return $this;
            }
            public function __get($property)
            {
                if (isset($this->changed[$property])) {
                    return $this->changed[$property];
                }
                if (isset($this->initial[$property])) {
                    return $this->initial[$property];
                }
                if (isset($this->fetched[$property])) {
                    return is_callable($this->fetched[$property]) ?
                        $this->fetched[$property] = call_user_func($this->fetched[$property]) :
                        $this->fetched[$property];
                }
                return null;
            }
            public function __set($property, $value)
            {
                $this->changed[$property] = $value;
            }
            public function __call($method, $args)
            {
                if (isset($this->definition->getRelations()[$method])) {
                    if (isset($this->fetched[$method])) {
                        return is_callable($this->fetched[$method]) ?
                            $this->fetched[$method] = call_user_func($this->fetched[$method], $args[0] ?? null) :
                            $this->fetched[$method];
                    }
                }
                return null;
            }
            public function definition()
            {
                return $this->definition;
            }
            public function toArray(bool $fetch = false)
            {
                $data = [];
                foreach ($this->definition->getColumns() as $k) {
                    if (isset($this->fetched[$k])) {
                        if ($fetch) {
                            $this->fetched[$k] = call_user_func($this->fetched[$k]);
                        }
                        if (!is_callable($this->fetched[$k])) {
                            $data[$k] = $this->fetched[$k];
                        }
                    }
                    if (isset($this->initial[$k])) {
                        $data[$k] = $this->initial[$k];
                    }
                    if (isset($this->changed[$k])) {
                        $data[$k] = $this->changed[$k];
                    }
                }
                return $data;
            }
            public function fromArray(array $data)
            {
                foreach ($this->definition->getColumns() as $k) {
                    if (isset($data[$k])) {
                        $this->changed[$k] = $data[$k];
                    }
                }
                return $this;
            }
            public function id()
            {
                $primary = [];
                foreach ($this->definition->getPrimaryKey() as $k) {
                    $primary[$k] = $this->initial[$k] ?? null;
                }
                return $primary;
            }
            public function save()
            {
                $this->mapper->save($this);
                return $this->flatten();
            }
            public function delete()
            {
                $this->mapper->delete($this);
            }
            public function refresh()
            {
                $this->mapper->refresh($this);
                return $this->flatten();
            }
            public function flatten()
            {
                $this->initial = $this->toArray();
                $this->changed = [];
                return $this;
            }
        };
        if ($empty) {
            return $entity;
        }
        $this->lazy($entity, $data);
        return $this->objects[$definition->getName()][base64_encode(serialize($primary))] = $entity;
    }
    /**
     * Get a collection of entities
     *
     * @param TableQuery $iterator
     * @param Table $definition
     * @return Collection
     */
    public function collection(TableQueryIterator $iterator, Table $definition) : Collection
    {
        return Collection::from($iterator)
            ->map(function ($v) use ($definition) {
                return $this->entity($definition, $v);
            });
    }
    /**
     * Persist all changes to an entity in the DB. Does not include modified relation collections.
     *
     * @param object $entity
     * @return object
     */
    public function save($entity)
    {
        $query = $this->db->table($entity->definition()->getName());
        $primary = $entity->id();
        if (!isset($this->objects[$entity->definition()->getName()][base64_encode(serialize($primary))])) {
            $new = $query->insert($entity->toArray());
            $entity->fromArray($new);
            $this->objects[$entity->definition()->getName()][base64_encode(serialize($new))] = $entity;
        } else {
            foreach ($primary as $k => $v) {
                $query->filter($k, $v);
            }
            $query->update($entity->toArray());
            $new = [];
            foreach ($primary as $k => $v) {
                $new[$k] = $entity->{$k};
            }
            if (base64_encode(serialize($new)) !== base64_encode(serialize($primary))) {
                unset($this->objects[$entity->definition()->getName()][base64_encode(serialize($primary))]);
                $this->objects[$entity->definition()->getName()][base64_encode(serialize($new))] = $entity;
            }
        }
        return $this->lazy($entity, $entity->toArray());
    }
    /**
     * Delete an entity from the database
     *
     * @param object $entity
     * @return void
     */
    public function delete($entity)
    {
        $query = $this->db->table($entity->definition()->getName());
        $primary = $entity->id();
        if (isset($this->objects[$entity->definition()->getName()][base64_encode(serialize($primary))])) {
            foreach ($primary as $k => $v) {
                $query->filter($k, $v);
            }
            $query->delete();
            unset($this->objects[$entity->definition()->getName()][base64_encode(serialize($primary))]);
        }
    }
    /**
     * Refresh an entity from the DB (includes own columns and relations).
     *
     * @param object $entity
     * @return object
     */
    public function refresh($entity)
    {
        $query = $this->db->table($entity->definition()->getName());
        $primary = $entity->id();
        foreach ($primary as $k => $v) {
            $query->filter($k, $v);
        }
        $data = $query[0] ?? [];
        $entity->fromArray($data);
        return $this->lazy($entity, $data);
    }
    protected function lazy($entity, $data)
    {
        $primary = $entity->id();
        $definition = $entity->definition();
        foreach ($definition->getColumns() as $column) {
            if (!isset($data[$column])) {
                $entity->__lazyProperty($column, function () use ($entity, $definition, $primary, $column) {
                    $query = $this->db->table($definition->getName());
                    foreach ($primary as $k => $v) {
                        $query->filter($k, $v);
                    }
                    return $query->select([$column])[0][$column] ?? null;
                });
            }
        }
        foreach ($definition->getRelations() as $name => $relation) {
            $entity->__lazyProperty(
                $name,
                isset($data[$name]) ?
                    ($relation->many ? 
                        array_map(function ($v) use ($relation) {
                            return $this->entity($relation->table, $v);
                        }, $data[$name]) :
                        $this->entity($relation->table, $data[$name])
                    ) :
                    function (array $columns = null) use ($entity, $definition, $primary, $relation, $data) {
                        $query = $this->db->table($relation->table->getName(), true);
                        if ($columns !== null) {
                            $query->columns($columns);
                        }
                        if ($relation->sql) {
                            $query->where($relation->sql, $relation->par);
                        }
                        if ($relation->pivot) {
                            $nm = null;
                            foreach ($relation->table->getRelations() as $rname => $rdata) {
                                if ($rdata->pivot && $rdata->pivot->getName() === $relation->pivot->getName()) {
                                    $nm = $rname;
                                }
                            }
                            if (!$nm) {
                                $nm = $definition->getName();
                                $relation->table->manyToMany(
                                    $this->db->table($definition->getName()),
                                    $relation->pivot,
                                    $nm,
                                    array_flip($relation->keymap),
                                    $relation->pivot_keymap
                                );
                            }
                            foreach ($definition->getPrimaryKey() as $v) {
                                $query->filter($nm . '.' . $v, $data[$v] ?? null);
                            }
                        } else {
                            foreach ($relation->keymap as $k => $v) {
                                $query->filter($v, $entity->{$k} ?? null);
                            }
                        }
                        return $relation->many ?
                            $query->iterator() :
                            $query[0];
                    }
            );
        }
        return $entity;
    }
}