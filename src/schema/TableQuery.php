<?php
namespace vakata\database\schema;

use Traversable;
use vakata\collection\Collection;
use vakata\database\DBInterface;
use vakata\database\DBException;
use vakata\database\ResultInterface;

/**
 * A database query class
 */
class TableQuery implements \IteratorAggregate, \ArrayAccess, \Countable
{
    const SEP = '___';

    protected DBInterface $db;
    protected Table $definition;
    protected ?TableQueryIterator $qiterator;

    protected array $where = [];
    protected array $order = [];
    protected array $group = [];
    protected array $having = [];
    protected array $li_of = [0,0];
    protected bool $li_mt = false;
    protected array $fields = [];
    protected array $withr = [];
    protected array $joins = [];
    protected array $pkey = [];
    protected array $aliases = [];
    protected bool $findRelations = false;
    protected bool $manualColumns = false;
    protected bool $aliasColumns = false;

    /**
     * Create an instance
     * @param  DBInterface    $db              the database connection
     * @param  Table|string   $table           the name or definition of the main table in the query
     * @param  bool           $findRelations   should the query builder try to find missing joins
     */
    public function __construct(DBInterface $db, Table|string $table, bool $findRelations = false)
    {
        $this->db = $db;
        $this->findRelations = $findRelations;
        $this->definition = $table instanceof Table ? $table : $this->db->definition((string)$table);
        $primary = $this->definition->getPrimaryKey();
        $columns = $this->definition->getColumns();
        $this->pkey = count($primary) ? $primary : $columns;
        $this->columns($columns);
        $this->manualColumns = false;
    }
    public function __clone()
    {
        $this->qiterator = null;
    }
    /**
     * Get the table definition of the queried table
     * @return Table        the definition
     */
    public function getDefinition() : Table
    {
        return $this->definition;
    }

