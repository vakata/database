<?php
namespace vakata\database\schema;

use vakata\collection\Collection;
use vakata\database\DBInterface;

/**
 * @template-covariant T of object
 */
interface MapperInterface
{
    /**
     * @param array<string,mixed> $data
     * @param boolean $empty
     * @return T
     */
    public function entity(array $data, bool $empty = false): object;
    /**
     * @param T $entity
     * @return T
     */
    public function save(object $entity): object;
    /**
     * @param T $entity
     * @return void
     */
    public function delete(object $entity): void;
    /**
     * @param T $entity
     * @return array<string,mixed>
     */
    public function id(Entity $entity): array;
    /**
     * @param T $entity
     * @return array<string,mixed>
     */
    public function toArray(Entity $entity): array;
}
