<?php
namespace vakata\database\schema;

use vakata\collection\Collection;
use vakata\database\DBException;

/**
 * A table query iterator
 */
class TableQueryIterator implements \Iterator, \ArrayAccess
{
    const SEP = '___';
    /**
     * @var array
     */
    protected $pkey;
    /**
     * @var Collection
     */
    protected $result;
    /**
     * @var array[]
     */
    protected $relations;
    /**
     * @var array[]
     */
    protected $aliases;
    /**
     * @var string|null
     */
    protected $primary = null;
    /**
     * @var int
     */
    protected $fetched = 0;

    public function __construct(Collection $result, array $pkey, array $relations = [], array $aliases = [])
    {
        $this->pkey = $pkey;
        $this->result = $result;
        $this->relations = $relations;
        $this->aliases = $aliases;
    }

    public function key()
    {
        return $this->fetched;
    }
    public function current()
    {
        $result = null;
        $remove = [];
        while ($this->result->valid()) {
            $row = $this->result->current();
            $pk = [];
            foreach ($this->pkey as $field) {
                $pk[$field] = $row[$field];
            }
            $pk = json_encode($pk);
            if ($this->primary !== null && $pk !== $this->primary) {
                break;
            }
            $this->primary = $pk;
            if (!$result) {
                $result = $row;
            }
            foreach ($this->relations as $name => $relation) {
                $relation = $relation[0];
                $fields = [];
                $exists = false;
                foreach ($relation->table->getColumns() as $column) {
                    $nm = $name . static::SEP . $column;
                    if (isset($this->aliases[$nm])) {
                        $nm = $this->aliases[$nm];
                    }
                    $fields[$column] = $row[$nm];
                    if (!$exists && $row[$nm] !== null) {
                        $exists = true;
                    }
                    $remove[] = $nm; // $name . static::SEP . $column;
                }
                $temp  = &$result;
                $parts = explode(static::SEP, $name);
                $name  = array_pop($parts);
                if (!$exists && !count($parts) && !isset($temp[$name])) {
                    $temp[$name] = $relation->many ? [ '___clean' => true ] : null;
                }
                if ($exists) {
                    $full  = '';
                    foreach ($parts as $item) {
                        $full = $full ? $full . static::SEP . $item : $item;
                        $temp = &$temp[$item];
                        $rpk = [];
                        foreach ($this->relations[$full][0]->table->getPrimaryKey() as $pkey) {
                            $nm = $full . static::SEP . $pkey;
                            if (isset($this->aliases[$nm])) {
                                $nm = $this->aliases[$nm];
                            }
                            $rpk[$pkey] = $row[$nm];
                        }
                        $temp = &$temp[json_encode($rpk)];
                    }
                    if (!isset($temp[$name])) {
                        $temp[$name] = $relation->many ? [ '___clean' => true ] : null;
                    }
                    $temp = &$temp[$name];
                    if ($relation->many) {
                        $rpk = [];
                        foreach ($relation->table->getPrimaryKey() as $field) {
                            $rpk[$field] = $fields[$field];
                        }
                        $temp[json_encode($rpk)] = array_merge($temp[json_encode($rpk)] ?? [], $fields);
                    } else {
                        $temp = array_merge($temp ?? [], $fields);
                    }
                }
            }
            $this->result->next();
        }
        if ($result) {
            foreach ($remove as $name) {
                unset($result[$name]);
            }
            $result = $this->values($result);
        }
        return $result;
    }
    protected function values(array $data)
    {
        foreach ($data as $k => $v) {
            if (is_array($v) && isset($v['___clean']) && $v['___clean'] === true) {
                unset($v['___clean']);
                $data[$k] = array_values($v);
                foreach ($data[$k] as $kk => $vv) {
                    $data[$k][$kk] = $this->values($vv);
                }
            }
        }
        return $data;
    }

    public function rewind()
    {
        $this->fetched = 0;
        $this->primary = null;
        return $this->result->rewind();
    }
    public function next()
    {
        if ($this->primary === null) {
            $this->result->next();
            if ($this->result->valid()) {
                $row = $this->result->current();
                $temp = [];
                foreach ($this->pkey as $field) {
                    $temp[$field] = $row[$field];
                }
                $this->primary = json_encode($temp);
                return;
            }
        }
        $this->fetched ++;
        while ($this->result->valid()) {
            $row = $this->result->current();
            $pk = [];
            foreach ($this->pkey as $field) {
                $pk[$field] = $row[$field];
            }
            $pk = json_encode($pk);
            if ($this->primary !== $pk) {
                $this->primary = $pk;
                break;
            }
            $this->result->next();
        }
    }
    public function valid()
    {
        return $this->result->valid();
    }

    public function offsetGet($offset)
    {
        $index = $this->fetched;
        $item = null;
        foreach ($this as $k => $v) {
            if ($k === $offset) {
                $item = $v;
            }
        }
        foreach ($this as $k => $v) {
            if ($k === $index) {
                break;
            }
        }
        return $item;
    }
    public function offsetExists($offset)
    {
        $index = $this->fetched;
        $exists = false;
        foreach ($this as $k => $v) {
            if ($k === $offset) {
                $exists = true;
            }
        }
        foreach ($this as $k => $v) {
            if ($k === $index) {
                break;
            }
        }
        return $exists;
    }
    public function offsetSet($offset, $value)
    {
        throw new DBException('Invalid call to offsetSet');
    }
    public function offsetUnset($offset)
    {
        throw new DBException('Invalid call to offsetUnset');
    }
}