    protected function getColumn(string $column): array
    {
        $column = explode('.', $column);
        if (count($column) === 1) {
            $column = [ $this->definition->getFullName(), $column[0] ];
            $col = $this->definition->getColumn($column[1]);
            if (!$col) {
                throw new DBException('Invalid column name in main table: ' . $column[1]);
            }
        } elseif (count($column) === 2) {
            if ($column[0] === $this->definition->getName()) {
                $col = $this->definition->getColumn($column[1]);
                if (!$col) {
                    throw new DBException('Invalid column name in main table: ' . $column[1]);
                }
            } else {
                if ($this->definition->hasRelation($column[0])) {
                    $col = $this->definition->getRelation($column[0])?->table?->getColumn($column[1]);
                    if (!$col) {
                        throw new DBException('Invalid column name in related table: ' . $column[1]);
                    }
                } elseif (isset($this->joins[$column[0]])) {
                    $col = $this->joins[$column[0]]->table->getColumn($column[1]);
                    if (!$col) {
                        throw new DBException('Invalid column name in related table: ' . $column[1]);
                    }
                } else {
                    $col = null;
                    foreach ($this->withr as $k => $v) {
                        $temp = array_reverse(array_values(explode(static::SEP, $k)))[0];
                        if ($temp === $column[0]) {
                            $col = $v[0]->table->getColumn($column[1]);
                            if ($col) {
                                break;
                            }
                        }
                    }
                    if (!$col) {
                        $path = [];
                        if ($this->findRelations) {
                            $path = $this->db->findRelation($this->definition->getName(), $column[0]);
                        }
                        if (!count($path)) {
                            throw new DBException('Invalid foreign table / column name: ' . implode(',', $column));
                        }
                        unset($path[0]);
                        $this->with(implode('.', $path), false);
                        $col = $this->db->definition($column[0])->getColumn($column[1]);
                    }
                }
            }
        } else {
            $name = array_pop($column);
            if ($this->definition->hasRelation(implode('.', $column))) {
                $this->with(implode('.', $column), false);
                $col = $this->definition->getRelation(implode('.', $column))?->table?->getColumn($name);
                $column = [ implode('.', $column), $name ];
            } else {
                $this->with(implode('.', $column), false);
                $table = $this->definition;
                $table = array_reduce(
                    $column,
                    function ($carry, $item) use (&$table) {
                        $table = $table->getRelation($item)->table;
                        return $table;
                    }
                );
                $col = $table->getColumn($name);
                $column = [ implode(static::SEP, $column), $name ];
            }
        }
        return [ 'name' => implode('.', $column), 'data' => $col ];
    }
    protected function normalizeValue(TableColumn $col, mixed $value): mixed
    {
        $strict = (int)$this->db->driverOption('strict', 0) > 0;
        if ($value === null && $col->isNullable()) {
            return null;
        }
        switch ($col->getBasicType()) {
            case 'date':
                if (is_string($value)) {
                    $temp = strtotime($value);
                    if (!$temp) {
                        if ($strict) {
                            throw new DBException('Invalid value for date column: ' . $col->getName());
                        }
                        return null;
                    }
                    return date('Y-m-d', $temp);
                }
                if (is_int($value)) {
                    return date('Y-m-d', $value);
                }
                if ($value instanceof \DateTime) {
                    return $value->format('Y-m-d');
                }
                if ($strict) {
                    throw new DBException('Invalid value (unknown data type) for date column: ' . $col->getName());
                }
                return $value;
            case 'datetime':
                if (is_string($value)) {
                    $temp = strtotime($value);
                    if (!$temp) {
                        if ($strict) {
                            throw new DBException('Invalid value for datetime column: ' . $col->getName());
                        }
                        return null;
                    }
                    return date('Y-m-d H:i:s', $temp);
                }
                if (is_int($value)) {
                    return date('Y-m-d H:i:s', $value);
                }
                if ($value instanceof \DateTime) {
                    return $value->format('Y-m-d H:i:s');
                }
                if ($strict) {
                    throw new DBException('Invalid value (unknown data type) for datetime column ' . $col->getName());
                }
                return $value;
            case 'enum':
                $values = $col->getValues();
                if (is_int($value)) {
                    if (!isset($values[$value])) {
                        if ($strict) {
                            throw new DBException('Invalid value (using integer) for enum: ' . $col->getName());
                        }
                        return $value;
                    }
                    return $values[$value];
                }
                if (!in_array($value, $col->getValues())) {
                    if ($strict) {
                        throw new DBException('Invalid value for enum: ' . $col->getName());
                    }
                    return 0;
                }
                return $value;
            case 'int':
                $temp = preg_replace('([^+\-0-9]+)', '', (string)$value);
                return is_string($temp) ? (int)$temp : 0;
            case 'float':
                $temp = preg_replace('([^+\-0-9.]+)', '', str_replace(',', '.', (string)$value));
                return is_string($temp) ? (float)$temp : 0;
            case 'text':
                // check using strlen first, in order to avoid hitting mb_ functions which might be polyfilled
                // because the polyfill is quite slow
                if ($col->hasLength() && strlen($value) > $col->getLength() && mb_strlen($value) > $col->getLength()) {
                    if ($strict) {
                        throw new DBException('Invalid value for text column: ' . $col->getName());
                    }
                    return mb_substr($value, 0, $col->getLength());
                }
                return $value;
            case 'json':
                if (!is_string($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                return $value;
            default: // time, blob, etc
                return $value;
        }
    }

    protected function filterSQL(string $column, mixed $value, bool $negate = false) : array
    {
        $orig = $column;
        list($name, $column) = array_values($this->getColumn($column));
        $sqls = [];
        $pars = [];
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                if ($k === 'not') {
                    $temp = $this->filterSQL($orig, $v, true);
                    $sqls[] = $temp[0];
                    $pars = array_merge($pars, $temp[1]);
                    unset($value[$k]);
                } elseif ($k === 'isnull') {
                    $temp = $this->filterSQL($orig, null);
                    $sqls[] = $temp[0];
                    $pars = array_merge($pars, $temp[1]);
                    unset($value[$k]);
                } elseif (in_array($k, ['like','ilike','contains','icontains','ends','iends'])) {
                    if ($column->getBasicType() !== 'text') {
                        switch ($this->db->driverName()) {
                            case 'oracle':
                                $name = 'CAST(' . $name . ' AS NVARCHAR(500))';
                                break;
                            case 'postgre':
                                $name = $name.'::text';
                                break;
                        }
                    }
                    $mode = array_keys($value)[0];
                    $values = array_values($value)[0];
                    if (!is_array($values)) {
                        $values = [$values];
                    }
                    $sql = [];
                    $par = [];
                    foreach ($values as $v) {
                        $v = str_replace(['%', '_'], ['\\%','\\_'], $v) . '%';
                        if ($mode === 'contains' || $mode === 'icontains') {
                            $v = '%' . $v;
                        }
                        if ($mode === 'ends' || $mode === 'iends') {
                            $v = '%' . rtrim($v, '%');
                        }
                        if ($mode === 'icontains' || $mode === 'ilike' || $mode === 'iends') {
                            $v = mb_strtoupper($v);
                            $name = 'UPPER(' . $name . ')';
                        }
                        $sql[] = $negate ? $name . ' NOT LIKE ?' : $name . ' LIKE ?';
                        $par[] = $v;
                    }
                    if ($negate) {
                        $sqls[] = '(' . implode(' AND ', $sql) . ')';
                        $pars = array_merge($pars, $par);
                    } else {
                        $sqls[] = '(' . implode(' OR ', $sql) . ')';
                        $pars = array_merge($pars, $par);
                    }
                    unset($value[$k]);
                }
            }
            if (!count($value)) {
                return [
                    '(' . implode(' AND ', $sqls) . ')',
                    $pars
                ];
            }
        }
        if (is_null($value)) {
            $sqls[] = $negate ? $name . ' IS NOT NULL' : $name . ' IS NULL';
            return [
                '(' . implode(' AND ', $sqls) . ')',
                $pars
            ];
        }
        if (!is_array($value)) {
            $sqls[] = $negate ? $name . ' <> ?' : $name . ' = ?';
            $pars[] = $this->normalizeValue($column, $value);
            return [
                '(' . implode(' AND ', $sqls) . ')',
                $pars
            ];
        }
        if (isset($value['beg']) && strlen($value['beg']) && (!isset($value['end']) || !strlen($value['end']))) {
            $value = [ 'gte' => $value['beg'] ];
        }
        if (isset($value['end']) && strlen($value['end']) && (!isset($value['beg']) || !strlen($value['beg']))) {
            $value = [ 'lte' => $value['end'] ];
        }
        if (isset($value['beg']) && isset($value['end'])) {
            $sqls[] = $negate ? $name.' NOT BETWEEN ? AND ?' : $name.' BETWEEN ? AND ?';
            $pars[] = $this->normalizeValue($column, $value['beg']);
            $pars[] = $this->normalizeValue($column, $value['end']);
            return [
                '(' . implode(' AND ', $sqls) . ')',
                $pars
            ];
        }
        if (isset($value['gt']) || isset($value['lt']) || isset($value['gte']) || isset($value['lte'])) {
            $sql = [];
            $par = [];
            if (isset($value['gt'])) {
                $sql[] = $name. ' ' . ($negate ? '<=' : '>') . ' ?';
                $par[] = $this->normalizeValue($column, $value['gt']);
            }
            if (isset($value['gte'])) {
                $sql[] = $name. ' ' . ($negate ? '<' : '>=') . ' ?';
                $par[] = $this->normalizeValue($column, $value['gte']);
            }
            if (isset($value['lt'])) {
                $sql[] = $name. ' ' . ($negate ? '>=' : '<') . ' ?';
                $par[] = $this->normalizeValue($column, $value['lt']);
            }
            if (isset($value['lte'])) {
                $sql[] = $name. ' ' . ($negate ? '>' : '<=') . ' ?';
                $par[] = $this->normalizeValue($column, $value['lte']);
            }
            $sqls[] = '(' . implode(' AND ', $sql) . ')';
            $pars = array_merge($pars, $par);
            return [
                '(' . implode(' AND ', $sqls) . ')',
                $pars
            ];
        }

        $value = array_values(array_map(function ($v) use ($column) {
            return $this->normalizeValue($column, $v);
        }, $value));
        if ($this->db->driverName() === 'oracle') {
            $sql = [];
            $par = [];
            for ($i = 0; $i < count($value); $i += 500) {
                $sql[] = $negate ? $name . ' NOT IN (??)' : $name . ' IN (??)';
                $par[] = array_slice($value, $i, 500);
            }
            $sql = '(' . implode($negate ? ' AND ' : ' OR ', $sql) . ')';
            $sqls[] = $sql;
            $pars = array_merge($pars, $par);
            return [
                '(' . implode(' AND ', $sqls) . ')',
                $pars
            ];
        }
        $sqls[] = $negate ? $name . ' NOT IN (??)' : $name . ' IN (??)';
        $pars[] = $value;
        return [
            '(' . implode(' AND ', $sqls) . ')',
            $pars
        ];
    }
    /**
     * Filter the results by a column and a value
     * @param  string $column  the column name to filter by (related columns can be used - for example: author.name)
     * @param  mixed  $value   a required value, array of values or range of values (range example: ['beg'=>1,'end'=>3])
     * @param  bool   $negate  optional boolean indicating that the filter should be negated
     * @return $this
     */
    public function filter(string $column, $value, bool $negate = false) : static
    {
        $sql = $this->filterSQL($column, $value, $negate);
        return strlen($sql[0]) ? $this->where($sql[0], $sql[1]) : $this;
    }
    /**
     * Filter the results matching any of the criteria
     * @param  array $criteria  each row is a column, value and optional negate flag (same as filter method)
     * @return $this
     */
    public function any(array $criteria) : static
    {
        $sql = [];
        $par = [];
        foreach ($criteria as $row) {
            if (isset($row[1])) {
                $temp = $this->filterSQL($row[0], $row[1] ?? null, $row[2] ?? false);
                $sql[] = $temp[0];
                $par = array_merge($par, $temp[1]);
            }
        }
        return $this->where('(' . implode(' OR ', $sql) . ')', $par);
    }
    /**
     * Filter the results matching all of the criteria
     * @param  array $criteria  each row is a column, value and optional negate flag (same as filter method)
     * @return $this
     */
    public function all(array $criteria) : static
    {
        $sql = [];
        $par = [];
        foreach ($criteria as $row) {
            if (isset($row[1])) {
                $temp = $this->filterSQL($row[0], $row[1] ?? null, $row[2] ?? false);
                $sql[] = $temp[0];
                $par = array_merge($par, $temp[1]);
            }
        }
        return $this->where('(' . implode(' AND ', $sql) . ')', $par);
    }
    /**
     * Sort by a column
     * @param  string       $column the column name to sort by (related columns can be used - for example: author.name)
     * @param  bool|boolean $desc   should the sorting be in descending order, defaults to `false`
     * @return $this
     */
    public function sort(string $column, bool $desc = false) : static
    {
        try {
            $this->getColumn($column);
        } catch (DBException $e) {
            throw new DBException('Invalid sort column: ' . $column);
        }
        return $this->order($column . ' ' . ($desc ? 'DESC' : 'ASC'));
    }
    /**
     * Group by a column (or columns)
     * @param  string|array        $column the column name (or names) to group by
     * @return $this
     */
    public function group($column) : static
    {
        if (!is_array($column)) {
            $column = [ $column ];
        }
        foreach ($column as $k => $v) {
            $column[$k] = $this->getColumn($v)['name'];
        }
        return $this->groupBy(implode(', ', $column), []);
    }
    /**
     * Get a part of the data
     * @param  int|integer $page    the page number to get (1-based), defaults to 1
     * @param  int|integer $perPage the number of records per page - defaults to 25
     * @return $this
     */
    public function paginate(int $page = 1, int $perPage = 25) : static
    {
        return $this->limit($perPage, ($page - 1) * $perPage);
    }
    public function __call(string $name, mixed $data): static
    {
        if (strpos($name, 'filterBy') === 0) {
            return $this->filter(strtolower(substr($name, 8)), $data[0]);
        }
        if (strpos($name, 'sortBy') === 0) {
            return $this->sort(strtolower(substr($name, 6)), $data[0]);
        }
        if (strpos($name, 'groupBy') === 0) {
            return $this->group(strtolower(substr($name, 7)));
        }
        return $this;
    }
    /**
     * Remove all filters, sorting, etc
     * @return $this
     */
    public function reset() : static
    {
        $this->where = [];
        $this->joins = [];
        $this->group = [];
        $this->withr = [];
        $this->order = [];
        $this->having = [];
        $this->aliases = [];
        $this->li_of = [0,0];
        $this->li_mt = false;
        $this->qiterator = null;
        return $this;
    }
    /**
     * Apply advanced grouping
     * @param  string $sql    SQL statement to use in the GROUP BY clause
     * @param  array  $params optional params for the statement (defaults to an empty array)
     * @return $this
     */
    public function groupBy(string $sql, array $params = []) : static
    {
        $this->qiterator = null;
        $this->group = [ $sql, $params ];
        return $this;
    }
    /**
     * Join a table to the query (no need to do this for relations defined with foreign keys)
     * @param  Table|string $table     the table to join
     * @param  array        $fields    what to join on (joined_table_field => other_field)
     * @param  string|null  $name      alias for the join, defaults to the table name
     * @param  bool         $multiple  are multiple rows joined (results in a LEFT JOIN), default to true
     * @return $this
     */
    public function join($table, array $fields, ?string $name = null, bool $multiple = true)
    {
        $this->qiterator = null;
        $table = $table instanceof Table ? $table : $this->db->definition((string)$table);
        $name = $name ?? $table->getName();
        if (isset($this->joins[$name]) || $this->definition->hasRelation($name)) {
            throw new DBException('Alias / table name already in use');
        }
        $this->joins[$name] = new TableRelation($this->definition, $name, $table, [], $multiple);
        foreach ($fields as $k => $v) {
            $k = explode('.', $k, 2);
            $k = count($k) == 2 ? $k[1] : $k[0];
            $this->joins[$name]->keymap[$this->getColumn($name . '.' . $k)['name']] = $this->getColumn($v)['name'];
        }
        return $this;
    }
    /**
     * Apply an advanced filter (can be called multiple times)
     * @param  string $sql    SQL statement to be used in the where clause
     * @param  array  $params parameters for the SQL statement (defaults to an empty array)
     * @return $this
     */
    public function where(string $sql, array $params = []) : static
    {
        $this->qiterator = null;
        $this->where[] = [ $sql, $params ];
        return $this;
    }
    /**
     * Apply an advanced HAVING filter (can be called multiple times)
     * @param  string $sql    SQL statement to be used in the HAING clause
     * @param  array  $params parameters for the SQL statement (defaults to an empty array)
     * @return $this
     */
    public function having(string $sql, array $params = []) : static
    {
        $this->qiterator = null;
        $this->having[] = [ $sql, $params ];
        return $this;
    }
    /**
     * Apply advanced sorting
     * @param  string $sql    SQL statement to use in the ORDER clause
     * @param  array  $params optional params for the statement (defaults to an empty array)
     * @return $this
     */
    public function order(string $sql, array $params = []) : static
    {
        $this->qiterator = null;
        $name = null;
        if (!count($params)) {
            $name = preg_replace('(\s+(ASC|DESC)\s*$)i', '', $sql);
            try {
                if ($name === null || !preg_match('(^[a-z0-9_]+$)i', trim($name))) {
                    throw new \Exception();
                }
                $name = $this->getColumn(trim($name))['name'];
                $sql = $name . ' ' . (strpos(strtolower($sql), ' desc') ? 'DESC' : 'ASC');
            } catch (\Exception $e) {
                $name = null;
            }
        }
        $this->order = [ $sql, $params, $name ];
        return $this;
    }
    /**
     * Apply an advanced limit
     * @param  int         $limit  number of rows to return
     * @param  int         $offset number of rows to skip from the beginning (defaults to 0)
     * @return $this
     */
    public function limit(int $limit, int $offset = 0, ?bool $limitOnMainTable = null) : static
    {
        $this->qiterator = null;
        $this->li_of = [ $limit, $offset ];
        if (isset($limitOnMainTable)) {
            $this->li_mt = $limitOnMainTable;
        }
        return $this;
    }
    public function limitOnMainTable(bool $limit): static
    {
        $this->li_mt = $limit;
        return $this;
    }
    /**
     * Get the number of records
     * @return int the total number of records (does not respect pagination)
     */
    public function count() : int
    {
        $aliases = [];
        $aliases_ext = [];
        $getAlias = function ($name) use (&$aliases, &$aliases_ext) {
            // to bypass use: return $name;
            $aliases[$name] = $aliases[$name] ?? 'alias' . static::SEP . count($aliases);
            if (isset($aliases_ext[$name])) {
                unset($aliases_ext[$name]);
            }
            $temp = explode(static::SEP, $name);
            $temp = $temp[count($temp) - 1];
            if (!isset($aliases[$temp])) {
                $aliases_ext[$temp] = $aliases[$name];
            }
            return $aliases[$name];
        };
        $table = $this->definition->getFullName();
        $sql = 'SELECT COUNT(DISTINCT ('.$table.'.'.implode(', '.$table.'.', $this->pkey).')) FROM '.$table.' ';
        $par = [];

        $relations = $this->withr;
        foreach ($relations as $k => $v) {
            $getAlias($k);
        }
        $w = $this->where;
        $h = $this->having;
        $o = $this->order;
        $g = $this->group;
        $j = array_map(function ($v) {
            return clone $v;
        }, $this->joins);

        $used_relations = [];
        foreach ($this->withr as $k => $relation) {
            if ($this->definition->hasRelation($k)) {
                continue;
            }
            foreach ($w as $kk => $v) {
                if (preg_match('(\b'.preg_quote($k . '.'). ')i', $v[0])) {
                    $used_relations[] = $k;
                    $w[$kk][0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $getAlias($k) . '.', $v[0]);
                }
            }
            foreach ($h as $kk => $v) {
                if (preg_match('(\b'.preg_quote($k . '.'). ')i', $v[0])) {
                    $used_relations[] = $k;
                    $h[$kk][0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $getAlias($k) . '.', $v[0]);
                }
            }
            if (isset($g[0]) && preg_match('(\b'.preg_quote($k . '.'). ')i', $g[0])) {
                $used_relations[] = $k;
                $g[0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $getAlias($k) . '.', $g[0]);
            }
            foreach ($j as $kk => $v) {
                foreach ($v->keymap as $kkk => $vv) {
                    if (preg_match('(\b'.preg_quote($k . '.'). ')i', $vv)) {
                        $used_relations[] = $k;
                        $j[$kk]->keymap[$kkk] = preg_replace(
                            '(\b'.preg_quote($k . '.'). ')i',
                            $getAlias($k) . '.',
                            $vv
                        );
                    }
                }
            }
        }
        foreach ($this->definition->getRelations() as $k => $v) {
            foreach ($w as $kk => $vv) {
                if (preg_match('(\b'.preg_quote($k . '.'). ')i', $vv[0])) {
                    $relations[$k] = [ $v, $table ];
                    $used_relations[] = $k;
                    $w[$kk][0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $getAlias($k) . '.', $vv[0]);
                }
            }
            if (isset($o[0]) && preg_match('(\b'.preg_quote($k . '.'). ')i', $o[0])) {
                $relations[$k] = [ $v, $table ];
            }
            foreach ($h as $kk => $vv) {
                if (preg_match('(\b'.preg_quote($k . '.'). ')i', $vv[0])) {
                    $relations[$k] = [ $v, $table ];
                    $used_relations[] = $k;
                    $h[$kk][0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $getAlias($k) . '.', $vv[0]);
                }
            }
            if (isset($g[0]) && preg_match('(\b'.preg_quote($k . '.'). ')i', $g[0])) {
                $relations[$k] = [ $v, $table ];
                $used_relations[] = $k;
                $g[0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $getAlias($k) . '.', $g[0]);
            }
            foreach ($j as $kk => $vv) {
                foreach ($vv->keymap as $kkk => $vvv) {
                    if (preg_match('(\b'.preg_quote($k . '.'). ')i', $vvv)) {
                        $relations[$k] = [ $v, $table ];
                        $used_relations[] = $k;
                        $j[$kk]->keymap[$kkk] =
                            preg_replace('(\b'.preg_quote($k . '.'). ')i', $getAlias($k) . '.', $vvv);
                    }
                }
            }
        }
        foreach ($aliases_ext as $k => $alias) {
            foreach ($w as $kk => $v) {
                if (preg_match('(\b'.preg_quote($k . '.'). ')i', $v[0])) {
                    $w[$kk][0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $alias . '.', $v[0]);
                    $used_relations[] = $k;
                }
            }
            foreach ($h as $kk => $v) {
                if (preg_match('(\b'.preg_quote($k . '.'). ')i', $v[0])) {
                    $h[$kk][0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $alias . '.', $v[0]);
                    $used_relations[] = $k;
                }
            }
            if (isset($o[0]) && preg_match('(\b'.preg_quote($k . '.'). ')i', $o[0])) {
                $used_relations[] = $k;
            }
            if (isset($g[0]) && preg_match('(\b'.preg_quote($k . '.'). ')i', $g[0])) {
                $g[0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $alias . '.', $g[0]);
                $used_relations[] = $k;
            }
            foreach ($j as $kk => $v) {
                foreach ($v->keymap as $kkk => $vv) {
                    if (preg_match('(\b'.preg_quote($k . '.'). ')i', $vv)) {
                        $used_relations[] = $k;
                        $j[$kk]->keymap[$kkk] = preg_replace(
                            '(\b'.preg_quote($k . '.'). ')i',
                            $alias . '.',
                            $vv
                        );
                    }
                }
            }
        }
        if (count($used_relations)) {
            foreach ($relations as $k => $v) {
                $table = $v[1] !== $this->definition->getName() && $v[1] !== $this->definition->getFullName() ?
                    $getAlias($v[1]) : $v[1];
                $v = $v[0];
                if ($v->pivot) {
                    $alias = $getAlias($k.'_pivot');
                    $sql .= 'LEFT JOIN '.$v->pivot->getFullName().' '.$alias.' ON ';
                    $tmp = [];
                    foreach ($v->keymap as $kk => $vv) {
                        $tmp[] = $table.'.'.$kk.' = '.$alias.'.'.$vv.' ';
                    }
                    $sql .= implode(' AND ', $tmp) . ' ';
                    $sql .= 'LEFT JOIN '.$v->table->getFullName().' '.$getAlias($k).' ON ';
                    $tmp = [];
                    foreach ($v->pivot_keymap as $kk => $vv) {
                        $tmp[] = $getAlias($k).'.'.$vv.' = '.$alias.'.'.$kk.' ';
                    }
                    $sql .= implode(' AND ', $tmp) . ' ';
                } else {
                    $alias = $getAlias($k);
                    $sql .= 'LEFT JOIN '.$v->table->getFullName().' '.$alias.' ON ';
                    $tmp = [];
                    foreach ($v->keymap as $kk => $vv) {
                        $tmp[] = $table.'.'.$kk.' = '.$alias.'.'.$vv.' ';
                    }
                    if ($v->sql) {
                        $tmp[] = $v->sql . ' ';
                        $par = array_merge($par, $v->par ?? []);
                    }
                    $sql .= implode(' AND ', $tmp) . ' ';
                }
            }
        }
        $jMany = false;
        foreach ($j as $k => $v) {
            if ($v->many) {
                $jMany = true;
            }
            $sql .= ($v->many ? 'LEFT ' : '' ) . 'JOIN '.$v->table->getFullName().' '.$k.' ON ';
            $tmp = [];
            foreach ($v->keymap as $kk => $vv) {
                $tmp[] = $kk.' = '.$vv;
            }
            $sql .= implode(' AND ', $tmp) . ' ';
        }
        if (!$jMany && !count($used_relations)) {
            $sql = str_replace('COUNT(DISTINCT ', 'COUNT(', $sql);
        }
        if (count($w)) {
            $sql .= 'WHERE ';
            $tmp = [];
            foreach ($w as $v) {
                $tmp[] = '(' . $v[0] . ')';
                $par = array_merge($par, $v[1]);
            }
            $sql .= implode(' AND ', $tmp).' ';
        }
        if (count($g)) {
            $sql .= 'GROUP BY ' . $g[0] . ' ';
            $par = array_merge($par, $g[1]);
        }
        if (count($h)) {
            $sql .= 'HAVING ';
            $tmp = [];
            foreach ($h as $v) {
                $tmp[] = '(' . $v[0] . ')';
                $par = array_merge($par, $v[1]);
            }
            $sql .= implode(' AND ', $tmp).' ';
        }
        return $this->db->one($sql, $par);
    }
    /**
     * Specify which columns to fetch (be default all table columns are fetched)
     * @param  array $fields optional array of columns to select (related columns can be used too)
     * @return $this
     */
    public function columns(array $fields, bool $addPrimary = true, bool $alias = false) : static
    {
        $this->manualColumns = true;
        $this->aliasColumns = $alias;
        $this->qiterator = null;
        foreach ($fields as $k => $v) {
            if (strpos($v, '*') !== false) {
                $temp = explode('.', $v);
                if (count($temp) === 1) {
                    $table = $this->definition->getName();
                    $cols = $this->definition->getColumns();
                } elseif (count($temp) === 2) {
                    $table = $temp[0];
                    if ($this->definition->hasRelation($table)) {
                        $cols = $this->definition->getRelation($table)?->table->getColumns();
                    } elseif (isset($this->joins[$table])) {
                        $cols = $this->joins[$table]->table->getColumns();
                    } else {
                        throw new DBException('Invalid foreign table name: ' . $table);
                    }
                } else {
                    array_pop($temp);
                    $this->with(implode('.', $temp));
                    $table = array_reduce(
                        $temp,
                        function ($carry, $item) use (&$table) {
                            return $table->getRelation($item)->table;
                        }
                    );
                    $cols = $table->getColumns();
                    $table = implode(static::SEP, $temp);
                }
                unset($fields[$k]);
                foreach ($cols as $col) {
                    $fields[] = $table . '.' . $col;
                }
            }
        }
        $primary = $this->definition->getPrimaryKey();
        foreach ($fields as $k => $v) {
            try {
                $fields[$k] = $this->getColumn($v)['name'];
            } catch (DBException $e) {
                $fields[$k] = $v;
            }
        }
        if ($addPrimary) {
            foreach ($primary as $field) {
                $field = $this->getColumn($field)['name'];
                if (!in_array($field, $fields)) {
                    $fields[] = $field;
                }
            }
        }
        $this->fields = $fields;
        return $this;
    }
    /**
     * Get the columns returned by the query
     * @return list<string>        the columns string array
     */
    public function schema(): array
    {
        $f = array_values($this->fields);
        foreach ($this->withr as $name => $relation) {
            if ($relation[2]) {
                if (!$this->manualColumns) {
                    foreach ($relation[0]->table->getColumns() as $column) {
                        $f[] = $name . '.' . $column;
                    }
                } else {
                    foreach ($relation[0]->table->getPrimaryKey() as $column) {
                        $f[] = $name . '.' . $column;
                    }
                }
            }
        }
        $r = [];
        foreach (array_unique($f) as $v) {
            $temp = explode('.', $v);
            if (count($temp) === 1 && $this->definition->getColumn($temp[0])) {
                $r[] = $temp[0];
            }
            if (count($temp) === 2 && $temp[0] === $this->definition->getName() && $this->definition->getColumn($temp[1])) {
                $r[] = $temp[1];
                continue;
            }
            if (count($temp) === 3 && $temp[0] === $this->definition->getSchema() && $temp[1] === $this->definition->getName()) {
                $r[] = $temp[2];
                continue;
            }
            if (count($temp) === 2 && $this->definition->hasRelation($temp[0]) && $this->definition->getRelation($temp[0])?->table->getColumn($temp[1])) {
                $r[] = $temp[0] . '.' . $temp[1];
                continue;
            }
            if (count($temp) === 3 && $temp[0] === $this->definition->getSchema() && $this->definition->hasRelation($temp[1])) {
                $r[] = $temp[1] . '.' . $temp[2];
                continue;
            }
        }
        $r = array_unique($r);
        return $r;
    }
    /**
     * Perform the actual fetch
     * @param  array|null $fields optional array of columns to select (related columns can be used too)
     * @return mixed               the query result as an iterator (with array access)
     */
    public function iterator(?array $fields = null, ?array $collectionKey = null)
    {
        if (isset($this->qiterator)) {
            return $this->qiterator;
        }
        $aliases = [];
        $aliases_ext = [];
        $getAlias = function ($name) use (&$aliases, &$aliases_ext) {
            // to bypass use: return $name;
            $aliases[$name] = $aliases[$name] ?? 'alias' . static::SEP . count($aliases);
            if (isset($aliases_ext[$name])) {
                unset($aliases_ext[$name]);
            }
            $temp = explode(static::SEP, $name);
            $temp = $temp[count($temp) - 1];
            if (!isset($aliases[$temp])) {
                $aliases_ext[$temp] = $aliases[$name];
            }
            return $aliases[$name];
        };
        $table = $this->definition->getName();
        if ($fields !== null) {
            $this->columns($fields);
        }
        $relations = $this->withr;
        foreach ($relations as $k => $v) {
            $getAlias($k);
        }

        $f = $this->fields;
        $w = $this->where;
        $h = $this->having;
        $o = $this->order;
        $g = $this->group;
        $j = array_map(function ($v) {
            return clone $v;
        }, $this->joins);

        $porder = [];
        foreach ($this->definition->getPrimaryKey() as $field) {
            $porder[] = $this->getColumn($field)['name'];
        }

        foreach ($this->withr as $k => $relation) {
            if ($this->definition->hasRelation($k)) {
                continue;
            }
            $temp = [];
            foreach ($f as $kk => $field) {
                if (strpos($field, $k . '.') === 0) {
                    $f[$kk] = str_replace($k . '.', $getAlias($k) . '.', $field);
                    $nk = $this->aliasColumns && is_numeric($kk) ?
                        $getAlias($k . static::SEP . str_replace($k . '.', '', $field)) :
                        $kk;
                    $temp[$nk] = $f[$kk];
                } else {
                    $temp[$kk] = $field;
                }
            }
            $f = $temp;
            foreach ($w as $kk => $v) {
                if (preg_match('(\b'.preg_quote($k . '.'). ')i', $v[0])) {
                    $w[$kk][0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $getAlias($k) . '.', $v[0]);
                }
            }
            foreach ($h as $kk => $v) {
                if (preg_match('(\b'.preg_quote($k . '.'). ')i', $v[0])) {
                    $h[$kk][0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $getAlias($k) . '.', $v[0]);
                }
            }
            if (isset($o[0]) && preg_match('(\b'.preg_quote($k . '.'). ')i', $o[0])) {
                $o[0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $getAlias($k) . '.', $o[0]);
            }
            if (isset($g[0]) && preg_match('(\b'.preg_quote($k . '.'). ')i', $g[0])) {
                $g[0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $getAlias($k) . '.', $g[0]);
            }
            foreach ($j as $kk => $v) {
                foreach ($v->keymap as $kkk => $vv) {
                    if (preg_match('(\b'.preg_quote($k . '.'). ')i', $vv)) {
                        $j[$kk]->keymap[$kkk] = preg_replace(
                            '(\b'.preg_quote($k . '.'). ')i',
                            $getAlias($k) . '.',
                            $vv
                        );
                    }
                }
            }
        }
        foreach ($this->definition->getRelations() as $k => $relation) {
            $temp = [];
            foreach ($f as $kk => $field) {
                if (strpos($field, $k . '.') === 0) {
                    $relations[$k] = [ $relation, $table ];
                    $f[$kk] = str_replace($k . '.', $getAlias($k) . '.', $field);
                    $nk = $this->aliasColumns && is_numeric($kk) ?
                        $getAlias($k . static::SEP . str_replace($k . '.', '', $field)) :
                        $kk;
                    $temp[$nk] = $f[$kk];
                } else {
                    $temp[$kk] = $field;
                }
            }
            $f = $temp;
            foreach ($w as $kk => $v) {
                if (preg_match('(\b'.preg_quote($k . '.'). ')i', $v[0])) {
                    $relations[$k] = [ $relation, $table ];
                    $w[$kk][0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $getAlias($k) . '.', $v[0]);
                }
            }
            foreach ($h as $kk => $v) {
                if (preg_match('(\b'.preg_quote($k . '.'). ')i', $v[0])) {
                    $relations[$k] = [ $relation, $table ];
                    $h[$kk][0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $getAlias($k) . '.', $v[0]);
                }
            }
            if (isset($o[0]) && preg_match('(\b'.preg_quote($k . '.'). ')i', $o[0])) {
                $relations[$k] = [ $relation, $table ];
                $o[0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $getAlias($k) . '.', $o[0]);
            }
            if (isset($g[0]) && preg_match('(\b'.preg_quote($k . '.'). ')i', $g[0])) {
                $relations[$k] = [ $relation, $table ];
                $g[0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $getAlias($k) . '.', $g[0]);
            }
            foreach ($j as $kk => $v) {
                foreach ($v->keymap as $kkk => $vv) {
                    if (preg_match('(\b'.preg_quote($k . '.'). ')i', $vv)) {
                        $relations[$k] = [ $relation, $table ];
                        $j[$kk]->keymap[$kkk] = preg_replace(
                            '(\b'.preg_quote($k . '.'). ')i',
                            $getAlias($k) . '.',
                            $vv
                        );
                    }
                }
            }
        }
        foreach ($aliases_ext as $k => $alias) {
            $temp = [];
            foreach ($f as $kk => $field) {
                if (strpos($field, $k . '.') === 0) {
                    $f[$kk] = str_replace($k . '.', $alias . '.', $field);
                    $nk = $this->aliasColumns && is_numeric($kk) ?
                        $getAlias($k . static::SEP . str_replace($k . '.', '', $field)) :
                        $kk;
                    $temp[$nk] = $f[$kk];
                } else {
                    $temp[$kk] = $field;
                }
            }
            $f = $temp;
            foreach ($w as $kk => $v) {
                if (preg_match('(\b'.preg_quote($k . '.'). ')i', $v[0])) {
                    $w[$kk][0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $alias . '.', $v[0]);
                }
            }
            foreach ($h as $kk => $v) {
                if (preg_match('(\b'.preg_quote($k . '.'). ')i', $v[0])) {
                    $h[$kk][0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $alias . '.', $v[0]);
                }
            }
            if (isset($o[0]) && preg_match('(\b'.preg_quote($k . '.'). ')i', $o[0])) {
                $o[0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $alias . '.', $o[0]);
            }
            if (isset($g[0]) && preg_match('(\b'.preg_quote($k . '.'). ')i', $g[0])) {
                $g[0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $alias . '.', $g[0]);
            }
            foreach ($j as $kk => $v) {
                foreach ($v->keymap as $kkk => $vv) {
                    if (preg_match('(\b'.preg_quote($k . '.'). ')i', $vv)) {
                        $j[$kk]->keymap[$kkk] = preg_replace(
                            '(\b'.preg_quote($k . '.'). ')i',
                            $alias . '.',
                            $vv
                        );
                    }
                }
            }
        }
        foreach ($this->withr as $name => $relation) {
            if ($relation[2]) {
                if (!$this->manualColumns) {
                    foreach ($relation[0]->table->getColumns() as $column) {
                        if (!in_array($getAlias($name) . '.' . $column, $f)) {
                            $f[$getAlias($name . static::SEP . $column)] = $getAlias($name) . '.' . $column;
                        }
                    }
                } else {
                    foreach ($relation[0]->table->getPrimaryKey() as $column) {
                        if (!in_array($getAlias($name) . '.' . $column, $f)) {
                            $f[$getAlias($name . static::SEP . $column)] = $getAlias($name) . '.' . $column;
                        }
                    }
                }
            }
        }
        $select = [];
        foreach ($f as $k => $field) {
            $select[] = $field . (!is_numeric($k) ? ' ' . $k : '');
        }
        $sql = 'SELECT '.implode(', ', $select).' FROM '.$this->definition->getFullName().' ';
        $par = [];
        $many = false;
        foreach ($relations as $relation => $v) {
            $table = $v[1] !== $this->definition->getName() && $v[1] !== $this->definition->getFullName() ?
                $getAlias($v[1]) : $v[1];
            $v = $v[0];
            if ($v->many || $v->pivot) {
                $many = true;
            }
            if ($v->pivot) {
                $alias = $getAlias($relation.'_pivot');
                $sql .= 'LEFT JOIN '.$v->pivot->getFullName().' '.$alias.' ON ';
                $tmp = [];
                foreach ($v->keymap as $kk => $vv) {
                    $tmp[] = $table.'.'.$kk.' = '.$alias.'.'.$vv.' ';
                }
                $sql .= implode(' AND ', $tmp) . ' ';
                $sql .= 'LEFT JOIN '.$v->table->getFullName().' '.$getAlias($relation).' ON ';
                $tmp = [];
                foreach ($v->pivot_keymap as $kk => $vv) {
                    $tmp[] = $getAlias($relation).'.'.$vv.' = '.$alias.'.'.$kk.' ';
                }
                $sql .= implode(' AND ', $tmp) . ' ';
            } else {
                $alias = $getAlias($relation);

                $sql .= 'LEFT JOIN '.$v->table->getFullName().' '.$alias.' ON ';
                $tmp = [];
                foreach ($v->keymap as $kk => $vv) {
                    $tmp[] = $table.'.'.$kk.' = '.$alias.'.'.$vv.' ';
                }
                if ($v->sql) {
                    $tmp[] = $v->sql . ' ';
                    $par = array_merge($par, $v->par ?? []);
                }
                $sql .= implode(' AND ', $tmp) . ' ';
            }
        }
        foreach ($j as $k => $v) {
            if ($v->many) {
                $many = true;
            }
            $sql .= ($v->many ? 'LEFT ' : '' ) . 'JOIN '.$v->table->getFullName().' '.$k.' ON ';
            $tmp = [];
            foreach ($v->keymap as $kk => $vv) {
                $tmp[] = $kk.' = '.$vv;
            }
            $sql .= implode(' AND ', $tmp) . ' ';
        }
        if ($many && count($porder) && $this->li_mt) {
            $ids = $this->ids();
            if (count($ids)) {
                if (count($porder) > 1) {
                    $pkw = [];
                    foreach ($porder as $name) {
                        $pkw[] = $name . ' = ?';
                    }
                    $pkw = '(' . implode(' AND ', $pkw) . ')';
                    $pkp = [];
                    foreach ($ids as $id) {
                        foreach ($id as $p) {
                            $pkp[] = $p;
                        }
                    }
                    $w[] = [
                        implode(' OR ', array_fill(0, count($ids), $pkw)),
                        $pkp
                    ];
                } else {
                    $w[] = [ $porder[0] . ' IN ('.implode(',', array_fill(0, count($ids), '?')).')', $ids ];
                }
            } else {
                $w[] = [ '1=0', [] ];
            }
        }
        if (count($w)) {
            $sql .= 'WHERE ';
            $tmp = [];
            foreach ($w as $v) {
                $tmp[] = '(' . $v[0] . ')';
                $par = array_merge($par, $v[1]);
            }
            $sql .= implode(' AND ', $tmp).' ';
        }
        if (count($g)) {
            $sql .= 'GROUP BY ' . $g[0] . ' ';
            $par = array_merge($par, $g[1]);
        }
        if (count($h)) {
            $sql .= 'HAVING ';
            $tmp = [];
            foreach ($h as $v) {
                $tmp[] = '(' . $v[0] . ')';
                $par = array_merge($par, $v[1]);
            }
            $sql .= implode(' AND ', $tmp).' ';
        }
        $ordered = false;
        if (count($o)) {
            $sql .= 'ORDER BY ' . $o[0] . ' ';
            $par = array_merge($par, $o[1]);
            $ordered = true;
        }
        if (!count($g) && count($porder)) {
            $pdir = (count($o) && strpos($o[0], 'DESC') !== false) ? 'DESC' : 'ASC';
            $porder = array_map(function ($v) use ($pdir) {
                return $v . ' ' . $pdir;
            }, $porder);
            $sql .= ($ordered ? ', ' : 'ORDER BY ') . implode(', ', $porder) . ' ';
            $ordered = true;
        }
        foreach ($this->withr as $k => $v) {
            if (isset($v[3])) {
                $sql .= ($ordered ? ', ' : 'ORDER BY ') . $getAlias($k) . '.' . $v[3] . ' ' . ($v[4] ? 'DESC' : 'ASC');
                $ordered = true;
            }
        }
        if ((!$many || !$this->li_mt || !count($porder)) && $this->li_of[0]) {
            if ($this->db->driverName() === 'oracle') {
                if ((int)$this->db->driverOption('version', 0) >= 12) {
                    $sql .= 'OFFSET ' . $this->li_of[1] . ' ROWS FETCH NEXT ' . $this->li_of[0] . ' ROWS ONLY';
                } else {
                    $f = array_map(function ($v) {
                        $v = explode(' ', trim($v), 2);
                        if (count($v) === 2) {
                            return $v[1];
                        }
                        $v = explode('.', $v[0], 2);
                        return count($v) === 2 ? $v[1] : $v[0];
                    }, $select);
                    $sql = "SELECT " . implode(', ', $f) . "
                            FROM (
                                SELECT tbl__.*, rownum rnum__ FROM (
                                    " . $sql . "
                                ) tbl__
                                WHERE rownum <= " . ($this->li_of[0] + $this->li_of[1]) . "
                            ) WHERE rnum__ > " . $this->li_of[1];
                }
            } else {
                $sql .= 'LIMIT ' . $this->li_of[0] . ' OFFSET ' . $this->li_of[1];
            }
        }
        return $this->qiterator = new TableQueryIterator(
            $this->db->get($sql, $par, null, false, false, true),
            $collectionKey ?? $this->pkey,
            $this->withr,
            $aliases
        );
    }
    /**
     * Perform the actual fetch
     * @param  array|null $fields optional array of columns to select (related columns can be used too)
     * @return array               the query result as an array
     */
    public function select(?array $fields = null, ?array $collectionKey = null) : array
    {
        return iterator_to_array($this->iterator($fields, $collectionKey));
    }
    /**
     * Insert a new row in the table
     * @param  array   $data   key value pairs, where each key is the column name and the value is the value to insert
     * @return array           the inserted ID where keys are column names and values are column values
     */
    public function insert(array $data) : array
    {
        $table = $this->definition->getFullName();
        $columns = $this->definition->getFullColumns();
        $insert = [];
        foreach ($data as $column => $value) {
            if (isset($columns[$column])) {
                $insert[$column] = $this->normalizeValue($columns[$column], $value);
            }
        }
        if (!count($insert)) {
            throw new DBException('No valid columns to insert');
        }
        $sql = 'INSERT INTO '.$table.' ('.implode(', ', array_keys($insert)).') VALUES (??)';
        $par = [$insert];
        $primary = $this->definition->getPrimaryKey();
        if (!count($primary)) {
            $this->db->query($sql, $par);
            return [];
        }
        if ($this->db->driverName() === 'oracle') {
            $ret = [];
            foreach ($primary as $k) {
                $ret[$k] = str_repeat(' ', 255);
                $par[] = &$ret[$k];
            }
            $sql .= ' RETURNING ' . implode(',', $primary) .
                ' INTO ' . implode(',', array_fill(0, count($primary), '?'));
            $this->db->query($sql, $par);
            return $ret;
        } elseif ($this->db->driverName() === 'postgre') {
            $sql .= ' RETURNING ' . implode(',', $primary);
            return $this->db->one($sql, $par, false);
        } else {
            $ret = [];
            $ins = $this->db->query($sql, $par)->insertID();
            foreach ($primary as $k) {
                $ret[$k] = $data[$k] ?? $ins;
            }
            return $ret;
        }
    }
    /**
     * Update the filtered rows with new data
     * @param  array  $data key value pairs, where each key is the column name and the value is the value to insert
     * @return int          the number of affected rows
     */
    public function update(array $data) : int
    {
        $table = $this->definition->getName();
        $columns = $this->definition->getFullColumns();
        $update = [];
        foreach ($data as $column => $value) {
            if (isset($columns[$column])) {
                $update[$column] = $this->normalizeValue($columns[$column], $value);
            }
        }
        if (!count($update)) {
            throw new DBException('No valid columns to update');
        }
        $sql = 'UPDATE '.$table.' SET ';
        $par = [];
        $sql .= implode(', ', array_map(function ($v) {
            return $v . ' = ?';
        }, array_keys($update))) . ' ';
        $par = array_merge($par, array_values($update));
        if (count($this->where)) {
            $sql .= 'WHERE ';
            $tmp = [];
            foreach ($this->where as $v) {
                $tmp[] = $v[0];
                $par = array_merge($par, $v[1]);
            }
            $sql .= implode(' AND ', $tmp) . ' ';
        }
        if (count($this->order)) {
            $sql .= $this->order[0];
            $par = array_merge($par, $this->order[1]);
        }
        return $this->db->query($sql, $par)->affected();
    }
    /**
     * Delete the filtered rows from the DB
     * @return int the number of deleted rows
     */
    public function delete() : int
    {
        $table = $this->definition->getFullName();
        $sql = 'DELETE FROM '.$table.' ';
        $par = [];
        if (count($this->where)) {
            $sql .= 'WHERE ';
            $tmp = [];
            foreach ($this->where as $v) {
                $tmp[] = $v[0];
                $par = array_merge($par, $v[1]);
            }
            $sql .= implode(' AND ', $tmp) . ' ';
        }
        if (count($this->order)) {
            $sql .= $this->order[0];
            $par = array_merge($par, $this->order[1]);
        }
        return $this->db->query($sql, $par)->affected();
    }
    /**
     * Solve the n+1 queries problem by prefetching a relation by name
     * @param  string $relation the relation name to fetch along with the data
     * @return $this
     */
    public function with(string $relation, bool $select = true, ?string $order = null, bool $desc = false) : static
    {
        $this->qiterator = null;
        $table = $this->definition;
        if ($table->hasRelation($relation)) {
            $temp = $table->getRelation($relation);
            $this->withr[$relation] = [
                $temp,
                $table->getName(),
                $select || ($this->withr[$relation][2] ?? false),
                $order,
                $desc
            ];
        } else {
            $parts = explode('.', $relation);
            try {
                $name = array_reduce(
                    $parts,
                    function ($carry, $item) use (&$table, $select) {
                        if (!$table->hasRelation($item)) {
                            throw new DBException('Invalid relation name: '.$table->getName().' -> ' . $item);
                        }
                        $relation = $table->getRelation($item);
                        if (!$relation) {
                            throw new DBException('Invalid relation name: '.$table->getName().' -> ' . $item);
                        }
                        $name = $carry ? $carry . static::SEP . $item : $item;
                        $this->withr[$name] = [
                            $relation,
                            $carry ?? $table->getName(),
                            $select || ($this->withr[$name][2] ?? false)
                        ];
                        $table = $relation->table;
                        return $name;
                    }
                );
            } catch (DBException $e) {
                $path = [];
                if (count($parts) === 1 && $this->findRelations) {
                    $path = $this->db->findRelation($this->definition->getName(), $relation);
                }
                if (!count($path)) {
                    throw $e;
                }
                unset($path[0]);
                return $this->with(implode('.', $path), $select, $order, $desc);
            }
            $this->withr[$name][3] = $order;
            $this->withr[$name][4] = $desc;
        }
        return $this;
    }

