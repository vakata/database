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
    protected array  $pkey;
    /**
     * @var Collection
     */
    protected Collection $result;
    /**
     * @var array[]
     */
    protected array $relations;
    /**
     * @var array[]
     */
    protected array $aliases;
    /**
     * @var string|null
     */
    protected ?string $primary = null;
    /**
     * @var int
     */
    protected int $fetched = 0;

    public function __construct(Collection $result, array $pkey, array $relations = [], array $aliases = [])
    {
        $this->pkey = $pkey;
        $this->result = $result;
        $this->relations = $relations;
        $this->aliases = $aliases;
    }

    public function key(): mixed
    {
        return $this->fetched;
    }
    public function current(): mixed
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
            if ($pk === false) {
                throw new DBException('Invalid PK');
            }
            if ($this->primary !== null && $pk !== $this->primary) {
                break;
            }
            $this->primary = $pk;
            if (!$result) {
                $result = $row;
            }
            foreach ($this->relations as $name => $relation) {
                if (!$relation[2]) {
                    continue;
                }
                $relation = $relation[0];
                $fields = [];
                $exists = false;
                foreach ($relation->table->getColumns() as $column) {
                    $nm = $name . static::SEP . $column;
                    if (isset($this->aliases[$nm])) {
                        $nm = $this->aliases[$nm];
                    }
                    if (array_key_exists($nm, $row)) {
                        $fields[$column] = $row[$nm];
                    }
                    if (!$exists && array_key_exists($nm, $row) && $row[$nm]) {
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
                        if ($this->relations[$full][0]->many) {
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
    protected function values(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                if (isset($v['___clean']) && $v['___clean'] === true) {
                    unset($v['___clean']);
                    $data[$k] = array_values($v);
                }
                foreach ($data[$k] as $kk => $vv) {
                    if (is_array($vv)) {
                        $data[$k][$kk] = $this->values($vv);
                    }
                }
            }
        }
        return $data;
    }

    public function rewind(): void
    {
        $this->fetched = 0;
        $this->primary = null;
        $this->result->rewind();
    }
    public function next(): void
    {
        if ($this->primary === null) {
            $this->result->next();
            if ($this->result->valid()) {
                $row = $this->result->current();
                $temp = [];
                foreach ($this->pkey as $field) {
                    $temp[$field] = $row[$field];
                }
                $pk = json_encode($temp);
                if ($pk === false) {
                    throw new DBException('Invalid PK');
                }
                $this->primary = $pk;
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
            if ($pk === false) {
                throw new DBException('Invalid PK');
            }
            if ($this->primary !== $pk) {
                $this->primary = $pk;
                break;
            }
            $this->result->next();
        }
    }
    public function valid(): bool
    {
        return $this->result->valid();
    }

    public function offsetGet(mixed $offset): mixed
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
    public function offsetExists(mixed $offset): bool
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
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new DBException('Invalid call to offsetSet');
    }
    public function offsetUnset(mixed $offset): void
    {
        throw new DBException('Invalid call to offsetUnset');
    }
}

