<?php

namespace vakata\database\driver\sqlite;

use SQLite3Result;
use \vakata\database\DBException;
use \vakata\database\ResultInterface;

class Result implements ResultInterface
{
    protected SQLite3Result $statement;
    protected array $row = [];
    protected ?array $last = null;
    protected int $fetched = -1;
    protected int $iid = 0;
    protected int $aff = 0;

    public function __construct(SQLite3Result $statement, int $iid, int $aff)
    {
        $this->statement = $statement;
        $this->iid = $iid;
        $this->aff = $aff;
    }
    public function __destruct()
    {
        $this->statement->finalize();
    }
    public function affected() : int
    {
        return $this->aff;
    }
    public function insertID(string $sequence = null): int
    {
        return $this->iid;
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
            $this->statement->reset();
        }
        $this->last = null;
        $this->fetched = -1;
        $this->next();
    }
    public function next(): void
    {
        $this->fetched ++;
        $this->last = $this->statement->fetchArray(\SQLITE3_ASSOC)?:null;
    }
    public function valid(): bool
    {
        return !!$this->last;
    }
}
