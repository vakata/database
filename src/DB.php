<?php

namespace vakata\database;

use \vakata\collection\Collection;
use \vakata\database\schema\Table;
use \vakata\database\schema\TableQuery;
use \vakata\database\schema\TableRelation;

/**
 * A database abstraction with support for various drivers (mySQL, postgre, oracle, msSQL, sphinx, and even PDO).
 */
class DB implements DBInterface
{
    /**
     * @var DriverInterface
     */
    protected $driver;
    /**
     * @var Table[]
     */
    protected $tables = [];

    /**
     * Create an instance.
     *
     * @param DriverInterface|string $driver a driver instance or a connection string
     */
    public function __construct($driver) {
        $this->driver = $driver instanceof DriverInterface ? $driver : static::getDriver($driver);
    }
    /**
     * Create a driver instance from a connection string
     * @param string $connectionString the connection string
     * @return DriverInterface
     */
    public static function getDriver(string $connectionString)
    {
        $connection = [
            'orig' => $connectionString,
            'type' => null,
            'user' => null,
            'pass' => null,
            'host' => null,
            'port' => null,
            'name' => null,
            'opts' => []
        ];
        $aliases = [
            'mysqli' => 'mysql',
            'pg' => 'postgre',
            'oci' => 'oracle',
            'firebird' => 'ibase'
        ];
        $connectionString = array_pad(explode('://', $connectionString, 2), 2, '');
        $connection['type'] = $connectionString[0];
        $connectionString = $connectionString[1];
        if (strpos($connectionString, '@') !== false) {
            $connectionString = array_pad(explode('@', $connectionString, 2), 2, '');
            list($connection['user'], $connection['pass']) = array_pad(explode(':', $connectionString[0], 2), 2, '');
            $connectionString = $connectionString[1];
        }
        $connectionString = array_pad(explode('/', $connectionString, 2), 2, '');
        list($connection['host'], $connection['port']) = array_pad(explode(':', $connectionString[0], 2), 2, null);
        $connectionString = $connectionString[1];
        if ($pos = strrpos($connectionString, '?')) {
            $opt = substr($connectionString, $pos + 1);
            parse_str($opt, $connection['opts']);
            if ($connection['opts'] && count($connection['opts'])) {
                $connectionString = substr($connectionString, 0, $pos);
            } else {
                $connection['opts'] = [];
            }
        }
        $connection['name'] = $connectionString;
        $connection['type'] = isset($aliases[$connection['type']]) ?
            $aliases[$connection['type']] :
            $connection['type'];
        $tmp = '\\vakata\\database\\driver\\'.strtolower($connection['type']).'\\Driver';
        return new $tmp($connection);
    }
    /**
     * Prepare a statement.
     * Use only if you need a single query to be performed multiple times with different parameters.
     *
     * @param string $sql the query to prepare - use `?` for arguments
     * @return StatementInterface the prepared statement
     */
    public function prepare(string $sql) : StatementInterface
    {
        return $this->driver->prepare($sql);
    }
    /**
     * Test the connection
     *
     * @return bool
     */
    public function test() : bool
    {
        return $this->driver->test();
    }
    protected function expand(string $sql, $par = null) : array
    {
        $new = '';
        $par = array_values($par);
        if (substr_count($sql, '?') === 2 && !is_array($par[0])) {
            $par = [ $par ];
        }
        $parts = explode('??', $sql);
        $index = 0;
        foreach ($parts as $part) {
            $tmp = explode('?', $part);
            $new .= $part;
            $index += count($tmp) - 1;
            if (isset($par[$index])) {
                if (!is_array($par[$index])) {
                    $par[$index] = [ $par[$index] ];
                }
                $params = $par[$index];
                array_splice($par, $index, 1, $params);
                $index += count($params);
                $new .= implode(',', array_fill(0, count($params), '?'));
            }
        }
        return [ $new, $par ];
    }
    /**
     * Run a query (prepare & execute).
     * @param string      $sql  SQL query
     * @param array|null  $par parameters (optional)
     * @return ResultInterface the result of the execution
     */
    public function query(string $sql, $par = null) : ResultInterface
    {
        $par = isset($par) ? (is_array($par) ? $par : [$par]) : [];
        if (strpos($sql, '??') && count($par)) {
            list($sql, $par) = $this->expand($sql, $par);
        }
        return $this->driver->prepare($sql)->execute($par);
    }
    /**
     * Run a SELECT query and get an array-like result.
     * When using `get` the data is kept in the database client and fetched as needed (not in PHP memory as with `all`)
     *
     * @param string   $sql      SQL query
     * @param array    $par      parameters
     * @param string   $key      column name to use as the array index
     * @param bool     $skip     do not include the column used as index in the value (defaults to `false`)
     * @param bool     $opti     if a single column is returned - do not use an array wrapper (defaults to `true`)
     *
     * @return Collection the result of the execution
     */
    public function get(string $sql, $par = null, string $key = null, bool $skip = false, bool $opti = true): Collection
    {
        $coll = Collection::from($this->query($sql, $par));
        if (($keys = $this->driver->option('mode')) && in_array($keys, ['strtoupper', 'strtolower'])) {
            $coll->map(function ($v) use ($keys) {
                $new = [];
                foreach ($v as $k => $vv) {
                    $new[call_user_func($keys, $k)] = $vv;
                }
                return $new;
            });
        }
        if ($key !== null) {
            $coll->mapKey(function ($v) use ($key) { return $v[$key]; });
        }
        if ($skip) {
            $coll->map(function ($v) use ($key) { unset($v[$key]); return $v; });
        }
        if ($opti) {
            $coll->map(function ($v) { return count($v) === 1 ? current($v) : $v; });
        }
        return $coll;
    }
    /**
     * Run a SELECT query and get a single row
     * @param string   $sql      SQL query
     * @param array    $par      parameters
     * @param bool     $opti     if a single column is returned - do not use an array wrapper (defaults to `true`)
     * @return Collection the result of the execution
     */
    public function one(string $sql, $par = null, bool $opti = true)
    {
        return $this->get($sql, $par, null, false, $opti)->value();
    }
    /**
     * Run a SELECT query and get an array
     * @param string   $sql      SQL query
     * @param array    $par      parameters
     * @param string   $key      column name to use as the array index
     * @param bool     $skip     do not include the column used as index in the value (defaults to `false`)
     * @param bool     $opti     if a single column is returned - do not use an array wrapper (defaults to `true`)
     * @return Collection the result of the execution
     */
    public function all(string $sql, $par = null, string $key = null, bool $skip = false, bool $opti = true) : array
    {
        return $this->get($sql, $par, $key, $skip, $opti)->toArray();
    }
    /**
     * Begin a transaction.
     * @return $this
     */
    public function begin() : DBInterface
    {
        if (!$this->driver->begin()) {
            throw new DBException('Could not begin');
        }
        return $this;
    }
    /**
     * Commit a transaction.
     * @return $this
     */
    public function commit() : DBInterface
    {
        if (!$this->driver->commit()) {
            throw new DBException('Could not commit');
        }
        return $this;
    }
    /**
     * Rollback a transaction.
     * @return $this
     */
    public function rollback() : DBInterface
    {
        if (!$this->driver->rollback()) {
            throw new DBException('Could not rollback');
        }
        return $this;
    }
    /**
     * Get the current driver name (`"mysql"`, `"postgre"`, etc).
     * @return string the current driver name
     */
    public function driverName() : string
    {
        return array_reverse(explode('\\', get_class($this->driver)))[1];
    }
    /**
     * Get an option from the driver
     *
     * @param string $key     the option name
     * @param mixed  $default the default value to return if the option key is not defined
     * @return mixed the option value
     */
    public function driverOption(string $key, $default = null)
    {
        return $this->driver->option($key, $default);
    }

