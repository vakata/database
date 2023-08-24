<?php

namespace vakata\database;

use \vakata\collection\Collection;
use \vakata\database\schema\Table;
use \vakata\database\schema\TableQuery;
use \vakata\database\schema\TableQueryMapped;
use \vakata\database\schema\TableRelation;

/**
 * A database abstraction with support for various drivers (mySQL, postgre, oracle, msSQL, sphinx, and even PDO).
 */
class DB implements DBInterface
{
    /**
     * @var DriverInterface
     */
    protected DriverInterface $driver;
    /**
     * @var Table[]
     */
    protected array $tables = [];

    /**
     * Create an instance.
     *
     * @param string $connectionString a driver instance or a connection string
     */
    public function __construct(string $connectionString)
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
            'my'        => 'mysql',
            'mysqli'    => 'mysql',
            'pg'        => 'postgre',
            'oci'       => 'oracle',
            'firebird'  => 'ibase'
        ];
        $temp = parse_url($connectionString);
        if ($temp === false || (isset($temp['query']) && strpos($temp['query'], 'regexparser=1') !== false)) {
            if (!preg_match(
                '(^
                    (?<scheme>.*?)://
                    (?:(?<user>.*?)(?:\:(?<pass>.*))?@)?
                    (?<host>[a-zа-я.\-_0-9=();:]+?) # added =();: for oracle and pdo configs
                    (?:\:(?<port>\d+))?
                    (?<path>/.+?)? # path is optional for oracle and pdo configs
                    (?:\?(?<query>.*))?
                $)xui',
                $connectionString,
                $temp
            )) {
                $temp = explode('://', $connectionString, 2);
                if (!preg_match('(^[a-z0-9_]+$)i', $temp[0])) {
                    throw new DBException('Could not parse connection string');
                }
                $temp = [
                    'scheme' => $temp[0]
                ];
            }
        }
        $connection['type'] = isset($temp['scheme']) && strlen((string)$temp['scheme']) ? $temp['scheme'] : null;
        $connection['user'] = isset($temp['user']) && strlen((string)$temp['user']) ? $temp['user'] : null;
        $connection['pass'] = isset($temp['pass']) && strlen((string)$temp['pass']) ? $temp['pass'] : null;
        $connection['host'] = isset($temp['host']) && strlen((string)$temp['host']) ? $temp['host'] : null;
        $connection['name'] = isset($temp['path']) && strlen((string)$temp['path']) ? trim((string)$temp['path'], '/') : null;
        $connection['port'] = isset($temp['port']) && (int)$temp['port'] ? (int)$temp['port'] : null;
        if (isset($temp['query']) && strlen((string)$temp['query'])) {
            parse_str((string)$temp['query'], $connection['opts']);
        }
        // create the driver
        $connection['type'] = $aliases[$connection['type']] ?? $connection['type'];
        $tmp = '\\vakata\\database\\driver\\'.strtolower((string)$connection['type']).'\\Driver';
        if (!class_exists($tmp)) {
            throw new DBException('Unknown DB backend');
        }
        /* @phpstan-ignore-next-line */
        $this->driver = new $tmp($connection);
    }

    public function driver(): DriverInterface
    {
        return $this->driver;
    }

    /**
     * Prepare a statement.
     * Use only if you need a single query to be performed multiple times with different parameters.
     *
     * @param string $sql the query to prepare - use `?` for arguments
     * @return StatementInterface the prepared statement
     */
    public function prepare(string $sql, ?string $name = null) : StatementInterface
    {
        return $this->driver->prepare($sql, $name);
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
    protected function expand(string $sql, mixed $par = null) : array
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
     * @param string   $sql   SQL query
     * @param mixed    $par   parameters (optional)
     * @param bool     $buff  should the results be buffered (defaults to true)
     * @return ResultInterface the result of the execution
     */
    public function query(string $sql, mixed $par = null, bool $buff = true) : ResultInterface
    {
        $par = isset($par) ? (is_array($par) ? $par : [$par]) : [];
        if (strpos($sql, '??') && count($par)) {
            list($sql, $par) = $this->expand($sql, $par);
        }
        return $this->driver->prepare($sql)->execute($par, $buff);
    }
    /**
     * Run a query.
     * @param string   $sql   SQL query
     * @return mixed the result of the execution
     */
    public function raw(string $sql)
    {
        return $this->driver->raw($sql);
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
     * @param bool     $buff     should the results be buffered (defaults to `false`)
     *
     * @return Collection the result of the execution
     */
    public function get(
        string $sql,
        mixed $par = null,
        string $key = null,
        bool $skip = false,
        bool $opti = true,
        bool $buff = true
    ): Collection {
        $coll = Collection::from($this->query($sql, $par, $buff));
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
            $coll->mapKey(function ($v) use ($key) {
                return $v[$key];
            });
        }
        if ($skip) {
            $coll->map(function ($v) use ($key) {
                unset($v[$key]);
                return $v;
            });
        }
        if ($opti) {
            $coll->map(function ($v) {
                return count($v) === 1 ? current($v) : $v;
            });
        }
        return $coll;
    }
    /**
     * Run a SELECT query and get a single row
     * @param string   $sql      SQL query
     * @param array    $par      parameters
     * @param bool     $opti     if a single column is returned - do not use an array wrapper (defaults to `true`)
     * @return mixed the result of the execution
     */
    public function one(string $sql, mixed $par = null, bool $opti = true): mixed
    {
        return $this->get($sql, $par, null, false, $opti, true)->value();
    }
    /**
     * Run a SELECT query and get an array
     * @param string   $sql      SQL query
     * @param array    $par      parameters
     * @param string   $key      column name to use as the array index
     * @param bool     $skip     do not include the column used as index in the value (defaults to `false`)
     * @param bool     $opti     if a single column is returned - do not use an array wrapper (defaults to `true`)
     * @return array the result of the execution
     */
    public function all(string $sql, mixed $par = null, string $key = null, bool $skip = false, bool $opti = true): array
    {
        return $this->get($sql, $par, $key, $skip, $opti, true)->toArray();
    }
    public function unbuffered(
        string $sql,
        mixed $par = null,
        string $key = null,
        bool $skip = false,
        bool $opti = true
    ) : Collection {
        return $this->get($sql, $par, $key, $skip, $opti, false);
    }
    /**
     * Begin a transaction.
     * @return $this
     */
    public function begin(bool $soft = false) : DBInterface
    {
        if (!$soft && !$this->driver->begin()) {
            throw new DBException('Could not begin');
        }
        if ($soft) {
            $this->driver->softBegin();
        }
        return $this;
    }
    /**
     * Commit a transaction.
     * @return $this
     */
    public function commit(bool $soft = false) : DBInterface
    {
        if (!$soft && !$this->driver->commit()) {
            throw new DBException('Could not commit');
        }
        if ($soft) {
            $this->driver->softCommit();
        }
        return $this;
    }
    /**
     * Rollback a transaction.
     * @return $this
     */
    public function rollback(bool $soft = false) : DBInterface
    {
        if (!$soft && !$this->driver->rollback()) {
            throw new DBException('Could not rollback');
        }
        if ($soft) {
            $this->driver->softRollback();
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
    public function driverOption(string $key, $default = null): mixed
    {
        return $this->driver->option($key, $default);
    }

    public function definition(string $table, bool $detectRelations = true) : Table
    {
        return $this->tables[$table] ??
            $this->tables[strtoupper($table)] ??
            $this->tables[strtolower($table)] ??
            $this->driver->table($table, $detectRelations);
    }

    public function hasSchema(): bool
    {
        return count($this->tables) !== 0;
    }
    /**
     * Parse all tables from the database.
     * @return $this
     */
    public function parseSchema(): static
    {
        $this->tables = $this->driver->tables();
        return $this;
    }
    /**
     * Get the full schema as an array that you can serialize and store
     * @return array
     */
    public function getSchema(bool $asPlainArray = true): array
    {
        return !$asPlainArray ? $this->tables : array_map(function ($table) {
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
    /**
     * Load the schema data from a schema definition array (obtained from getSchema)
     * @param  mixed        $data the schema definition
     * @return $this
     */
    public function setSchema($data): static
    {
        if (!is_array($data)) {
            $this->tables = \unserialize($data);
            return $this;
        }
        foreach ($data as $tableData) {
            $this->tables[$tableData['name']] = (new Table($tableData['name'], $tableData['schema']))
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
                    $relationData['pivot'] instanceof Table ? $relationData['pivot'] : null,
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
    public function table(string $table, bool $findRelations = false): TableQuery
    {
        return new TableQuery($this, $this->definition($table), $findRelations);
    }
    public function tableMapped(string $table, bool $findRelations = false): TableQueryMapped
    {
        return new TableQueryMapped($this, $this->definition($table), $findRelations);
    }
    public function __call(string $method, array $args): TableQuery|TableQueryMapped
    {
        return ($args[0] ?? false) ?
            $this->tableMapped($method, $args[1] ?? false) :
            $this->table($method, $args[1] ?? false);
    }
    public function findRelation(string $start, string $end): array
    {
        /**
         * @var array
         */
        static $schema = [];

        $start = $this->definition($start)->getName();
        $end = $this->definition($end)->getName();

        if (!$this->tables) {
            $this->parseSchema();
        }
        if (!count($schema)) {
            foreach ($this->tables as $table) {
                $name = $table->getName();

                $relations = [];
                foreach ($table->getRelations() as $relation) {
                    $t = $relation->table->getName();
                    $w = 10;
                    if (strpos($name, '_') !== false || strpos($t, '_') !== false) {
                        foreach (explode('_', $name) as $p1) {
                            foreach (explode('_', $t) as $p2) {
                                $w = min($w, levenshtein($p1, $p2));
                            }
                        }
                    }
                    $relations[$t] = $w;
                }
                if (!isset($schema[$name])) {
                    $schema[$name] = [ 'edges' => [] ];
                }
                foreach ($relations as $t => $w) {
                    $schema[$name]['edges'][$t] = $w;
                    if (!isset($schema[$t])) {
                        $schema[$t] = [ 'edges' => [] ];
                    }
                    $schema[$t]['edges'][$name] = $w;
                }
            }
        }
        $graph = $schema;
        foreach ($graph as $k => $v) {
            $graph[$k]['weight'] = 0;
            $graph[$k]['from'] = false;
            $graph[$k]['added'] = false;
        }
        $graph[$start]['weight'] = 0;
        $graph[$start]['from'] = $start;
    
        // go through graph
        $to_add = $start;
        for ($i = 0; $i < count($graph); $i++) {
            $graph[$to_add]['added'] = true;
            foreach ($graph[$to_add]['edges'] as $k => $w) {
                if ($graph[$k]['added']) {
                    continue;
                }
                if ($graph[$k]['from'] === false || $graph[$to_add]['weight'] + $w < $graph[$k]['weight']) {
                    $graph[$k]['from'] = $to_add;
                    $graph[$k]['weight'] = $graph[$to_add]['weight'] + $w;
                }
            }
            $to_add = false;
            $min_weight = 0;
            foreach ($graph as $k => $v) {
                if ($v['added'] || $v['from'] === false) {
                    continue;
                }
                if ($to_add === false || $v['weight'] < $min_weight) {
                    $to_add = $k;
                    $min_weight = $v['weight'];
                }
            }
            if ($to_add === false) { 
                break;
            }
            // also break here if $to_add === $end
            // and set it to true
        }
        if ($graph[$end]['added'] === false) {
            return [];
        }
        $path = array();
        $i = $end;
        while ($i != $start) {
            $path[] = $i;
            $i = $graph[$i]['from'];
        }
        $path[] = $start;
        return array_reverse($path);
    }
}
