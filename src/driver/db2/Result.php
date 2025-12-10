<?php

namespace vakata\database\driver\db2;

use \vakata\database\DBException;
use \vakata\database\DriverInterface;
use \vakata\database\ResultInterface;
use \vakata\collection\Collection;

class Result implements ResultInterface
{
    protected array $data;
    protected mixed $statement;
    protected mixed $driver;
    protected ?array $last = null;
    protected int $fetched = -1;
    protected int $affected = 0;

    public function __construct(mixed $statement, array $data, mixed $driver)
    {
        $this->statement = $statement;
        $this->data = $data;
        $this->driver = $driver;
        $this->affected = \db2_num_rows($this->statement);
    }
    public function __destruct()
    {
        \db2_free_result($this->statement);
    }
    public function affected() : int
    {
        return $this->affected;
    }
    public function insertID(?string $sequence = null): mixed
    {
        return \db2_last_insert_id($this->driver);
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
            $temp = \db2_execute($this->statement, $this->data);
            if (!$temp) {
                throw new DBException('Could not execute query');
            }
        }
        $this->last = null;
        $this->fetched = -1;
        $this->next();
    }
    public function next(): void
    {
        $this->fetched ++;
        $this->last = \db2_fetch_assoc($this->statement)?:null;
    }
    public function valid(): bool
    {
        return !!$this->last;
    }
}
