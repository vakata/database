<?php
namespace vakata\database\schema;

use vakata\collection\Collection;
use vakata\database\DBInterface;

/**
 * A basic mapper to enable relation traversing and basic create / update / delete functionality
 */
class Mapper implements MapperInterface
{
    protected DBInterface $db;
    protected array $objects;

    public function __construct(DBInterface $db)
    {
        $this->db = $db;
    }
    protected function instance(Table $definition, array $data = []): Entity
    {
        return new Entity($this, $definition, $data);
    }
    /**
     * Create an entity from an array of data
     *
     * @param Table $definition
     * @param array $data
     * @param boolean $empty
     * @return Entity
     */
    public function entity(Table $definition, array $data, bool $empty = false): Entity
    {
        $primary = [];
        if (!$empty) {
            foreach ($definition->getPrimaryKey() as $column) {
                $primary[$column] = $data[$column];
            }
            if (isset($this->objects[$definition->getFullName()][base64_encode(serialize($primary))])) {
                return $this->objects[$definition->getFullName()][base64_encode(serialize($primary))];
            }
        }
        $entity = $this->instance($definition, $data);
        if ($empty) {
            return $entity;
        }
        $this->lazy($entity, $data);
        return $this->objects[$definition->getFullName()][base64_encode(serialize($primary))] = $entity;
    }
    /**
     * Get a collection of entities
     *
     * @param TableQueryIterator $iterator
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
     * @param Entity $entity
     * @return Entity
     */
    public function save(object $entity): Entity
    {
        if (!($entity instanceof Entity)) {
            throw new \Exception('Invalid object');
        }
        $query = $this->db->table($entity->definition()->getFullName());
        $primary = $entity->id();
        if (!isset($this->objects[$entity->definition()->getFullName()][base64_encode(serialize($primary))])) {
            $new = $query->insert($entity->toArray());
            $entity->fromArray($new);
            $this->objects[$entity->definition()->getFullName()][base64_encode(serialize($new))] = $entity;
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
                unset($this->objects[$entity->definition()->getFullName()][base64_encode(serialize($primary))]);
                $this->objects[$entity->definition()->getFullName()][base64_encode(serialize($new))] = $entity;
            }
        }
        return $this->lazy($entity, $entity->toArray());
    }
    /**
     * Delete an entity from the database
     *
     * @param Entity $entity
     * @return void
     */
    public function delete(object $entity): void
    {
        if (!($entity instanceof Entity)) {
            throw new \Exception('Invalid object');
        }
        $query = $this->db->table($entity->definition()->getFullName());
        $primary = $entity->id();
        if (isset($this->objects[$entity->definition()->getFullName()][base64_encode(serialize($primary))])) {
            foreach ($primary as $k => $v) {
                $query->filter($k, $v);
            }
            $query->delete();
            unset($this->objects[$entity->definition()->getFullName()][base64_encode(serialize($primary))]);
        }
    }
    /**
     * Refresh an entity from the DB (includes own columns and relations).
     *
     * @param Entity $entity
     * @return Entity
     */
    public function refresh(object $entity): Entity
    {
        if (!($entity instanceof Entity)) {
            throw new \Exception('Invalid object');
        }
        $query = $this->db->table($entity->definition()->getFullName());
        $primary = $entity->id();
        foreach ($primary as $k => $v) {
            $query->filter($k, $v);
        }
        $data = $query[0] ?? [];
        $entity->fromArray($data);
        return $this->lazy($entity, $data);
    }
    protected function lazy(Entity $entity, array $data): Entity
    {
        $primary = $entity->id();
        $definition = $entity->definition();
        foreach ($definition->getColumns() as $column) {
            if (!array_key_exists($column, $data)) {
                $entity->__lazyProperty($column, function () use ($definition, $primary, $column) {
                    $query = $this->db->table($definition->getFullName());
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
                array_key_exists($name, $data) && isset($data[$name]) ?
                    ($relation->many ?
                        array_map(function ($v) use ($relation) {
                            return $this->entity($relation->table, $v);
                        }, $data[$name]) :
                        $this->entity($relation->table, $data[$name])
                    ) :
                    function (
                        array $columns = null,
                        string $order = null,
                        bool $desc = false
                    ) use (
                        $entity,
                        $definition,
                        $relation,
                        $data
                    ) {
                        $query = $this->db->tableMapped($relation->table->getFullName());
                        if ($columns !== null) {
                            $query->columns($columns);
                        }
                        if ($relation->sql) {
                            $query->where($relation->sql, $relation->par?:[]);
                        }
                        if ($relation->pivot) {
                            $nm = null;
                            foreach ($relation->table->getRelations() as $rname => $rdata) {
                                if ($rdata->pivot && $rdata->pivot->getFullName() === $relation->pivot->getFullName()) {
                                    $nm = $rname;
                                }
                            }
                            if (!$nm) {
                                $nm = $definition->getName();
                                $relation->table->manyToMany(
                                    $definition,
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
                        if ($relation->many && $order) {
                            $query->sort($order, $desc);
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
