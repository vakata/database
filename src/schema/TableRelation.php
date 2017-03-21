<?php
namespace vakata\database\schema;

/**
 * A table definition
 */
class TableRelation
{
    /**
     * @var string
     */
    public $name;
    /**
     * @var Table
     */
    public $table;
    /**
     * @var string[]
     */
    public $keymap;
    /**
     * @var bool
     */
    public $many;
    /**
     * @var Table|null
     */
    public $pivot;
    /**
     * @var string[]
     */
    public $pivot_keymap;
    /**
     * @var string|null
     */
    public $sql;
    /**
     * @var array|null
     */
    public $par;

    /**
     * Create a new instance
     * @param  string      $name   the name of the relation
     * @param  Table       $table  the foreign table definition
     * @param  array       $keymap the keymap (local => foreign)
     * @param  bool        $many   is it a one to many rows relation, defaults to false
     * @param  Table|null  $pivot  the pivot table definition (if exists), defaults to null
     * @param  array|null  $keymap the keymap (local => foreign), defaults to null
     * @param  string|null $sql    additional where clauses to use, default to null
     * @param  array       $par    parameters for the above statement, defaults to null
     */
    public function __construct(
        string $name,
        Table $table,
        array $keymap,
        bool $many = false,
        Table $pivot = null,
        array $pivot_keymap = null,
        string $sql = null,
        array $par = null
    ) {
        $this->name = $name;
        $this->table = $table;
        $this->keymap = $keymap;
        $this->many = $many;
        $this->pivot = $pivot;
        $this->pivot_keymap = $pivot_keymap;
        $this->sql = $sql;
        $this->par = $par;
    }
}
