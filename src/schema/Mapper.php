<?php
namespace vakata\database\schema;

use vakata\collection\Collection;
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
    protected array $index = [];
    protected array $objects = [];
    /**
     * @var class-string<T>
     */
    protected string $clss = Entity::class;

    /**
     * @param DBInterface $db
     * @param string|Table|null $table
     * @param class-string<T> $clss
     * @return void
     */
    public function __construct(DBInterface $db, string|Table $table = '', string $clss = Entity::class)
    {
        $this->db = $db;
        if (!$table) {
            $table = preg_replace('(mapper$)', '', strtolower(basename(str_replace('\\', '/', static::class))));
        }
        if (!($table instanceof Table)) {
            $table = $this->db->definition($table);
        }
        $this->table = $table;
        $this->clss = $clss;
    }
    protected function hash(array $data): string
    {
        ksort($data);
        return sha1(serialize($data));
    }
    /**
     * 
     * @param array<string,mixed> $data
     * @param array<string,callable> $lazy
     * @param array<string,callable> $relations
     * @return T
     */
    protected function instance(array $data = [], array $lazy = [], array $relations = []): object
    {
        return new ($this->clss)($data, $lazy, $relations);
    }
    /**
     * @param T $entity
     * @return array<string,mixed>
     */
    public function id(object $entity): array
    {
        $temp = [];
        foreach ($this->table->getPrimaryKey() as $column) {
            try {
                $temp[$column] = $entity->{$column} ?? null;
            } catch (\Throwable $ignore) {
            }
        }
        return $temp;
    }
    /**
     * 
     * @param T $entity
     * @param null|array $columns
     * @param null|array $relations
     * @param bool $fetch
     * @return array<string, mixed>
     */
    public function toArray(
        object $entity,
        ?array $columns = null,
        ?array $relations = [],
        bool $fetch = false
    ): array
    {
        if (!isset($columns)) {
            $columns = $this->table->getColumns();
        }
        if (!isset($relations)) {
            $relations = array_keys($this->table->getRelations());
        }

        // BEG: ugly hack to get relations changed directly on the object (not hydrated)
        $hack = [];
        foreach ((array)$entity as $k => $v) {
            $hack[$k[0] === "\0" ? substr($k, strrpos($k, "\0", 1) + 1) : $k] = $v;
        }
        $hack = $hack['changed'] ?? [];
        // END: ugly hack to get relations changed directly on the object (not hydrated)

        $temp = [];
        $fetched = $this->objects[spl_object_hash($entity)][3];
        foreach ($columns as $column) {
            try {
                if (in_array($column, $fetched) || array_key_exists($column, $hack) || $fetch) {
                    $temp[$column] = $entity->{$column};
                }
            } catch (\Throwable $ignore) {
            }
        }

        $fetched = $this->objects[spl_object_hash($entity)][4];
        foreach ($relations as $relation) {
            try {
                if (array_key_exists($relation, $fetched) || array_key_exists($relation, $hack) || $fetch) {
                    $temp[$relation] = $entity->{$relation};
                }
            } catch (\Throwable $ignore) {
            }
        }
        return $temp;
    }
    /**
     * @param T $entity
     * @return void
     */
    public function fromArray(object $entity, array $data): void
    {
        foreach ($data as $k => $v) {
            $entity->{$k} = $v;
        }
    }

    /**
     * Create an entity from an array of data
     *
     * @param array<string,mixed> $data
     * @param boolean $empty
     * @return T
     */
    public function entity(array $data, bool $empty = false): object
    {
        $primary = [];
        if (!$empty) {
            foreach ($this->table->getPrimaryKey() as $column) {
                $primary[$column] = $data[$column];
            }
            if (isset($this->index[base64_encode(serialize($primary))])) {
                return $this->objects[$this->index[base64_encode(serialize($primary))]][1];
            }
        }
        $temp = [];
        foreach ($this->table->getColumns() as $column) {
            if (array_key_exists($column, $data)) {
                $temp[$column] = $data[$column];
            }
            if ($empty) {
                $temp[$column] = null;
            }
        }
        $lazy = [];
        foreach ($this->table->getColumns() as $column) {
            if (!array_key_exists($column, $temp)) {
                $lazy[$column] = function ($entity) use ($column) {
                    $query = $this->db->table($this->table->getFullName());
                    foreach ($this->id($entity) as $k => $v) {
                        $query->filter($k, $v);
                    }
                    $temp = $this->toArray($entity);
                    $value = $query->select([$column])[0][$column] ?? null;
                    $clean = $this->objects[spl_object_hash($entity)][2] === $this->hash($temp);
                    $this->objects[spl_object_hash($entity)][3][] = $column;
                    if ($clean) {
                        $temp[$column] = $value;
                        $this->objects[spl_object_hash($entity)][2] = $this->hash($temp);
                    }
                    return $value;
                };
            }
        }
        $relations = [];
        foreach ($this->table->getRelations() as $name => $relation) {
            $relations[$name] = function (object $entity, bool $queryOnly = false) use (
                $name,
                $relation,
                $data
            ) {
                $mapper = $this->db->getMapper($relation->table);
                if (!$queryOnly && array_key_exists($name, $data)) {
                    if (!isset($data[$name])) {
                        return $this->objects[spl_object_hash($entity)][4][$name] = null;
                    }
                    $value = $relation->many ?
                        Collection::from(array_map(function ($v) use ($mapper) {
                            return $mapper->entity($v);
                        }, $data[$name]))
                            ->filter(function ($v) use ($mapper) {
                                return !$mapper->deleted($v);
                            }) :
                        ($mapper->deleted($data[$name]) ? null : $mapper->entity($data[$name]));
                    $this->objects[spl_object_hash($entity)][4][$name] = isset($value) ? spl_object_hash($value) : null;
                    return $value;
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
                    $pk = $this->id($entity);
                    foreach ($pk as $k => $v) {
                        $query->filter($nm . '.' . $k, $v);
                    }
                } else {
                    $temp = $this->toArray($entity, array_keys($relation->keymap));
                    foreach ($relation->keymap as $k => $v) {
                        $query->filter($v, $temp[$k] ?? null);
                    }
                }
                if ($queryOnly) {
                    return $query;
                }
                $value = $relation->many ?
                    $query->iterator() :
                    ($query[0] ?? null);
                if ($value instanceof Collection) {
                    $value->filter(function ($v) use ($mapper) {
                        return !$mapper->deleted($v);
                    });
                } elseif (isset($value) && $mapper->deleted($value)) {
                    $value = null;
                }
                $this->objects[spl_object_hash($entity)][4][$name] = isset($value) ? spl_object_hash($value) : null;
                return $value;
            };
        }
        $entity = $this->instance($temp, $lazy, $relations);
        if (!$empty) {
            $this->index[base64_encode(serialize($primary))] = spl_object_hash($entity);
        }
        $this->objects[spl_object_hash($entity)] = [
            $primary,
            $entity,
            '',
            array_keys($temp),
            []
        ];
        $this->objects[spl_object_hash($entity)][2] = $this->hash($this->toArray($entity));
        return $entity;
    }
    /**
     * @param T $entity
     * @return array<int,string>
     */
    protected function changedRelations(object $entity): array
    {
        $temp = [];
        $data = $this->toArray($entity, [], null, false);
        $fetched = $this->objects[spl_object_hash($entity)][4];
        foreach ($this->table->getRelations() as $name => $relation) {
            if (array_key_exists($name, $data)) {
                $hash = isset($data[$name]) ? spl_object_hash($data[$name]) : null;
                if (!array_key_exists($name, $fetched) || $fetched[$name] !== $hash) {
                    $temp[] = $name;
                }
                if ($data[$relation->name] instanceof Collection && $data[$relation->name]->changed()) {
                    $temp[] = $name;
                }
            }
        }
        return $temp;
    }
    /**
     * @param T $entity
     * @return bool
     */
    public function isDirty(object $entity, bool $relations = false): bool
    {
        // new record, not found in DB
        if (!in_array(spl_object_hash($entity), $this->index)) {
            return true;
        }
        // changed internal columns
        if ($this->hash($this->toArray($entity)) !== $this->objects[spl_object_hash($entity)][2]) {
            return true;
        }
        // check relations
        if ($relations && count($this->changedRelations($entity))) {
            return true;
        }
        return false;
    }
    /**
     * Persist all changes to an entity in the DB. Does not include modified relation collections.
     *
     * @param T $entity
     * @return T
     */
    public function save(object $entity, bool $relations = false): object
    {
        $query = $this->db->table($this->table->getFullName());
        $data = $this->toArray($entity);
        $new = [];
        $old = [];
        if ($relations) {
            $rels = $this->toArray($entity, [], null);
            foreach ($this->table->getRelations() as $relation) {
                // cannot filter based on changed as the remote object may be modified
                if (!array_key_exists($relation->name, $rels)) {
                    // relation not hydrated
                    continue;
                }
                // if the relation updated a local column
                if (
                    !$relation->many &&
                    !count(array_intersect(array_keys($relation->keymap), $this->table->getPrimaryKey()))
                ) {
                    $value = $rels[$relation->name];
                    if ($value !== null) {
                        $mapper = $this->db->getMapper($relation->table);
                        // save the remote relation if dirty
                        if ($mapper->isDirty($value, false)) {
                            $value = $mapper->save($value, false);
                        }
                        $value = $mapper->toArray($value);
                        foreach ($relation->keymap as $local => $remote) {
                            $data[$local] = $value[$remote];
                        }
                    } else {
                        foreach (array_keys($relation->keymap) as $local) {
                            $data[$local] = null;
                        }
                    }
                }
            }
        }
        $changed = 0; // 0 - nothing is done, 1 - record is created, 2 - changed, 3 - primary key changed
        if (!in_array(spl_object_hash($entity), $this->index)) {
            $changed = 1;
            // this is a new record
            foreach ($this->table->getPrimaryKey() as $column) {
                if (array_key_exists($column, $data) && !isset($data[$column])) {
                    unset($data[$column]);
                }
            }
            $new = $query->insert($data);
            $this->fromArray($entity, array_merge($data, $new));
            $this->index[base64_encode(serialize($new))] = spl_object_hash($entity);
            $this->objects[spl_object_hash($entity)][0] = $new;
            $this->objects[spl_object_hash($entity)][2] = $this->hash($this->toArray($entity));
        } else {
            $changed = 2;
            $old = $primary = $this->objects[spl_object_hash($entity)][0];
            foreach ($primary as $k => $v) {
                $query->filter($k, $v);
            }
            $query->update($data);
            $new = [];
            foreach ($this->table->getPrimaryKey() as $column) {
                $new[$column] = $data[$column];
            }
            if (serialize($old) !== serialize($new)) {
                $changed = 3;
            }
            unset($this->index[base64_encode(serialize($primary))]);
            $this->fromArray($entity, array_merge($data, $new));
            $this->index[base64_encode(serialize($new))] = spl_object_hash($entity);
            $this->objects[spl_object_hash($entity)][0] = $new;
            $this->objects[spl_object_hash($entity)][2] = $this->hash($this->toArray($entity));
        }
        if ($relations) {
            $new = $this->objects[spl_object_hash($entity)][0];
            // all relations will be hydrated if the PK changes
            $rels = $this->toArray($entity, [], null, $changed === 3);
            $chng = $this->changedRelations($entity);
            foreach ($this->table->getRelations() as $relation) {
                if (!array_key_exists($relation->name, $rels)) {
                    // relation not hydrated
                    continue;
                }
                if (
                    !$relation->many &&
                    count(array_intersect(array_keys($relation->keymap), $this->table->getPrimaryKey()))
                ) {
                    $value = $rels[$relation->name];
                    if (in_array($relation->name, $chng) && $changed > 1) {
                        // relation is changed - remove all references from old remote entities
                        $q = $this->db->tableMapped($relation->table->getFullName());
                        $u = [];
                        foreach ($relation->keymap as $local => $remote) {
                            $q->filter($remote, $old[$local] ?? $data[$local]);
                            $u[$remote] = null;
                        }
                        $mapper = $this->db->getMapper($relation->table);
                        foreach ($q as $e) {
                            $mapper->fromArray($e, $u);
                            if ($mapper->isDirty($value, false)) {
                                $value = $mapper->save($value, false);
                            }
                        }
                    }
                    if ($value !== null) {
                        // update entity with new values
                        $mapper = $this->db->getMapper($relation->table);
                        $temp = [];
                        foreach ($relation->keymap as $local => $remote) {
                            $temp[$remote] = $data[$local];
                        }
                        $mapper->fromArray($value, $temp);
                        if ($mapper->isDirty($value, false)) {
                            $value = $mapper->save($value, false);
                        }
                    }
                }
                if ($relation->many && !$relation->pivot) {
                    $value = $rels[$relation->name];
                    if (in_array($relation->name, $chng) && $changed > 1) {
                        // relation is changed - remove all references from old remote entities
                        $q = $this->db->tableMapped($relation->table->getFullName());
                        $u = [];
                        foreach ($relation->keymap as $local => $remote) {
                            $q->filter($remote, $old[$local] ?? $data[$local]);
                            $u[$remote] = null;
                        }
                        $mapper = $this->db->getMapper($relation->table);
                        foreach ($q as $e) {
                            $mapper->fromArray($e, $u);
                            if ($mapper->isDirty($e, false)) {
                                $mapper->save($e, false);
                            }
                        }
                    }
                    $mapper = $this->db->getMapper($relation->table);
                    foreach ($value ?? [] as $e) {
                        $temp = [];
                        foreach ($relation->keymap as $local => $remote) {
                            $temp[$remote] = $data[$local];
                        }
                        $mapper->fromArray($e, $temp);
                        if ($mapper->isDirty($e, false)) {
                            $value = $mapper->save($e, false);
                        }
                    }
                }
                if ($relation->many && $relation->pivot) {
                    $value = $rels[$relation->name];
                    if (in_array($relation->name, $chng) && $changed > 1) {
                        // relation is changed - remove all references from old remote entities
                        $q = $this->db->table($relation->pivot->getFullName());
                        foreach ($relation->keymap as $local => $remote) {
                            $q->filter($remote, $old[$local] ?? $data[$local]);
                        }
                        $q->delete();
                    }
                    $mapper = $this->db->getMapper($relation->table);
                    foreach ($value ?? [] as $e) {
                        if ($mapper->isDirty($e, false)) {
                            $e = $mapper->save($e, false);
                        }
                        $temp = $mapper->toArray($e);
                        $i = [];
                        foreach ($relation->keymap as $local => $remote) {
                            $i[$remote] = $new[$local] ?? $data[$local];
                        }
                        foreach ($relation->pivot_keymap as $local => $remote) {
                            $i[$local] = $temp[$remote];
                        }
                        $this->db->table($relation->pivot->getFullName())->insert($i);
                    }
                }
            }
        }
        return $entity;
    }
    public function exists(array|object $entity): bool
    {
        if (is_array($entity)) {
            $primary = [];
            foreach ($this->table->getPrimaryKey() as $column) {
                $primary[$column] = $entity[$column];
            }
            return isset($this->index[base64_encode(serialize($primary))]) &&
                $this->index[base64_encode(serialize($primary))] !== false;
        }
        return isset($this->objects[spl_object_hash($entity)]) &&
            $this->objects[spl_object_hash($entity)] !== false;
    }
    public function deleted(array|object $entity): bool
    {
        if (is_array($entity)) {
            $primary = [];
            foreach ($this->table->getPrimaryKey() as $column) {
                $primary[$column] = $entity[$column];
            }
            return isset($this->index[base64_encode(serialize($primary))]) &&
                $this->index[base64_encode(serialize($primary))] === false;
        }
        return isset($this->objects[spl_object_hash($entity)]) &&
            $this->objects[spl_object_hash($entity)] === false;
    }
    /**
     * Delete an entity from the database
     *
     * @param Т $entity
     * @return void
     */
    public function delete(object $entity, bool $relations = false): void
    {
        if (isset($this->objects[spl_object_hash($entity)]) && $this->objects[spl_object_hash($entity)] !== false) {
            $primary = $this->objects[spl_object_hash($entity)][0];
            if ($relations) {
                foreach ($this->table->getRelations() as $relation) {
                    if ($relation->pivot) {
                        // deleted related rows from pivot
                        $q = $this->db->table($relation->pivot->getFullName());
                        foreach ($relation->keymap as $local => $remote) {
                            $q->filter($remote, $primary[$local]);
                        }
                        $q->delete();
                    } 
                    if (
                        !$relation->many &&
                        count(array_intersect(array_keys($relation->keymap), $this->table->getPrimaryKey()))
                    ) {
                        // delete related single row
                        $q = $this->db->table($relation->table->getFullName());
                        foreach ($relation->keymap as $local => $remote) {
                            $q->filter($remote, $primary[$local]);
                        }
                        $q->delete();
                    }
                    if ($relation->many && !$relation->pivot) {
                        $q = $this->db->table($relation->table->getFullName());
                        $u = [];
                        foreach ($relation->keymap as $local => $remote) {
                            $q->filter($remote, $primary[$local]);
                            $u[$remote] = null;
                        }
                        $q->update($u);
                    }
                }
            }
            $query = $this->db->table($this->table->getFullName());
            foreach ($primary as $k => $v) {
                $query->filter($k, $v);
            }
            $query->delete();
            $this->index[base64_encode(serialize($primary))] = false;
            $this->objects[spl_object_hash($entity)] = false;
        }
    }
}
