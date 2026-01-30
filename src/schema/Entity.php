<?php
namespace vakata\database\schema;

use JsonSerializable;
use vakata\collection\Collection;
use vakata\database\DBException;

/** @package vakata\database\schema */
class Entity implements JsonSerializable
{
    /**
     * @var array<string,mixed>
     */
    protected array $__data = [];
    /**
     * @var array<string,callable>
     */
    protected array $__lazy = [];
    /**
     * @var array<string,mixed>
     */
    protected array $__changed = [];
    /**
     * @var array<string,callable>
     */
    protected array $__relations = [];
    /**
     * @var array<string,mixed>
     */
    protected array $__cached = [];

    /**
     * @param array<string,mixed> $data
     * @param array<string,callable> $lazy
     * @param array<string,callable> $relations
     */
    public function __construct(array $data = [], array $lazy = [], array $relations = [])
    {
        $this->__data = $data;
        $this->__lazy = $lazy;
        $this->__relations = $relations;
        foreach ($data as $k => $v) {
            if (property_exists($this, $k)) {
                $e = $this->__isBackedEnum($k);
                $this->{$k} = $e ? $e::tryFrom($v) : $v;
            }
        }
    }
    /**
     * @param string $property
     * @return class-string<\BackedEnum>
     */
    protected function __isBackedEnum(string $property): ?string
    {
        $pr = new \ReflectionProperty($this, $property);
        $pt = $pr->getType();

        $types = [];
        if ($pt instanceof \ReflectionNamedType) {
            $types[] = $pt;
        }
        if ($pt instanceof \ReflectionUnionType) {
            foreach ($pt->getTypes() as $t) {
                if ($t instanceof \ReflectionNamedType) {
                    $types[] = $t;
                }
            }
        }
        foreach ($types as $t) {
            if ($t->isBuiltin()) {
                continue;
            }
            $n = $t->getName();
            if (!enum_exists($n)) {
                continue;
            }
            if (is_subclass_of($n, \BackedEnum::class)) {
                return $n;
            }
        }
        return null;
    }
    public function __isset(string $property): bool
    {
        if (array_key_exists($property, $this->__changed)) {
            return isset($this->__changed[$property]);
        }
        if (array_key_exists($property, $this->__data)) {
            return isset($this->__data[$property]);
        }
        if (isset($this->__lazy[$property])) {
            $this->__data[$property] = call_user_func($this->__lazy[$property], $this);
            return isset($this->__data[$property]);
        }
        if (array_key_exists($property, $this->__cached)) {
            return isset($this->__cached[$property]);
        }
        if (isset($this->__relations[$property])) {
            $relation = $this->__call($property, []);
            return isset($relation);
        }
        return false;
    }
    public function &__get(string $property): mixed
    {
        if (array_key_exists($property, $this->__changed)) {
            return $this->__changed[$property];
        }
        if (property_exists($this, $property)) {
            return $this->{$property};
        }
        if (array_key_exists($property, $this->__data)) {
            return $this->__data[$property];
        }
        if (isset($this->__lazy[$property])) {
            $this->__data[$property] = call_user_func($this->__lazy[$property], $this);
            return $this->__data[$property];
        }
        if (array_key_exists($property, $this->__cached)) {
            return $this->__cached[$property];
        }
        if (isset($this->__relations[$property])) {
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
        if (array_key_exists($method, $this->__relations)) {
            $rslt = call_user_func($this->__relations[$method], $this, ...$args);
            if (isset($args[0]) && $args[0] === true) {
                return $rslt;
            }
            $this->__cached[$method] = $rslt;
        } else {
            throw new DBException('Invalid relation name: ' . $method);
        }
        return $this->__cached[$method] ?? null;
    }
    public function __set(string $property, mixed $value): void
    {
        if (property_exists($this, $property)) {
            $this->{$property} = $value;
        }
        $this->__changed[$property] = $value;
    }
    protected function relatedQuery(string $name): TableQueryMapped
    {
        if (!array_key_exists($name, $this->__relations)) {
            throw new DBException('Invalid relation name: ' . $name);
        }
        return call_user_func_array($this->__relations[$name], [$this, true]);
    }
    protected function relatedRow(string $name): mixed
    {
        if (!array_key_exists($name, $this->__relations)) {
            throw new DBException('Invalid relation name: ' . $name);
        }
        return call_user_func_array($this->__relations[$name], [$this]);
    }
    /**
     * @param string $name
     * @return Collection<int,Entity>
     */
    protected function relatedRows(string $name): Collection
    {
        if (!array_key_exists($name, $this->__relations)) {
            throw new DBException('Invalid relation name: ' . $name);
        }
        return call_user_func_array($this->__relations[$name], [$this]);
    }
    public function toArray(): array
    {
        $temp = $this->__data;
        foreach ($this->__relations as $name => $relation) {
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
