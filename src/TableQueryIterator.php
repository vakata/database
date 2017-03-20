<?php
namespace vakata\database;

/**
 * A table query iterator
 */
class TableQueryIterator implements \Iterator, \ArrayAccess
{
    /**
     * @var array
     */
    protected $pkey;
    /**
     * @var Result
     */
    protected $result;
    /**
     * @var array[]
     */
    protected $relations;
    /**
     * @var string|null
     */
    protected $primary = null;
    /**
     * @var int
     */
    protected $fetched = 0;

    public function __construct(ResultInterface $result, array $pkey, array $relations = [])
    {
        $this->pkey = $pkey;
        $this->result = $result;
        $this->relations = $relations;
    }

    public function key()
    {
        return $this->fetched;
    }
    public function current()
    {
        $result = null;
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
                if (!isset($result[$name])) {
                    $result[$name] = $relation->many ? [] : null;
                }
                $fields = [];
                $exists = false;
                foreach ($relation->table->getColumns() as $column) {
                    $fields[$column] = $row[$name . '___' . $column];
                    if (!$exists && $row[$name . '___' . $column] !== null) {
                        $exists = true;
                    }
                    unset($result[$name . '___' . $column]);
                }
                if ($exists) {
                    if ($relation->many) {
                        $rpk = [];
                        foreach ($relation->table->getPrimaryKey() as $field) {
                            $rpk[$field] = $fields[$field];
                        }
                        $result[$name][json_encode($rpk)] = $fields;
                    } else {
                        $result[$name] = $fields;
                    }
                }
            }
            $this->result->next();
        }
        if ($result) {
            foreach ($this->relations as $name => $relation) {
                if ($relation->many) {
                    $result[$name] = array_values($result[$name]);
                }
            }
        }
        return $result;
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
        throw new DatabaseException('Invalid call to offsetSet');
    }
    public function offsetUnset($offset)
    {
        throw new DatabaseException('Invalid call to offsetUnset');
    }
}
