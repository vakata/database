<?php

namespace vakata\database\schema;

use \vakata\database\DBException;

/**
 * A table definition
 */
class Table
{
    protected array $data = [];
    /**
     * @var TableRelation[]
     */
    protected array $relations = [];

    /**
     * Create a new instance
     * @param  string      $name the table name
     */
    public function __construct(string $name, string $schema = '')
    {
        $this->data = [
            'name'    => $name,
            'schema'  => $schema,
            'columns' => [],
            'primary' => [],
            'comment' => ''
        ];
    }
    /**
     * Get the table comment
     * @return string  the table comment
     */
    public function getComment(): string
    {
        return $this->data['comment'];
    }
    /**
     * Set the table comment
     * @param  string    $comment     the table comment
     * @return $this
     */
    public function setComment(string $comment): static
    {
        $this->data['comment'] = $comment;
        return $this;
    }
    /**
     * Add a column to the definition
     * @param  string    $column     the column name
     * @param  array     $definition optional array of data associated with the column
     * @return  static
     */
    public function addColumn(string $column, array $definition = []): static
    {
        $this->data['columns'][$column] = TableColumn::fromArray($column, $definition);
        return $this;
    }
    /**
     * Add columns to the definition
     * @param  array      $columns key - value pairs, where each key is a column name and each value - array of info
     * @return  static
     */
    public function addColumns(array $columns): static
    {
        foreach ($columns as $column => $definition) {
            if (is_numeric($column) && is_string($definition)) {
                $this->addColumn($definition, []);
            } else {
                $this->addColumn($column, $definition);
            }
        }
        return $this;
    }
    /**
     * Set the primary key
     * @param  array|string        $column either a single column name or an array of column names
     * @return  static
     */
    public function setPrimaryKey(array|string $column): static
    {
        if (!is_array($column)) {
            $column = [ $column ];
        }
        $this->data['primary'] = array_values($column);
        return $this;
    }
    /**
     * Get the table schema
     * @return string  the table name
     */
    public function getSchema(): string
    {
        return $this->data['schema'];
    }
    /**
     * Get the table name
     * @return string  the table name
     */
    public function getName(): string
    {
        return $this->data['name'];
    }
    /**
     * Get the table name with the schema if available
     * @return string  the table name
     */
    public function getFullName(): string
    {
        return ($this->data['schema'] ? $this->data['schema'] . '.' : '') . $this->data['name'];
    }
    /**
     * Get a column definition
     * @param  string    $column the column name to search for
     * @return ?TableColumn the column details or `null` if the column does not exist
     */
    public function getColumn(string $column): ?TableColumn
    {
        return $this->data['columns'][$column] ?? null;
    }
    /**
     * Get all column names
     * @return array     array of strings, where each element is a column name
     */
    public function getColumns(): array
    {
        return array_keys($this->data['columns']);
    }
    /**
     * Get all column definitions
     * @return array         key - value pairs, where each key is a column name and each value - the column data
     */
    public function getFullColumns(): array
    {
        return $this->data['columns'];
    }
    /**
     * Get the primary key columns
     * @return array        array of column names
     */
    public function getPrimaryKey(): array
    {
        return $this->data['primary'];
    }
    /**
     * Create a relation where each record has zero or one related rows in another table
     * @param  Table             $toTable       the related table definition
     * @param  string|null       $name          the name of the relation (defaults to the related table name)
     * @param  string|array|null $toTableColumn the remote columns pointing to the PK in the current table
     * @param  string|null       $sql           additional where clauses to use, default to null
     * @param  array             $par           parameters for the above statement, defaults to []
     * @return $this
     */
    public function hasOne(
        Table $toTable,
        string $name = null,
        string|array|null $toTableColumn = null,
        string $sql = null,
        array $par = []
    ) : static {
        $columns = $toTable->getColumns();

        $keymap = [];
        if (!isset($toTableColumn)) {
            $toTableColumn = [];
        }
        if (!is_array($toTableColumn)) {
            $toTableColumn = [$toTableColumn];
        }
        foreach ($this->getPrimaryKey() as $k => $pkField) {
            if (isset($toTableColumn[$pkField])) {
                $key = $toTableColumn[$pkField];
            } elseif (isset($toTableColumn[$k])) {
                $key = $toTableColumn[$k];
            } else {
                $key = $this->getName().'_'.$pkField;
            }
            if (!in_array($key, $columns)) {
                throw new DBException('Missing foreign key mapping');
            }
            $keymap[$pkField] = $key;
        }

        if (!isset($name)) {
            $name = $toTable->getName() . '_' . implode('_', array_keys($keymap));
        }
        $this->addRelation(new TableRelation(
            $name,
            $toTable,
            $keymap,
            false,
            null,
            null,
            $sql,
            $par
        ));
        return $this;
    }
    /**
     * Create a relation where each record has zero, one or more related rows in another table
     * @param  Table   $toTable       the related table definition
     * @param  string|null       $name          the name of the relation (defaults to the related table name)
     * @param  string|array|null $toTableColumn the remote columns pointing to the PK in the current table
     * @param  string|null       $sql           additional where clauses to use, default to null
     * @param  array             $par           parameters for the above statement, defaults to []
     * @return $this
     */
    public function hasMany(
        Table $toTable,
        string $name = null,
        string|array|null $toTableColumn = null,
        ?string $sql = null,
        array $par = []
    ): static {
        $columns = $toTable->getColumns();

        $keymap = [];
        if (!isset($toTableColumn)) {
            $toTableColumn = [];
        }
        if (!is_array($toTableColumn)) {
            $toTableColumn = [$toTableColumn];
        }
        foreach ($this->getPrimaryKey() as $k => $pkField) {
            if (isset($toTableColumn[$pkField])) {
                $key = $toTableColumn[$pkField];
            } elseif (isset($toTableColumn[$k])) {
                $key = $toTableColumn[$k];
            } else {
                $key = $this->getName().'_'.$pkField;
            }
            if (!in_array($key, $columns)) {
                throw new DBException('Missing foreign key mapping');
            }
            $keymap[$pkField] = $key;
        }

        if (!isset($name)) {
            $name = $toTable->getName().'_'.implode('_', array_keys($keymap));
        }
        $this->addRelation(new TableRelation(
            $name,
            $toTable,
            $keymap,
            true,
            null,
            null,
            $sql,
            $par
        ));
        return $this;
    }
    /**
     * Create a relation where each record belongs to another row in another table
     * @param  Table   $toTable       the related table definition
     * @param  string|null       $name          the name of the relation (defaults to the related table name)
     * @param  string|array|null $localColumn   the local columns pointing to the PK of the related table
     * @param  string|null       $sql           additional where clauses to use, default to null
     * @param  array             $par           parameters for the above statement, defaults to []
     * @return $this
     */
    public function belongsTo(
        Table $toTable,
        string $name = null,
        string|array|null $localColumn = null,
        ?string $sql = null,
        array $par = []
    ): static {
        $columns = $this->getColumns();

        $keymap = [];
        if (!isset($localColumn)) {
            $localColumn = [];
        }
        if (!is_array($localColumn)) {
            $localColumn = [$localColumn];
        }
        foreach ($toTable->getPrimaryKey() as $k => $pkField) {
            if (isset($localColumn[$pkField])) {
                $key = $localColumn[$pkField];
            } elseif (isset($localColumn[$k])) {
                $key = $localColumn[$k];
            } else {
                $key = $toTable->getName().'_'.$pkField;
            }
            if (!in_array($key, $columns)) {
                throw new DBException('Missing foreign key mapping');
            }
            $keymap[$key] = $pkField;
        }

        if (!isset($name)) {
            $name = $toTable->getName().'_'.implode('_', array_keys($keymap));
        }
        $this->addRelation(new TableRelation(
            $name,
            $toTable,
            $keymap,
            false,
            null,
            null,
            $sql,
            $par
        ));
        return $this;
    }
    /**
     * Create a relation where each record has many linked records in another table but using a liking table
     * @param  Table   $toTable       the related table definition
     * @param  Table   $pivot         the pivot table definition
     * @param  string|null       $name          the name of the relation (defaults to the related table name)
     * @param  string|array|null $toTableColumn the local columns pointing to the pivot table
     * @param  string|array|null $localColumn   the pivot columns pointing to the related table PK
     * @return $this
     */
    public function manyToMany(
        Table $toTable,
        Table $pivot,
        ?string $name = null,
        string|array|null $toTableColumn = null,
        string|array|null $localColumn = null
    ): static {
        $pivotColumns = $pivot->getColumns();

        $keymap = [];
        if (!isset($toTableColumn)) {
            $toTableColumn = [];
        }
        if (!is_array($toTableColumn)) {
            $toTableColumn = [$toTableColumn];
        }
        foreach ($this->getPrimaryKey() as $k => $pkField) {
            if (isset($toTableColumn[$pkField])) {
                $key = $toTableColumn[$pkField];
            } elseif (isset($toTableColumn[$k])) {
                $key = $toTableColumn[$k];
            } else {
                $key = $this->getName().'_'.$pkField;
            }
            if (!in_array($key, $pivotColumns)) {
                throw new DBException('Missing foreign key mapping');
            }
            $keymap[$pkField] = $key;
        }

        $pivotKeymap = [];
        if (!isset($localColumn)) {
            $localColumn = [];
        }
        if (!is_array($localColumn)) {
            $localColumn = [$localColumn];
        }
        foreach ($toTable->getPrimaryKey() as $k => $pkField) {
            if (isset($localColumn[$pkField])) {
                $key = $localColumn[$pkField];
            } elseif (isset($localColumn[$k])) {
                $key = $localColumn[$k];
            } else {
                $key = $toTable->getName().'_'.$pkField;
            }
            if (!in_array($key, $pivotColumns)) {
                throw new DBException('Missing foreign key mapping');
            }
            $pivotKeymap[$key] = $pkField;
        }

        if (!isset($name)) {
            $name = $toTable->getName().'_'.implode('_', array_keys($keymap));
        }
        $this->addRelation(new TableRelation(
            $name,
            $toTable,
            $keymap,
            true,
            $pivot,
            $pivotKeymap
        ));
        return $this;
    }
    /**
     * Create an advanced relation using the internal array format
     * @param  TableRelation     $relation      the relation definition
     * @param  string|null       $name          optional name of the relation (defaults to the related table name)
     * @return $this
     */
    public function addRelation(TableRelation $relation, ?string $name = null): static
    {
        $name = $name ?? $relation->name;
        $relation->name = $name;
        $this->relations[$name] = $relation;
        return $this;
    }
    /**
     * Does the definition have related tables
     * @return boolean
     */
    public function hasRelations(): bool
    {
        return count($this->relations) > 0;
    }
    /**
     * Get all relation definitions
     * @return TableRelation[]       the relation definitions
     */
    public function getRelations(): array
    {
        return $this->relations;
    }
    /**
     * Check if a named relation exists
     * @param  string      $name the name to search for
     * @return boolean           does the relation exist
     */
    public function hasRelation(string $name): bool
    {
        return isset($this->relations[$name]);
    }
    /**
     * Get a relation by name
     * @param  string      $name      the name to search for
     * @return ?TableRelation          the relation definition
     */
    public function getRelation(string $name): ?TableRelation
    {
        return $this->relations[$name] ?? null;
    }
    /**
     * Rename a relation
     * @param  string      $name the name to search for
     * @param  string      $new  the new name for the relation
     * @return ?TableRelation       the relation definition
     */
    public function renameRelation(string $name, string $new): ?TableRelation
    {
        if (!isset($this->relations[$name])) {
            throw new DBException("Relation not found");
        }
        if (isset($this->relations[$new])) {
            throw new DBException("A relation with that name already exists");
        }
        $temp = $this->relations[$name];
        $temp->name = $new;
        $this->relations[$new] = $temp;
        unset($this->relations[$name]);
        return $this->relations[$new] ?? null;
    }
    public function toLowerCase(): static
    {
        $this->data['name'] = strtolower($this->data['name']);
        $temp = [];
        foreach ($this->data['columns'] as $k => $v) {
            $temp[strtolower($k)] = $v;
            $v->setName(strtolower($k));
        }
        $this->data['columns'] = $temp;
        $this->data['primary'] = array_map("strtolower", $this->data['primary']);
        $temp = [];
        foreach ($this->relations as $k => $v) {
            $t = [];
            foreach ($v->keymap as $kk => $vv) {
                $t[strtolower($kk)] = strtolower($vv);
            }
            $v->keymap = $t;
            if ($v->pivot_keymap !== null) {
                $t = [];
                foreach ($v->pivot_keymap as $kk => $vv) {
                    $t[strtolower($kk)] = strtolower($vv);
                }
                $v->pivot_keymap = $t;
            }
            $v->name = strtolower($v->name);
            $temp[strtolower($k)] = $v;
        }
        $this->relations = $temp;
        return $this;
    }
}
