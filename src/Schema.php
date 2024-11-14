<?php

namespace vakata\database;

use \vakata\database\schema\Table;
use \vakata\database\schema\TableRelation;

class Schema
{
    /**
     * @var Table[]
     */
    protected array $tables = [];

    /**
     * Create an instance.
     *
     * @param Table[] $tables
     */
    public function __construct(array $tables = [])
    {
        $this->tables = $tables;
    }

    public function hasTable(string $table): bool
    {
        return isset($this->tables[$table]) ||
            isset($this->tables[strtoupper($table)]) ||
            isset($this->tables[strtolower($table)]);
    }
    public function getTable(string $table): Table
    {
        if (!$this->hasTable($table)) {
            throw new DBException('Invalid table name: ' . $table);
        }
        return $this->tables[$table] ??
            $this->tables[strtoupper($table)] ??
            $this->tables[strtolower($table)];
    }
    public function addTable(Table $table, ?string $name = null): static
    {
        $this->tables[$name ?? $table->getName()] = $table;
        return $this;
    }
    public function removeTable(string $table): static
    {
        $tableObject = $this->getTable($table);
        if (!$tableObject->hasRelations()) {
            unset($this->tables[$table]);
            unset($this->tables[strtoupper($table)]);
            unset($this->tables[strtolower($table)]);
        }
        return $this;
    }
    /**
     * Get all tables
     *
     * @return Table[]
     */
    public function getTables(): array
    {
        return $this->tables;
    }
    
    public function toArray(): array
    {
        return array_map(function ($table) {
            return [
                'name' => $table->getName(),
                'schema' => $table->getSchema(),
                'pkey' => $table->getPrimaryKey(),
                'comment' => $table->getComment(),
                'columns' => array_map(function ($column) {
                    return [
                        'name' => $column->getName(),
                        'type' => $column->getType(),
                        'length' => $column->getLength(),
                        'comment' => $column->getComment(),
                        'values' => $column->getValues(),
                        'default' => $column->getDefault(),
                        'nullable' => $column->isNullable()
                    ];
                }, $table->getFullColumns()),
                'relations' => array_map(function ($rel) {
                    $relation = clone $rel;
                    $relation = (array)$relation;
                    $relation['table'] = $rel->table->getName();
                    if ($rel->pivot) {
                        $relation['pivot'] = $rel->pivot->getName();
                    }
                    return $relation;
                }, $table->getRelations())
            ];
        }, $this->tables);
    }
    public function __debugInfo(): array
    {
        return [
            'tables' => $this->toArray()
        ];
    }
}
