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
     * @param Table $definition
     * @param array<string,mixed> $data
     * @param boolean $empty
     * @return T
     */
    public function entity(Table $definition, array $data, bool $empty = false): object;
    public function collection(TableQueryIterator $iterator, Table $definition): Collection;
    public function save(object $entity): object;
    public function delete(object $entity): void;
    public function refresh(object $entity): object;
}
