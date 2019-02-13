<?php
namespace vakata\database\schema;

use vakata\collection\Collection;
use vakata\database\DBInterface;

class Mapper
{
    protected $db;
    protected $objects;

    public function __construct(DBInterface $db)
    {
        $this->db = $db;
    }
    public function entity($definition, array $data)
    {
        $primary = [];
        foreach ($definition->getPrimaryKey() as $column) {
            $primary[$column] = $data[$column];
        }
        if (isset($this->objects[$definition->getName()][base64_encode(serialize($primary))])) {
            return $this->objects[$definition->getName()][base64_encode(serialize($primary))];
        }
        $entity = new class ($definition, $data) extends \StdClass {
            protected $definition;
            protected $initial = [];
            protected $changed = [];
            protected $fetched = [];

            public function __construct($definition, array $data = [])
            {
                $this->definition = $definition;
                $this->initial = $data;
            }
            public function __lazyProperty(string $property, callable $resolve)
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
                if (isset($this->fetched)) {
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
            public function id()
            {
                $primary = [];
                foreach ($this->definition->getPrimaryKey() as $k) {
                    $primary[$k] = $this->{$k};
                }
                return $primary;
            }
        };
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
            if (isset($data[$name])) {
                $entity->{$name} = $relation->many ? 
                    array_map(function ($v) use ($relation) {
                        return $this->entity($relation->table, $v);
                    }, $data[$name]) :
                    $this->entity($relation->table, $data[$name]);
            } else {
                $entity->__lazyProperty($name, function () use ($entity, $definition, $primary, $relation, $data) {
                    $query = $this->db->table($relation->table->getName(), true);
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
                });
            }
        }
        return $this->objects[$definition->getName()][base64_encode(serialize($primary))] = $entity;
    }
    public function collection($iterator, $definition)
    {
        return Collection::from($iterator)
            // ->mapKey(function ($v, $k) use ($definition) {
            //     $pk = $definition->getPrimaryKey();
            //     return count($pk) === 1 ? ($v[$pk[0]] ?? $k) : $k;
            // })
            ->map(function ($v) use ($definition) {
                return $this->entity($definition, $v);
            });
    }
}