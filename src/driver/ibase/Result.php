<?php

namespace vakata\database\driver\ibase;

use \vakata\database\DBException;
use \vakata\database\DriverInterface;
use \vakata\database\ResultInterface;
use \vakata\collection\Collection;

class Result implements ResultInterface
{
    protected array $data;
    protected mixed $result;
    protected mixed $driver;
    protected ?array $last = null;
    protected int $fetched = -1;
    protected int $affected = 0;

    public function __construct(mixed $result, array $data, mixed $driver)
    {
        $this->result = $result;
        $this->data = $data;
        $this->driver = $driver;
        $this->affected = \ibase_affected_rows($this->driver);
    }
    public function __destruct()
    {
        \ibase_free_result($this->result);
    }
    public function affected() : int
    {
        return $this->affected;
    }
    public function insertID(?string $sequence = null): mixed
    {
        return null;
    }
    public function toArray() : array
    {
        return iterator_to_array($this);
    }

    public function count(): int
    {
        throw new DBException('Not supported');
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
            $this->result = call_user_func_array("\ibase_execute", $this->data);
            if (!$this->result) {
                throw new DBException('Could not execute query : '.\ibase_errmsg());
            }
        }
        $this->last = null;
        $this->fetched = -1;
        $this->next();
    }
    public function next(): void
    {
        $this->fetched ++;
        $this->last = \ibase_fetch_assoc($this->result, \IBASE_TEXT)?:null;
    }
    public function valid(): bool
    {
        return !!$this->last;
    }
}
