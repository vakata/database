<?php

namespace vakata\database;

use \vakata\collection\Collection;
use vakata\database\schema\Entity;
use vakata\database\schema\Mapper;
use vakata\database\schema\MapperInterface;
use \vakata\database\schema\Table;
use \vakata\database\schema\TableQuery;
use \vakata\database\schema\TableQueryMapped;
use \vakata\database\schema\TableRelation;

/**
 * A database abstraction with support for various drivers (mySQL, postgre, oracle, msSQL, sphinx, and even PDO).
 */
class DB implements DBInterface
{
    protected DriverInterface $driver;
    protected ?Schema $schema = null;
    /**
     * @var array<string,MapperInterface>
     */
    protected array $mappers = [];
    /**
     * @var array<string,Entity>
     */
    protected array $deleted = [];

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
        $connection['name'] = isset($temp['path']) && strlen((string)$temp['path']) ?
            trim((string)$temp['path'], '/') : null;
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
        $map = null;
        if (strpos($sql, ':')) {
            $tmp = $this->expandNames($sql);
            $sql = $tmp['sql'];
            $map = $tmp['map'];
        }
        return $this->driver->prepare($sql, $name, $map);
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
    protected function expandNames(string $sql, ?array $par = null) : array
    {
        $map = [];
        $sql = preg_replace_callback(
            '(\:[a-z_][a-z0-9_]+)i',
            function ($matches) use (&$map, $par) {
                $key = substr($matches[0], 1);
                $map[] = $key;
                return isset($par) && isset($par[$key]) && is_array($par[$key]) ? '??' : '?';
            },
            $sql
        );
        return [
            'sql' => $sql,
            'map' => $map
        ];
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
        if (strpos($sql, ':') && count($par)) {
            $tmp = $this->expandNames($sql, $par);
            $sql = $tmp['sql'];
            $ord = [];
            foreach ($tmp['map'] as $key) {
                $ord[] = $par[$key] ?? throw new DBException('Missing param ' . $key);
            }
            $par = $ord;
        }
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
     * @return Collection<array-key,mixed> the result of the execution
     */
    public function get(
        string $sql,
        mixed $par = null,
        ?string $key = null,
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
            $coll->mapKey(function ($v) use ($key): int|string {
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
    public function all(
        string $sql,
        mixed $par = null,
        ?string $key = null,
        bool $skip = false,
        bool $opti = true
    ): array {
        return $this->get($sql, $par, $key, $skip, $opti, true)->toArray();
    }
    /**
     * @param string $sql
     * @param mixed $par
     * @param string|null $key
     * @param boolean $skip
     * @param boolean $opti
     * @return Collection<array-key,mixed>
     */
    public function unbuffered(
        string $sql,
        mixed $par = null,
        ?string $key = null,
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
        return isset($this->schema) ?
            $this->schema->getTable($table) :
            $this->driver->table($table, $detectRelations);
    }

    public function hasSchema(): bool
    {
        return isset($this->schema);
    }
    /**
     * Parse all tables from the database.
     * @return $this
     */
    public function parseSchema(): static
    {
        $this->schema = new Schema($this->driver->tables());
        return $this;
    }
    /**
     * Get the full schema objact that can be serialized and stored
     */
    public function getSchema(): Schema
    {
        if (!isset($this->schema)) {
            throw new DBException('No schema exists');
        }
        return $this->schema;
    }
    /**
     * Load the schema data from an object (obtained from getSchema)
     * @return $this
     */
    public function setSchema(Schema $schema): static
    {
        $this->schema = $schema;
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
    /**
     * @template T of Entity
     * @param class-string<T> $class
     * @return TableQueryMapped<T>
     */
    public function entities(string $class): TableQueryMapped
    {
        $mapper = $this->getMapper($class);
        return $this->tableMapped($mapper->table(), false, $mapper);
    }
    /**
     * @template T of Entity
     * @param class-string<T> $class
     * @return T
     */
    public function entity(string $class): Entity
    {
        /** @phpstan-ignore-next-line */
        return $this->getMapper($class)->entity([], true);
    }
    public function delete(Entity $entity): void
    {
        $this->deleted[spl_object_hash($entity)] = $entity;
    }
    public function save(?Entity $entity = null): void
    {
        if (!isset($entity)) {
            foreach ($this->deleted as $e) {
                foreach ($this->mappers as $mapper) {
                    if ($mapper->exists($e)) {
                        $mapper->delete($e, true);
                    }
                }
            }
            foreach ($this->mappers as $mapper) {
                foreach ($mapper->entities() as $e) {
                    if ($mapper->isDirty($e)) {
                        $mapper->save($e, false);
                    }
                }
            }
            foreach ($this->mappers as $mapper) {
                foreach ($mapper->entities() as $e) {
                    if ($mapper->isDirty($e, true)) {
                        $mapper->save($e, true);
                    }
                }
            }
            return;
        }
        foreach ($this->mappers as $mapper) {
            if ($mapper->exists($entity)) {
                if (isset($this->deleted[spl_object_hash($entity)])) {
                    $mapper->delete($entity, true);
                } else {
                    $mapper->save($entity, true);
                }
            }
        }
    }
    /**
     * @template T of Entity
     * @param class-string<T>|Table|string $table
     * @return ($table is class-string ? MapperInterface<T> : MapperInterface<Entity>)
     */
    public function getMapper(Table|string $table): MapperInterface
    {
        if (is_string($table)) {
            if (isset($this->mappers['::' . $table])) {
                return $this->mappers['::' . $table];
            }
            $table = $this->definition($table);
        }
        if (isset($this->mappers[$table->getFullName()])) {
            return $this->mappers[$table->getFullName()];
        }
        return $this->mappers[$table->getFullName()] = new Mapper($this, $table);
    }
    public function setMapper(Table|string $table, MapperInterface $mapper, ?string $class = null): static
    {
        if (is_string($table)) {
            $table = $this->definition($table);
        }
        $this->mappers[$table->getFullName()] = $mapper;
        if (isset($class)) {
            $this->mappers['::' . $class] = $mapper;
        }
        return $this;
    }
    public function clearMappers(): static
    {
        $this->mappers = [];
        return $this;
    }
    public function tableMapped(
        string $table,
        bool $findRelations = false,
        ?MapperInterface $mapper = null
    ): TableQueryMapped {
        return new TableQueryMapped($this, $this->definition($table), $findRelations, $mapper);
    }
    public function __call(string $method, array $args): TableQuery|TableQueryMapped
    {
        return ($args[0] ?? false) ?
            $this->tableMapped($method, $args[1] ?? false, $args[2] ?? null) :
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

        if (!isset($this->schema)) {
            $this->parseSchema();
        }
        if (!isset($this->schema)) {
            throw new DBException('Could not parse schema');
        }
        if (!count($schema)) {
            foreach ($this->schema->getTables() as $table) {
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

    public function row(string $sql, array $par = []): ?array
    {
        return $this->one($sql, $par, false);
    }
    /**
     * @param string $sql
     * @param array $par
     * @return Collection<int,array<array-key,scalar|null>>
     */
    public function rows(string $sql, array $par = []): Collection
    {
        return $this->get($sql, $par, null, false, false);
    }
    public function col(string $sql, array $par = []): array
    {
        $temp = $this->all($sql, $par, null, false, true);
        foreach ($temp as $k => $v) {
            if (is_array($v)) {
                $temp[$k] = array_values($v)[0];
            }
        }
        return $temp;
    }
    public function val(string $sql, array $par = []): mixed
    {
        $temp = $this->one($sql, $par, true);
        if (is_array($temp)) {
            $temp = array_values($temp)[0];
        }
        return $temp;
    }
}
