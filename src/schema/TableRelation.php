<?php
namespace vakata\database\schema;

/**
 * A table definition
 */
class TableRelation
{
    /**
     * @var Table
     */
    public Table $self;
    /**
     * @var string
     */
    public string $name;
    /**
     * @var Table
     */
    public Table $table;
    /**
     * @var array<string,string>
     */
    public array $keymap;
    /**
     * @var bool
     */
    public bool $many;
    /**
     * @var Table|null
     */
    public ?Table $pivot = null;
    /**
     * @var array<string,string>|null
     */
    public ?array $pivot_keymap = null;
    /**
     * @var string|null
     */
    public ?string $sql = null;
    /**
     * @var array|null
     */
    public ?array $par = null;
    /**
     * @var bool
     */
    public bool $cascade = false;

    /**
     * Create a new instance
     * @param  string      $name   the name of the relation
     * @param  Table       $table  the foreign table definition
     * @param  array       $keymap the keymap (local => foreign)
     * @param  bool        $many   is it a one to many rows relation, defaults to false
     * @param  Table|null  $pivot  the pivot table definition (if exists), defaults to null
     * @param  array|null  $pivot_keymap the keymap (local => foreign), defaults to null
     * @param  string|null $sql    additional where clauses to use, default to null
     * @param  array       $par    parameters for the above statement, defaults to null
     */
    public function __construct(
        Table $self,
        string $name,
        Table $table,
        array $keymap,
        bool $many = false,
        ?Table $pivot = null,
        ?array $pivot_keymap = null,
        ?string $sql = null,
        ?array $par = null,
        bool $cascade = false
    ) {
        $this->self = $self;
        $this->name = $name;
        $this->table = $table;
        $this->keymap = $keymap;
        $this->many = $many;
        $this->pivot = $pivot;
        $this->pivot_keymap = $pivot_keymap;
        $this->sql = $sql;
        $this->par = $par;
        $this->cascade = $cascade;
    }
    public function reverse(): ?TableRelation
    {
        foreach ($this->table->getRelations() as $r) {
            if ($this->pivot) {
                if ($r->table === $this->self && $r->pivot === $this->pivot) {
                    if (!array_intersect(array_values($r->keymap), array_values($this->keymap))) {
                        return $r;
                    }
                }
            } else {
                if ($r->table === $this->self) {
                    if (serialize($this->keymap) === serialize(array_flip($r->keymap))) {
                        return $r;
                    }
                }
            }
        }
        return null;
    }
}
