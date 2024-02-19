<?php
namespace vakata\database\schema;

use vakata\database\DBInterface;

/**
 * A basic mapper to enable relation traversing and basic create / update / delete functionality
 * 
 * @template-covariant T of Entity
 * @implements MapperInterface<Entity>
 */
class Mapper implements MapperInterface
{
    protected DBInterface $db;
    protected Table $table;
    protected array $objects = [];

    public function __construct(DBInterface $db, Table $table)
    {
        $this->db = $db;
        $this->table = $table;
    }
    /**
     * 
     * @param array<string,mixed> $data
     * @param array<string,callable> $lazy
     * @param array<string,callable> $relations
     * @return T
     */
    protected function instance(array $data = [], array $lazy = [], array $relations = []): Entity
    {
        return new Entity($data, $lazy, $relations);
    }
    /**
     * @param T $entity
     * @return array<string,mixed>
     */
    public function id(Entity $entity): array
    {
        $data = [];
        foreach ($this->table->getPrimaryKey() as $column) {
            try {
                $data[$column] = $entity->{$column} ?? null;
            } catch (\Throwable $ignore) {
            }
        }
        return $data;
    }
    /**
     * @param T $entity
     * @return array<string,mixed>
     */
    public function toArray(Entity $entity): array
    {
        $data = [];
        foreach ($this->table->getColumns() as $column) {
            try {
                $data[$column] = $entity->{$column} ?? null;
            } catch (\Throwable $ignore) {
            }
        }
        return $data;
    }

    /**
     * Create an entity from an array of data
     *
     * @param array<string,mixed> $data
     * @param boolean $empty
     * @return T
     */
    public function entity(array $data, bool $empty = false): Entity
    {
        $primary = [];
        if (!$empty) {
            foreach ($this->table->getPrimaryKey() as $column) {
                $primary[$column] = $data[$column];
            }
            if (isset($this->objects[base64_encode(serialize($primary))])) {
                return $this->objects[base64_encode(serialize($primary))][1];
            }
        }
        $temp = [];
        foreach ($this->table->getColumns() as $column) {
            if (isset($data[$column])) {
                $temp[$column] = $data[$column];
            }
            if ($empty) {
                $temp[$column] = null;
            }
        }
        if ($empty) {
            return $this->instance($temp);
        }
        $lazy = [];
        foreach ($this->table->getColumns() as $column) {
            if (!array_key_exists($column, $temp)) {
                $lazy[$column] = function () use ($primary, $column) {
                    $query = $this->db->table($this->table->getFullName());
                    foreach ($primary as $k => $v) {
                        $query->filter($k, $v);
                    }
                    return $query->select([$column])[0][$column] ?? null;
                };
            }
        }
        $relations = [];
        foreach ($this->table->getRelations() as $name => $relation) {
            $mapper = $this->db->getMapper($relation->table);
            $relations[$name] = function (bool $queryOnly = false) use (
                $name,
                $relation,
                $mapper,
                $data
            ) {
                if (!$queryOnly && isset($data[$name])) {
                    return $relation->many ?
                        array_map(function ($v) use ($mapper) {
                            return $mapper->entity($v);
                        }, $data[$name]) :
                        $mapper->entity($data[$name]);
                }
                $query = $this->db->tableMapped($relation->table->getFullName());
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
                        $nm = $this->table->getName();
                        $relation->table->manyToMany(
                            $this->table,
                            $relation->pivot,
                            $nm,
                            array_flip($relation->keymap),
                            $relation->pivot_keymap
                        );
                    }
                    foreach ($this->table->getPrimaryKey() as $v) {
                        $query->filter($nm . '.' . $v, $data[$v] ?? null);
                    }
                } else {
                    foreach ($relation->keymap as $k => $v) {
                        $query->filter($v, $data[$k] ?? null);
                    }
                }
                if ($queryOnly) {
                    return $query;
                }
                return $relation->many ?
                    $query->iterator() :
                    ($query[0] ?? null);
            };
        }
        $entity = $this->instance($temp, $lazy, $relations);
        $this->objects[base64_encode(serialize($primary))] = $this->objects[spl_object_hash($entity)] = [
            $primary,
            $entity
        ];
        return $entity;
    }
    /**
     * Persist all changes to an entity in the DB. Does not include modified relation collections.
     *
     * @param T $entity
     * @return T
     */
    public function save(object $entity): Entity
    {
        if (!($entity instanceof Entity)) {
            throw new \Exception('Invalid object');
        }
        $query = $this->db->table($this->table->getFullName());
        $data = $this->toArray($entity);
        if (!isset($this->objects[spl_object_hash($entity)])) {
            foreach ($this->table->getPrimaryKey() as $column) {
                if (array_key_exists($column, $data) && !isset($data[$column])) {
                    unset($data[$column]);
                }
            }
            $new = $query->insert($data);
            $entity = $this->entity(array_merge($data, $new));
            $this->objects[base64_encode(serialize($new))] = $this->objects[spl_object_hash($entity)] = [
                $new,
                $entity
            ];
        } else {
            $primary = $this->objects[spl_object_hash($entity)][0];
            foreach ($primary as $k => $v) {
                $query->filter($k, $v);
            }
            $query->update($data);
            $new = [];
            foreach ($this->table->getPrimaryKey() as $column) {
                $new[$column] = $data[$column];
            }
            unset($this->objects[base64_encode(serialize($primary))]);
            unset($this->objects[spl_object_hash($entity)]);
            $entity = $this->entity(array_merge($data, $new));
            $this->objects[base64_encode(serialize($new))] = $this->objects[spl_object_hash($entity)] = [
                $new,
                $entity
            ];
        }
        return $entity;
    }
    /**
     * Delete an entity from the database
     *
     * @param Т $entity
     * @return void
     */
    public function delete(object $entity): void
    {
        if (!($entity instanceof Entity)) {
            throw new \Exception('Invalid object');
        }
        if (isset($this->objects[spl_object_hash($entity)])) {
            $query = $this->db->table($this->table->getFullName());
            $primary = $this->objects[spl_object_hash($entity)][0];
            foreach ($primary as $k => $v) {
                $query->filter($k, $v);
            }
            $query->delete();
            unset($this->objects[base64_encode(serialize($primary))]);
            unset($this->objects[spl_object_hash($entity)]);
        }
    }
}
