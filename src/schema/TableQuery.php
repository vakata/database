<?php
namespace vakata\database\schema;

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
    /**
     * @var DBInterface
     */
    protected $db;
    /**
     * @var Table
     */
    protected $definition;
    /**
     * @var TableQueryIterator|null
     */
    protected $qiterator;

    /**
     * @var array
     */
    protected $where = [];
    /**
     * @var array
     */
    protected $order = [];
    /**
     * @var array
     */
    protected $group = [];
    /**
     * @var array
     */
    protected $having = [];
    /**
     * @var int[]
     */
    protected $li_of = [0,0,0];
    /**
     * @var array
     */
    protected $fields = [];
    /**
     * @var array
     */
    protected $withr = [];
    /**
     * @var array
     */
    protected $joins = [];
    /**
     * @var array
     */
    protected $pkey = [];
    /**
     * @var array
     */
    protected $aliases = [];

    /**
     * Create an instance
     * @param  DBInterface    $db         the database connection
     * @param  Table|string   $table      the name or definition of the main table in the query
     */
    public function __construct(DBInterface $db, $table)
    {
        $this->db = $db;
        $this->definition = $table instanceof Table ? $table : $this->db->definition((string)$table);
        $primary = $this->definition->getPrimaryKey();
        $columns = $this->definition->getColumns();
        $this->pkey = count($primary) ? $primary : $columns;
        $this->columns($columns);
    }
    public function __clone()
    {
        $this->reset();
    }
    /**
     * Get the table definition of the queried table
     * @return Table        the definition
     */
    public function getDefinition() : Table
    {
        return $this->definition;
    }

    protected function getColumn($column)
    {
        $column = explode('.', $column);
        if (count($column) === 1) {
            $column = [ $this->definition->getName(), $column[0] ];
            $col = $this->definition->getColumn($column[1]);
            if (!$col) {
                throw new DBException('Invalid column name in own table');
            }
        } elseif (count($column) === 2) {
            if ($column[0] === $this->definition->getName()) {
                $col = $this->definition->getColumn($column[1]);
                if (!$col) {
                    throw new DBException('Invalid column name in own table');
                }
            } else {
                if ($this->definition->hasRelation($column[0])) {
                    $col = $this->definition->getRelation($column[0])->table->getColumn($column[1]);
                    if (!$col) {
                        throw new DBException('Invalid column name in related table');
                    }
                } elseif (isset($this->joins[$column[0]])) {
                    $col = $this->joins[$column[0]]->table->getColumn($column[1]);
                    if (!$col) {
                        throw new DBException('Invalid column name in related table');
                    }
                } else {
                    throw new DBException('Invalid foreign table name: ' . implode(',', $column));
                }
            }
        } else {
            $name = array_pop($column);
            $this->with(implode('.', $column));
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
        return [ 'name' => implode('.', $column), 'data' => $col ];
    }
    protected function normalizeValue(TableColumn $col, $value)
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
                            throw new DBException('Invalid value for date column ' . $col->getName());
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
                    throw new DBException('Invalid value (unknown data type) for date column ' . $col->getName());
                }
                return $value;
            case 'datetime':
                if (is_string($value)) {
                    $temp = strtotime($value);
                    if (!$temp) {
                        if ($strict) {
                            throw new DBException('Invalid value for datetime column ' . $col->getName());
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
                            throw new DBException('Invalid value (using integer) for enum ' . $col->getName());
                        }
                        return $value;
                    }
                    return $values[$value];
                }
                if (!in_array($value, $col->getValues())) {
                    if ($strict) {
                        throw new DBException('Invalid value for enum ' . $col->getName());
                    }
                    return 0;
                }
                return $value;
            case 'int':
                $temp = preg_replace('([^+\-0-9]+)', '', $value);
                return is_string($temp) ? (int)$temp : 0;
            case 'float':
                $temp = preg_replace('([^+\-0-9.]+)', '', str_replace(',', '.', $value));
                return is_string($temp) ? (float)$temp : 0;
            case 'text':
                // check using strlen first, in order to avoid hitting mb_ functions which might be polyfilled
                // because the polyfill is quite slow
                if ($col->hasLength() && strlen($value) > $col->getLength() && mb_strlen($value) > $col->getLength()) {
                    if ($strict) {
                        throw new DBException('Invalid value for text column ' . $col->getName());
                    }
                    return mb_substr($value, 0, $col->getLength());
                }
                return $value;
            default: // time, blob, etc
                return $value;
        }
    }

    protected function filterSQL(string $column, $value, bool $negate = false) : array
    {
        list($name, $column) = array_values($this->getColumn($column));
        if (is_array($value) && count($value) === 1 && isset($value['not'])) {
            $negate = true;
            $value = $value['not'];
        }
        if (is_array($value) && count($value) === 1 && isset($value['like'])) {
            $value = $value['like'];
            // str_replace(['%', '_'], ['\\%','\\_'], $q)
            return $negate ?
                [
                    $name . ' NOT LIKE ?',
                    [ $this->normalizeValue($column, $value) ]
                ] :
                [
                    $name . ' LIKE ?',
                    [ $this->normalizeValue($column, $value) ]
                ];
        }
        if (is_null($value)) {
            return $negate ?
                [ $name . ' IS NOT NULL', [] ]:
                [ $name . ' IS NULL', [] ];
        }
        if (!is_array($value)) {
            return $negate ?
                [
                    $name . ' <> ?',
                    [ $this->normalizeValue($column, $value) ]
                ] :
                [
                    $name . ' = ?',
                    [ $this->normalizeValue($column, $value) ]
                ];
        }
        if (isset($value['beg']) && strlen($value['beg']) && (!isset($value['end']) || !strlen($value['end']))) {
            $value = [ 'gte' => $value['beg'] ];
        }
        if (isset($value['end']) && strlen($value['end']) && (!isset($value['beg']) || !strlen($value['beg']))) {
            $value = [ 'lte' => $value['end'] ];
        }
        if (isset($value['beg']) && isset($value['end'])) {
            return $negate ?
                [
                    $name.' NOT BETWEEN ? AND ?',
                    [
                        $this->normalizeValue($column, $value['beg']),
                        $this->normalizeValue($column, $value['end'])
                    ]
                ] :
                [
                    $name.' BETWEEN ? AND ?',
                    [
                        $this->normalizeValue($column, $value['beg']),
                        $this->normalizeValue($column, $value['end'])
                    ]
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
            return [
                '(' . implode(' AND ', $sql) . ')',
                $par
            ];
        }
        return $negate ?
            [
                $name . ' NOT IN (??)',
                [ array_map(function ($v) use ($column) {
                    return $this->normalizeValue($column, $v);
                }, $value) ]
            ] :
            [
                $name . ' IN (??)',
                [ array_map(function ($v) use ($column) {
                    return $this->normalizeValue($column, $v);
                }, $value) ]
            ];
    }
    /**
     * Filter the results by a column and a value
     * @param  string $column  the column name to filter by (related columns can be used - for example: author.name)
     * @param  mixed  $value   a required value, array of values or range of values (range example: ['beg'=>1,'end'=>3])
     * @param  bool   $negate  optional boolean indicating that the filter should be negated
     * @return $this
     */
    public function filter(string $column, $value, bool $negate = false) : self
    {
        $sql = $this->filterSQL($column, $value, $negate);
        return strlen($sql[0]) ? $this->where($sql[0], $sql[1]) : $this;
    }
    /**
     * Filter the results matching any of the criteria
     * @param  array $criteria  each row is a column, value and optional negate flag (same as filter method)
     * @return $this
     */
    public function any(array $criteria) : self
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
    public function all(array $criteria) : self
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
    public function sort(string $column, bool $desc = false) : self
    {
        return $this->order($this->getColumn($column)['name'] . ' ' . ($desc ? 'DESC' : 'ASC'));
    }
    /**
     * Group by a column (or columns)
     * @param  string|array        $column the column name (or names) to group by
     * @return $this
     */
    public function group($column) : self
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
    public function paginate(int $page = 1, int $perPage = 25) : self
    {
        return $this->limit($perPage, ($page - 1) * $perPage);
    }
    public function __call($name, $data)
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
    }
    /**
     * Remove all filters, sorting, etc
     * @return $this
     */
    public function reset() : self
    {
        $this->where = [];
        $this->joins = [];
        $this->group = [];
        $this->withr = [];
        $this->order = [];
        $this->having = [];
        $this->aliases = [];
        $this->li_of = [0,0,0];
        $this->qiterator = null;
        return $this;
    }
    /**
     * Apply advanced grouping
     * @param  string $sql    SQL statement to use in the GROUP BY clause
     * @param  array  $params optional params for the statement (defaults to an empty array)
     * @return $this
     */
    public function groupBy(string $sql, array $params = []) : self
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
    public function join($table, array $fields, string $name = null, bool $multiple = true)
    {
        $table = $table instanceof Table ? $table : $this->db->definition((string)$table);
        $name = $name ?? $table->getName();
        if (isset($this->joins[$name]) || $this->definition->hasRelation($name)) {
            throw new DBException('Alias / table name already in use');
        }
        $this->joins[$name] = new TableRelation($name, $table, [], $multiple);
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
    public function where(string $sql, array $params = []) : self
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
    public function having(string $sql, array $params = []) : self
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
    public function order(string $sql, array $params = []) : self
    {
        $this->qiterator = null;
        $name = null;
        if (!count($params)) {
            $name = preg_replace('(\s+(ASC|DESC)\s*$)i', '', $sql);
            try {
                if ($name === null) {
                    throw new \Exception();
                }
                $name = $this->getColumn(trim($name))['name'];
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
    public function limit(int $limit, int $offset = 0, bool $limitOnMainTable = false) : self
    {
        $this->qiterator = null;
        $this->li_of = [ $limit, $offset, $limitOnMainTable ? 1 : 0 ];
        return $this;
    }
    /**
     * Get the number of records
     * @return int the total number of records (does not respect pagination)
     */
    public function count() : int
    {
        $aliases = [];
        $getAlias = function ($name) use (&$aliases) {
            // to bypass use: return $name;
            return $aliases[$name] = $aliases[$name] ?? 'alias' . static::SEP . count($aliases);
        };
        $table = $this->definition->getName();
        $sql = 'SELECT COUNT(DISTINCT '.$table.'.'.implode(', '.$table.'.', $this->pkey).') FROM '.$table.' ';
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
        foreach ($this->definition->getRelations() as $k => $v) {
            foreach ($w as $kk => $vv) {
                if (preg_match('(\b'.preg_quote($k . '.'). ')i', $vv[0])) {
                    $relations[$k] = [ $v, $table ];
                    $w[$kk][0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $getAlias($k) . '.', $vv[0]);
                }
            }
            if (isset($o[0]) && preg_match('(\b'.preg_quote($k . '.'). ')i', $o[0])) {
                $relations[$k] = [ $v, $table ];
            }
            foreach ($h as $kk => $vv) {
                if (preg_match('(\b'.preg_quote($k . '.'). ')i', $vv[0])) {
                    $relations[$k] = [ $v, $table ];
                    $h[$kk][0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $getAlias($k) . '.', $vv[0]);
                }
            }
            if (isset($g[0]) && preg_match('(\b'.preg_quote($k . '.'). ')i', $g[0])) {
                $relations[$k] = [ $v, $table ];
                $g[0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $getAlias($k) . '.', $g[0]);
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

        foreach ($relations as $k => $v) {
            $table = $v[1] !== $this->definition->getName() ? $getAlias($v[1]) : $v[1];
            $v = $v[0];
            if ($v->pivot) {
                $alias = $getAlias($k.'_pivot');
                $sql .= 'LEFT JOIN '.$v->pivot->getName().' '.$alias.' ON ';
                $tmp = [];
                foreach ($v->keymap as $kk => $vv) {
                    $tmp[] = $table.'.'.$kk.' = '.$alias.'.'.$vv.' ';
                }
                $sql .= implode(' AND ', $tmp) . ' ';
                $sql .= 'LEFT JOIN '.$v->table->getName().' '.$getAlias($k).' ON ';
                $tmp = [];
                foreach ($v->pivot_keymap as $kk => $vv) {
                    $tmp[] = $getAlias($k).'.'.$vv.' = '.$alias.'.'.$kk.' ';
                }
                $sql .= implode(' AND ', $tmp) . ' ';
            } else {
                $alias = $getAlias($k);
                $sql .= 'LEFT JOIN '.$v->table->getName().' '.$alias.' ON ';
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
            $sql .= ($v->many ? 'LEFT ' : '' ) . 'JOIN '.$v->table->getName().' '.$k.' ON ';
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
    public function columns(array $fields, bool $addPrimary = true) : self
    {
        foreach ($fields as $k => $v) {
            if (strpos($v, '*') !== false) {
                $temp = explode('.', $v);
                if (count($temp) === 1) {
                    $table = $this->definition->getName();
                    $cols = $this->definition->getColumns();
                } elseif (count($temp) === 2) {
                    $table = $temp[0];
                    if ($this->definition->hasRelation($table)) {
                        $cols = $this->definition->getRelation($table)->table->getColumns();
                    } elseif (isset($this->joins[$table])) {
                        $cols = $this->joins[$table]->table->getColumns();
                    } else {
                        throw new DBException('Invalid foreign table name');
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
     * Perform the actual fetch
     * @param  array|null $fields optional array of columns to select (related columns can be used too)
     * @return mixed               the query result as an iterator (with array access)
     */
    public function iterator(array $fields = null, array $collectionKey = null)
    {
        if ($this->qiterator) {
            return $this->qiterator;
        }
        $aliases = [];
        $getAlias = function ($name) use (&$aliases) {
            // to bypass use: return $name;
            return $aliases[$name] = $aliases[$name] ?? 'alias' . static::SEP . count($aliases);
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

        foreach ($this->definition->getRelations() as $k => $relation) {
            foreach ($f as $kk => $field) {
                if (strpos($field, $k . '.') === 0) {
                    $relations[$k] = [ $relation, $table ];
                    $f[$kk] = str_replace($k . '.', $getAlias($k) . '.', $field);
                }
            }
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
        $select = [];
        foreach ($f as $k => $field) {
            $select[] = $field . (!is_numeric($k) ? ' ' . $k : '');
        }
        foreach ($this->withr as $name => $relation) {
            foreach ($relation[0]->table->getColumns() as $column) {
                $select[] = $getAlias($name) . '.' . $column . ' ' . $getAlias($name . static::SEP . $column);
            }
        }
        $sql = 'SELECT '.implode(', ', $select).' FROM '.$table.' ';
        $par = [];
        $many = false;
        foreach ($relations as $relation => $v) {
            $table = $v[1] !== $this->definition->getName() ? $getAlias($v[1]) : $v[1];
            $v = $v[0];
            if ($v->many || $v->pivot) {
                $many = true;
            }
            if ($v->pivot) {
                $alias = $getAlias($relation.'_pivot');
                $sql .= 'LEFT JOIN '.$v->pivot->getName().' '.$alias.' ON ';
                $tmp = [];
                foreach ($v->keymap as $kk => $vv) {
                    $tmp[] = $table.'.'.$kk.' = '.$alias.'.'.$vv.' ';
                }
                $sql .= implode(' AND ', $tmp) . ' ';
                $sql .= 'LEFT JOIN '.$v->table->getName().' '.$getAlias($relation).' ON ';
                $tmp = [];
                foreach ($v->pivot_keymap as $kk => $vv) {
                    $tmp[] = $getAlias($relation).'.'.$vv.' = '.$alias.'.'.$kk.' ';
                }
                $sql .= implode(' AND ', $tmp) . ' ';
            } else {
                $alias = $getAlias($relation);

                $sql .= 'LEFT JOIN '.$v->table->getName().' '.$alias.' ON ';
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
            $sql .= ($v->many ? 'LEFT ' : '' ) . 'JOIN '.$v->table->getName().' '.$k.' ON ';
            $tmp = [];
            foreach ($v->keymap as $kk => $vv) {
                $tmp[] = $kk.' = '.$vv;
            }
            $sql .= implode(' AND ', $tmp) . ' ';
        }
        if ($many && count($porder) && $this->li_of[2] === 1) {
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
        if (count($o)) {
            $sql .= 'ORDER BY ' . $o[0] . ' ';
            $par = array_merge($par, $o[1]);
        }
        if (!count($g) && count($porder)) {
            $pdir = (count($o) && strpos($o[0], 'DESC') !== false) ? 'DESC' : 'ASC';
            $porder = array_map(function ($v) use ($pdir) {
                return $v . ' ' . $pdir;
            }, $porder);
            $sql .= (count($o) ? ', ' : 'ORDER BY ') . implode(', ', $porder) . ' ';
        }
        if ((!$many || $this->li_of[2] === 0 || !count($porder)) && $this->li_of[0]) {
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
    public function select(array $fields = null, array $collectionKey = null) : array
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
        $table = $this->definition->getName();
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
        $table = $this->definition->getName();
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
    public function with(string $relation) : self
    {
        $this->qiterator = null;
        $parts = explode('.', $relation);
        $table = $this->definition;
        array_reduce(
            $parts,
            function ($carry, $item) use (&$table) {
                if (!$table->hasRelation($item)) {
                    throw new DBException('Invalid relation name');
                }
                $relation = $table->getRelation($item);
                $name = $carry ? $carry . static::SEP . $item : $item;
                $this->withr[$name] = [ $relation, $carry ?? $table->getName() ];
                $table = $relation->table;
                return $name;
            }
        );
        return $this;
    }

    public function getIterator()
    {
        return $this->iterator();
    }

    public function offsetGet($offset)
    {
        return $this->iterator()->offsetGet($offset);
    }
    public function offsetExists($offset)
    {
        return $this->iterator()->offsetExists($offset);
    }
    public function offsetUnset($offset)
    {
        $this->iterator()->offsetUnset($offset);
    }
    public function offsetSet($offset, $value)
    {
        $this->iterator()->offsetSet($offset, $value);
    }

    public function collection(array $fields = null) : Collection
    {
        return new Collection($this->iterator($fields));
    }

    public function ids()
    {
        if (count($this->group)) {
            throw new DBException('Can not LIMIT result set by master table when GROUP BY is used');
        }
        if (count($this->order) && !isset($this->order[2])) {
            throw new DBException('Can not LIMIT result set by master table with a complex ORDER BY query');
        }

        $aliases = [];
        $getAlias = function ($name) use (&$aliases) {
            // to bypass use: return $name;
            return $aliases[$name] = $aliases[$name] ?? 'alias' . static::SEP . count($aliases);
        };
        
        $table = $this->definition->getName();
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
            if (isset($g[0]) && preg_match('(\b'.preg_quote($k . '.'). ')i', $g[0])) {
                $relations[$k] = [ $v, $table ];
                $g[0] = preg_replace('(\b'.preg_quote($k . '.'). ')i', $getAlias($k) . '.', $g[0]);
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
        $sql  = 'SELECT DISTINCT '.implode(', ', $dst).' FROM '.$table.' ';
        foreach ($relations as $k => $v) {
            $table = $v[1] !== $this->definition->getName() ? $getAlias($v[1]) : $v[1];
            $v = $v[0];
            if ($v->pivot) {
                $alias = $getAlias($k.'_pivot');
                $sql .= 'LEFT JOIN '.$v->pivot->getName().' '.$alias.' ON ';
                $tmp = [];
                foreach ($v->keymap as $kk => $vv) {
                    $tmp[] = $table.'.'.$kk.' = '.$alias.'.'.$vv.' ';
                }
                $sql .= implode(' AND ', $tmp) . ' ';
                $sql .= 'LEFT JOIN '.$v->table->getName().' '.$getAlias($k).' ON ';
                $tmp = [];
                foreach ($v->pivot_keymap as $kk => $vv) {
                    $tmp[] = $getAlias($k).'.'.$vv.' = '.$alias.'.'.$kk.' ';
                }
                $sql .= implode(' AND ', $tmp) . ' ';
            } else {
                $alias = $getAlias($k);
                $sql .= 'LEFT JOIN '.$v->table->getName().' '.$alias.' ON ';
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
            $sql .= ($v->many ? 'LEFT ' : '' ) . 'JOIN '.$v->table->getName().' '.$k.' ON ';
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
                if ((int)$this->db->driverOption('version', 0) >= 12) {
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
    public function find($primary)
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
