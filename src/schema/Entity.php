<?php
namespace vakata\database\schema;

class Entity
{
    protected Mapper $mapper;
    protected Table $definition;
    protected array $initial = [];
    protected array $changed = [];
    protected array $fetched = [];

    public function __construct(Mapper $mapper, Table $definition, array $data = [])
    {
        $this->mapper = $mapper;
        $this->definition = $definition;
        $this->initial = $data;
    }
    public function __lazyProperty(string $property, mixed $resolve): static
    {
        $this->fetched[$property] = $resolve;
        return $this;
    }
    public function &__get(string $property): mixed
    {
        if (array_key_exists($property, $this->fetched)) {
            if (is_callable($this->fetched[$property])) {
                $this->fetched[$property] = call_user_func($this->fetched[$property]);
            }
            return $this->fetched[$property];
        }
        if (array_key_exists($property, $this->changed)) {
            return $this->changed[$property];
        }
        if (array_key_exists($property, $this->initial)) {
            return $this->initial[$property];
        }
        $null = null;
        return $null;
    }
    public function __set(string $property, mixed $value): void
    {
        $this->changed[$property] = $value;
    }
    public function __call(string $method, array $args): mixed
    {
        if (isset($this->definition->getRelations()[$method])) {
            if (array_key_exists($method, $this->fetched)) {
                return is_callable($this->fetched[$method]) ?
                    $this->fetched[$method] = call_user_func_array($this->fetched[$method], $args) :
                    $this->fetched[$method];
            }
        }
        return null;
    }
    public function definition(): Table
    {
        return $this->definition;
    }
    public function toArray(bool $fetch = false): array
    {
        $data = [];
        foreach ($this->definition->getColumns() as $k) {
            if (array_key_exists($k, $this->fetched)) {
                if ($fetch) {
                    $this->fetched[$k] = call_user_func($this->fetched[$k]);
                }
                if (!is_callable($this->fetched[$k])) {
                    $data[$k] = $this->fetched[$k];
                }
            }
            if (array_key_exists($k, $this->initial)) {
                $data[$k] = $this->initial[$k];
            }
            if (array_key_exists($k, $this->changed)) {
                $data[$k] = $this->changed[$k];
            }
        }
        return $data;
    }
    public function fromArray(array $data): static
    {
        foreach ($this->definition->getColumns() as $k) {
            if (array_key_exists($k, $data)) {
                $this->changed[$k] = $data[$k];
            }
        }
        return $this;
    }
    public function id(): array
    {
        $primary = [];
        foreach ($this->definition->getPrimaryKey() as $k) {
            $primary[$k] = $this->initial[$k] ?? null;
        }
        return $primary;
    }
    public function save(): static
    {
        $this->mapper->save($this);
        return $this->flatten();
    }
    public function delete(): void
    {
        $this->mapper->delete($this);
    }
    public function refresh(): static
    {
        $this->mapper->refresh($this);
        return $this->flatten();
    }
    public function flatten(): static
    {
        $this->initial = $this->toArray();
        $this->changed = [];
        return $this;
    }
}
