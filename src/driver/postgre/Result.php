<?php

namespace vakata\database\driver\postgre;

use \vakata\database\DBException;
use \vakata\database\DriverInterface;
use \vakata\database\ResultInterface;
use \vakata\collection\Collection;

class Result implements ResultInterface
{
    protected mixed $statement;
    protected ?array $last = null;
    protected int $fetched = -1;
    protected mixed $driver = null;
    protected mixed $iid = null;
    protected int $aff = 0;
    protected array $types = [];

    public function __construct(mixed $statement, mixed $driver, int $aff)
    {
        $this->statement = $statement;
        $this->driver = $driver;
        $this->aff = $aff;
    }
    public function __destruct()
    {
        \pg_free_result($this->statement);
    }
    public function affected() : int
    {
        return $this->aff;
    }
    public function insertID(string $sequence = null): mixed
    {
        if ($this->iid === null) {
            $temp = @\pg_query(
                $this->driver,
                $sequence ?
                    'SELECT currval('.@\pg_escape_string($this->driver, $sequence).')' :
                    'SELECT lastval()'
            );
            if ($temp) {
                $res = \pg_fetch_row($temp);
                $this->iid = $res && is_array($res) && isset($res[0]) ? $res[0] : null;
            }
        }
        return $this->iid;
    }
    public function toArray() : array
    {
        return iterator_to_array($this);
    }

    public function count(): int
    {
        return \pg_num_rows($this->statement);
    }

    public function key(): mixed
    {
        return $this->fetched;
    }
    public function current(): mixed
    {
        return $this->last;
    }
    public function rewind(): void
    {
        if ($this->fetched >= 0) {
            \pg_result_seek($this->statement, 0);
        }
        $this->last = null;
        $this->fetched = -1;
        $this->next();
    }
    public function next(): void
    {
        $this->fetched ++;
        $this->last = \pg_fetch_array($this->statement, null, \PGSQL_ASSOC)?:null;
        if (is_array($this->last) && count($this->last)) {
            $this->cast();
        }
    }
    public function valid(): bool
    {
        return !!$this->last;
    }

    protected function cast()
    {
        if (!count($this->types)) {
            foreach (array_keys($this->last) as $k => $v) {
                $this->types[$v] = \pg_field_type($this->statement, $k);
            }
        }
        foreach ($this->last as $k => $v) {
            if (is_null($v) || !isset($this->types[$k])) {
                continue;
            }
            switch ($this->types[$k]) {
                case 'int2':
                case 'int4':
                case 'int8':
                    $this->last[$k] = (int)$v;
                    break;
                case 'bit':
                case 'bool':
                    $this->last[$k] = $v !== 'f' && (int)$v ? true : false;
                    break;
                case 'float4':
                case 'float8':
                    $this->last[$k] = (float)$v;
                case 'money':
                case 'numeric':
                    // TODO:
                    break;
            }
        }
    }
}
