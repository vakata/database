<?php
namespace vakata\database\schema;

use vakata\database\DBException;

class Entity
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

    public function __construct(array $data = [], array $lazy = [], array $relations = [])
    {
        $this->data = $data;
        $this->lazy = $lazy;
        $this->relations = $relations;
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
            return $this->data[$property] = call_user_func($this->lazy[$property]);
        }
        if (array_key_exists($property, $this->cached)) {
            return $this->cached[$property];
        }
        if (isset($this->relations[$property])) {
            $relation = $this->__call($property, []);
            return $relation;
        }
        return null;
    }
    /**
     * @param string $method
     * @param array $args
     * @return iterable<object of Entity>|Entity|null
     */
    public function __call(string $method, array $args): mixed
    {
        if (array_key_exists($method, $this->relations)) {
            $rslt = call_user_func_array($this->relations[$method], $args);
            if (isset($args[0]) && $args[0] === true) {
                return $rslt;
            }
            $this->cached[$method] = $rslt;
        } else {
            throw new DBException('Invalid relation name');
        }
        return $this->cached[$method] ?? null;
    }
    public function __set(string $property, mixed $value): void
    {
        $this->changed[$property] = $value;
    }
}
