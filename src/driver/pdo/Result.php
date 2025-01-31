<?php

namespace vakata\database\driver\pdo;

use PDO;
use PDOStatement;
use \vakata\database\DriverInterface;
use \vakata\database\ResultInterface;
use \vakata\collection\Collection;

class Result implements ResultInterface
{
    protected PDOStatement $statement;
    protected PDO $driver;
    protected int $fetched = -1;
    protected ?array $last = null;

    public function __construct(PDOStatement $statement, PDO $driver)
    {
        $this->statement = $statement;
        $this->driver = $driver;
    }
    public function affected() : int
    {
        return $this->statement->rowCount();
    }
    public function insertID(?string $sequence = null): mixed
    {
        return $this->driver->lastInsertId($sequence);
    }
    public function toArray() : array
    {
        return iterator_to_array($this);
    }

    public function count(): int
    {
        return $this->statement->rowCount();
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
            $this->statement->execute();
        }
        $this->last = null;
        $this->fetched = -1;
        $this->next();
    }
    public function next(): void
    {
        $this->fetched ++;
        $this->last = $this->statement->fetch(\PDO::FETCH_ASSOC)?:null;
    }
    public function valid(): bool
    {
        return !!$this->last;
    }
}
