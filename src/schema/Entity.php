<?php
namespace vakata\database\schema;

use JsonSerializable;
use vakata\collection\Collection;
use vakata\database\DBException;

class Entity implements JsonSerializable
{
    /**
     * @var array<string,mixed>
     */
    protected array $data = [];
    /**
     * @var array<string,callable>
     */
    protected array $lazy = [];
    /**
     * @var array<string,mixed>
     */
    protected array $changed = [];
    /**
     * @var array<string,callable>
     */
    protected array $relations = [];
    /**
     * @var array<string,mixed>
     */
    protected array $cached = [];

    /**
     * @param array<string,mixed> $data
     * @param array<string,callable> $lazy
     * @param array<string,callable> $relations
     */
    public function __construct(array $data = [], array $lazy = [], array $relations = [])
    {
        $this->data = $data;
        $this->lazy = $lazy;
        $this->relations = $relations;
    }
    public function __isset(string $property): bool
    {
        if (array_key_exists($property, $this->changed)) {
            return isset($this->changed[$property]);
        }
        if (array_key_exists($property, $this->data)) {
            return isset($this->data[$property]);
        }
        if (isset($this->lazy[$property])) {
            $this->data[$property] = call_user_func($this->lazy[$property], $this);
            return isset($this->data[$property]);
        }
        if (array_key_exists($property, $this->cached)) {
            return isset($this->cached[$property]);
        }
        if (isset($this->relations[$property])) {
            $relation = $this->__call($property, []);
            return isset($relation);
        }
        return false;
    }
    public function &__get(string $property): mixed
    {
        if (array_key_exists($property, $this->changed)) {
            return $this->changed[$property];
        }
        if (array_key_exists($property, $this->data)) {
            return $this->data[$property];
        }
        if (isset($this->lazy[$property])) {
            $this->data[$property] = call_user_func($this->lazy[$property], $this);
            return $this->data[$property];
        }
        if (array_key_exists($property, $this->cached)) {
            return $this->cached[$property];
        }
        if (isset($this->relations[$property])) {
            $relation = $this->__call($property, []);
            return $relation;
        }
        $null = null;
        return $null;
    }
    /**
     * @param string $method
     * @param array $args
     * @return null|Entity|iterable<Entity>
     */
    public function __call(string $method, array $args): mixed
    {
        if (array_key_exists($method, $this->relations)) {
            $rslt = call_user_func($this->relations[$method], $this, ...$args);
            if (isset($args[0]) && $args[0] === true) {
                return $rslt;
            }
            $this->cached[$method] = $rslt;
        } else {
            throw new DBException('Invalid relation name: ' . $method);
        }
        return $this->cached[$method] ?? null;
    }
    public function __set(string $property, mixed $value): void
    {
        $this->changed[$property] = $value;
    }
    protected function relatedQuery(string $name): TableQueryMapped
    {
        if (!array_key_exists($name, $this->relations)) {
            throw new DBException('Invalid relation name: ' . $name);
        }
        return call_user_func_array($this->relations[$name], [$this, true]);
    }
    protected function relatedRow(string $name): mixed
    {
        if (!array_key_exists($name, $this->relations)) {
            throw new DBException('Invalid relation name: ' . $name);
        }
        return call_user_func_array($this->relations[$name], [$this]);
    }
    /**
     * @param string $name
     * @return Collection<int,Entity>
     */
    protected function relatedRows(string $name): Collection
    {
        if (!array_key_exists($name, $this->relations)) {
            throw new DBException('Invalid relation name: ' . $name);
        }
        return call_user_func_array($this->relations[$name], [$this]);
    }
    public function toArray(): array
    {
        $temp = $this->data;
        foreach ($this->relations as $name => $relation) {
            try {
                $temp[$name] = call_user_func_array($relation, [ $this, false, true ]);
            } catch (DBException $ignore) {}
        }
        return $temp;
    }
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