    public function getIterator(): Traversable
    {
        return $this->iterator();
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->iterator()->offsetGet($offset);
    }
    public function offsetExists(mixed $offset): bool
    {
        return $this->iterator()->offsetExists($offset);
    }
    public function offsetUnset(mixed $offset): void
    {
        $this->iterator()->offsetUnset($offset);
    }
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->iterator()->offsetSet($offset, $value);
    }

    /**
     * @param array|null $fields
     * @return Collection<int,mixed>
     */
    public function collection(?array $fields = null) : Collection
    {
        return new Collection($this->iterator($fields));
    }

    public function ids(): array
    {
        if (count($this->group)) {
            throw new DBException('Can not LIMIT result set by master table when GROUP BY is used');
        }
        if (count($this->order) && !isset($this->order[2])) {
            throw new DBException('Can not LIMIT result set by master table with a complex ORDER BY query');
        }

        $aliases = [];
        $aliases_ext = [];
        $getAlias = function ($name) use (&$aliases, &$aliases_ext) {
            // to bypass use: return $name;
            $aliases[$name] = $aliases[$name] ?? 'alias' . static::SEP . count($aliases);
            if (isset($aliases_ext[$name])) {
                unset($aliases_ext[$name]);
            }
            $temp = explode(static::SEP, $name);
            $temp = $temp[count($temp) - 1];
            if (!isset($aliases[$temp])) {
                $aliases_ext[$temp] = $aliases[$name];
            }
            return $aliases[$name];
        };

        $table = $this->definition->getName();
        $relations = $this->withr;
        foreach ($relations as $k => $v) {
            $getAlias($k);
        }
        $w = $this->where;
        $h = $this->having;
        $o = $this->order;
        $j = array_map(function ($v) {
            return clone $v;
        }, $this->joins);

        foreach ($this->withr as $k => $relation) {
            if ($this->definition->hasRelation($k)) {
                continue;
            }
            foreach ($w as $kk => $v) {
                if (preg_match('(\b'.preg_quote($k . '.'). ')i', $v[0])) {
                    $w[$kk][0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $getAlias($k) . '.', $v[0]);
                }
            }
            foreach ($h as $kk => $v) {
                if (preg_match('(\b'.preg_quote($k . '.'). ')i', $v[0])) {
                    $h[$kk][0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $getAlias($k) . '.', $v[0]);
                }
            }
            if (isset($o[0]) && preg_match('(\b'.preg_quote($k . '.'). ')i', $o[0])) {
                $o[0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $getAlias($k) . '.', $o[0]);
            }
            foreach ($j as $kk => $v) {
                foreach ($v->keymap as $kkk => $vv) {
                    if (preg_match('(\b'.preg_quote($k . '.'). ')i', $vv)) {
                        $j[$kk]->keymap[$kkk] = preg_replace(
                            '(\b'.preg_quote($k . '.'). ')i',
                            $getAlias($k) . '.',
                            $vv
                        );
                    }
                }
            }
        }
        foreach ($this->definition->getRelations() as $k => $v) {
            foreach ($w as $kk => $vv) {
                if (preg_match('(\b'.preg_quote($k . '.'). ')i', $vv[0])) {
                    $relations[$k] = [ $v, $table ];
                    $w[$kk][0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $getAlias($k) . '.', $vv[0]);
                }
            }
            if (isset($o[0]) && preg_match('(\b'.preg_quote($k . '.'). ')i', $o[0])) {
                $relations[$k] = [ $v, $table ];
                $o[0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $getAlias($k) . '.', $o[0]);
                $o[2] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $getAlias($k) . '.', $o[2]);
            }
            foreach ($h as $kk => $vv) {
                if (preg_match('(\b'.preg_quote($k . '.'). ')i', $vv[0])) {
                    $relations[$k] = [ $v, $table ];
                    $h[$kk][0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $getAlias($k) . '.', $vv[0]);
                }
            }
            foreach ($j as $kk => $vv) {
                foreach ($vv->keymap as $kkk => $vvv) {
                    if (preg_match('(\b'.preg_quote($k . '.'). ')i', $vvv)) {
                        $relations[$k] = [ $v, $table ];
                        $j[$kk]->keymap[$kkk] =
                            preg_replace('(\b'.preg_quote($k . '.'). ')i', $getAlias($k) . '.', $vvv);
                    }
                }
            }
        }
        foreach ($aliases_ext as $k => $alias) {
            foreach ($w as $kk => $v) {
                if (preg_match('(\b'.preg_quote($k . '.'). ')i', $v[0])) {
                    $w[$kk][0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $alias . '.', $v[0]);
                }
            }
            foreach ($h as $kk => $v) {
                if (preg_match('(\b'.preg_quote($k . '.'). ')i', $v[0])) {
                    $h[$kk][0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $alias . '.', $v[0]);
                }
            }
            if (isset($o[0]) && preg_match('(\b'.preg_quote($k . '.'). ')i', $o[0])) {
                $o[0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $alias . '.', $o[0]);
            }
            foreach ($j as $kk => $v) {
                foreach ($v->keymap as $kkk => $vv) {
                    if (preg_match('(\b'.preg_quote($k . '.'). ')i', $vv)) {
                        $j[$kk]->keymap[$kkk] = preg_replace(
                            '(\b'.preg_quote($k . '.'). ')i',
                            $alias . '.',
                            $vv
                        );
                    }
                }
            }
        }

        $key = array_map(function ($v) use ($table) {
            return $table . '.' . $v;
        }, $this->pkey);
        $own = false;
        $dir = 'ASC';
        if (count($o)) {
            $dir = strpos($o[0], ' DESC') ? 'DESC' : 'ASC';
            $own = strpos($o[2], $table . '.') === 0;
        }

        $dst = $key;
        if (count($o)) {
            if ($own) {
                // if using own table - do not use max/min in order - that will prevent index usage
                $dst[] = $o[2] . ' orderbyfix___';
            } else {
                $dst[] = 'MAX(' . $o[2] . ') orderbyfix___';
            }
        }
        $dst = array_unique($dst);

        $par = [];
        $sql  = 'SELECT DISTINCT '.implode(', ', $dst).' FROM '.$this->definition->getFullName().' ';
        foreach ($relations as $k => $v) {
            $table = $v[1] !== $this->definition->getName() ? $getAlias($v[1]) : $v[1];
            $v = $v[0];
            if ($v->pivot) {
                $alias = $getAlias($k.'_pivot');
                $sql .= 'LEFT JOIN '.$v->pivot->getFullName().' '.$alias.' ON ';
                $tmp = [];
                foreach ($v->keymap as $kk => $vv) {
                    $tmp[] = $table.'.'.$kk.' = '.$alias.'.'.$vv.' ';
                }
                $sql .= implode(' AND ', $tmp) . ' ';
                $sql .= 'LEFT JOIN '.$v->table->getFullName().' '.$getAlias($k).' ON ';
                $tmp = [];
                foreach ($v->pivot_keymap as $kk => $vv) {
                    $tmp[] = $getAlias($k).'.'.$vv.' = '.$alias.'.'.$kk.' ';
                }
                $sql .= implode(' AND ', $tmp) . ' ';
            } else {
                $alias = $getAlias($k);
                $sql .= 'LEFT JOIN '.$v->table->getFullName().' '.$alias.' ON ';
                $tmp = [];
                foreach ($v->keymap as $kk => $vv) {
                    $tmp[] = $table.'.'.$kk.' = '.$alias.'.'.$vv.' ';
                }
                if ($v->sql) {
                    $tmp[] = $v->sql . ' ';
                    $par = array_merge($par, $v->par ?? []);
                }
                $sql .= implode(' AND ', $tmp) . ' ';
            }
        }
        foreach ($j as $k => $v) {
            $sql .= ($v->many ? 'LEFT ' : '' ) . 'JOIN '.$v->table->getFullName().' '.$k.' ON ';
            $tmp = [];
            foreach ($v->keymap as $kk => $vv) {
                $tmp[] = $kk.' = '.$vv;
            }
            $sql .= implode(' AND ', $tmp) . ' ';
        }
        if (count($w)) {
            $sql .= 'WHERE ';
            $tmp = [];
            foreach ($w as $v) {
                $tmp[] = '(' . $v[0] . ')';
                $par = array_merge($par, $v[1]);
            }
            $sql .= implode(' AND ', $tmp).' ';
        }
        if (!$own) {
            $sql .= 'GROUP BY ' . implode(', ', $key) . ' ';
        }
        if (count($h)) {
            $sql .= 'HAVING ';
            $tmp = [];
            foreach ($h as $v) {
                $tmp[] = '(' . $v[0] . ')';
                $par = array_merge($par, $v[1]);
            }
            $sql .= implode(' AND ', $tmp).' ';
        }
        if (count($o)) {
            $sql .= 'ORDER BY ';
            if ($own) {
                $sql .= $o[2] . ' ' . $dir;
            } else {
                $sql .= 'MAX('.$o[2].') ' . $dir;
            }
        }
        $porder = [];
        $pdir = (count($o) && strpos($o[0], 'DESC') !== false) ? 'DESC' : 'ASC';
        foreach ($this->definition->getPrimaryKey() as $field) {
            $porder[] = $this->getColumn($field)['name'] . ' ' . $pdir;
        }
        if (count($porder)) {
            $sql .= (count($o) ? ', ' : 'ORDER BY ') . implode(', ', $porder) . ' ';
        }

        if ($this->li_of[0]) {
            if ($this->db->driverName() === 'oracle') {
                if ((int)$this->db->driverOption('version', 12) >= 12) {
                    $sql .= 'OFFSET ' . $this->li_of[1] . ' ROWS FETCH NEXT ' . $this->li_of[0] . ' ROWS ONLY';
                } else {
                    $sql = "SELECT " . implode(', ', $dst) . "
                            FROM (
                                SELECT tbl__.*, rownum rnum__ FROM (
                                    " . $sql . "
                                ) tbl__
                                WHERE rownum <= " . ($this->li_of[0] + $this->li_of[1]) . "
                            ) WHERE rnum__ > " . $this->li_of[1];
                }
            } else {
                $sql .= 'LIMIT ' . $this->li_of[0] . ' OFFSET ' . $this->li_of[1];
            }
        }
        return array_map(function ($v) {
            if (array_key_exists('orderbyfix___', $v)) {
                unset($v['orderbyfix___']);
            }
            return count($v) === 1 ? array_values($v)[0] : $v;
        }, $this->db->all($sql, $par, null, false, false));
    }
    public function find(mixed $primary): mixed
    {
        $columns = $this->definition->getPrimaryKey();
        if (!count($columns)) {
            throw new DBException('Missing primary key');
        }
        if (!is_array($primary)) {
            $temp = [];
            $temp[$columns[0]] = $primary;
            $primary = $temp;
        }
        foreach ($columns as $k) {
            if (!isset($primary[$k])) {
                throw new DBException('Missing primary key component');
            }
            $this->filter($k, $primary[$k]);
        }
        return $this->iterator()[0] ?? null;
    }
}

