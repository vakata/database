<?php
namespace vakata\database\schema;

use vakata\collection\Collection;
use vakata\database\DBInterface;

/**
 * @template T of Entity
 */
interface MapperInterface
{
    /**
     * @param array<string,mixed> $data
     * @param boolean $empty
     * @return T
     */
    public function entity(array $data, bool $empty = false): Entity;
    /**
     * @param T $entity
     * @return void
     */
    public function save(object $entity, bool $relation = false): void;
    /**
     * @param T $entity
     * @return void
     */
    public function delete(object $entity, bool $relations = false): void;
    /**
     * @param T $entity
     * @return array<string,mixed>
     */
    public function id(Entity $entity): array;
    /**
     * @param T $entity
     * @param array<string,mixed> $data
     * @return void
     */
    public function fromArray(object $entity, array $data): void;
    /**
     * @param T $entity
     * @param array|null $columns
     * @param array|null $relations
     * @param boolean $fetch
     * @return array
     */
    public function toArray(
        object $entity,
        ?array $columns = null,
        ?array $relations = [],
        bool $fetch = false
    ): array;
    /**
     * @param T $entity
     * @return bool
     */
    public function isDirty(object $entity, bool $relations = false): bool;
    /**
     * @param T $entity
     * @return bool
     */
    public function deleted(array|object $entity): bool;
    /**
     * @param T $entity
     * @return bool
     */
    public function exists(array|object $entity): bool;
    /**
     * @return array<int,T>
     */
    public function entities(): array;
    public function table(): string;
}