    public function definition(string $table, bool $detectRelations = true) : Table
    {
        return isset($this->tables[$table]) ?
            $this->tables[$table] :
            $this->driver->table($table, $detectRelations);
    }
    /**
     * Parse all tables from the database.
     * @return $this
     */
    public function parseSchema()
    {
        $this->tables = $this->driver->tables();
        return $this;
    }
    /**
     * Get the full schema as an array that you can serialize and store
     * @return array
     */
    public function getSchema($asPlainArray = true)
    {
        return !$asPlainArray ? $this->tables : array_map(function ($table) {
            return [
                'name' => $table->getName(),
                'pkey' => $table->getPrimaryKey(),
                'comment' => $table->getComment(),
                'columns' => array_map(function ($column) {
                    return [
                        'name' => $column->getName(),
                        'type' => $column->getType(),
                        'comment' => $column->getComment(),
                        'values' => $column->getValues(),
                        'default' => $column->getDefault(),
                        'nullable' => $column->isNullable()
                    ];
                }, $table->getFullColumns()),
                'relations' => array_map(function ($rel) {
                    $relation = clone $rel;
                    $relation->table = $relation->table->getName();
                    if ($relation->pivot) {
                        $relation->pivot = $relation->pivot->getName();
                    }
                    return (array)$relation;
                }, $table->getRelations())
            ];
        }, $this->tables);
    }
    /**
     * Load the schema data from a schema definition array (obtained from getSchema)
     * @param  array        $data the schema definition
     * @return $this
     */
    public function setSchema(array $data)
    {
        foreach ($data as $tableData) {
            $this->tables[$tableData['name']] = (new Table($tableData['name']))
                        ->setPrimaryKey($tableData['pkey'])
                        ->setComment($tableData['comment'])
                        ->addColumns($tableData['columns']);
        }
        foreach ($data as $tableData) {
            $table = $this->definition($tableData['name']);
            foreach ($tableData['relations'] as $relationName => $relationData) {
                $relationData['table'] = $this->definition($relationData['table']);
                if ($relationData['pivot']) {
                    $relationData['pivot'] = $this->definition($relationData['pivot']);
                }
                $table->addRelation(new TableRelation(
                    $relationData['name'],
                    $relationData['table'],
                    $relationData['keymap'],
                    $relationData['many'],
                    $relationData['pivot'] ?? null,
                    $relationData['pivot_keymap'],
                    $relationData['sql'],
                    $relationData['par']
                ));
            }
        }
        return $this;
    }

    /**
     * Initialize a table query
     * @param string $table the table to query
     * @return TableQuery
     */
    public function table($table)
    {
        return new TableQuery($this, $this->definition($table));
    }
    public function __call($method, $args)
    {
        return $this->table($method);
    }
}