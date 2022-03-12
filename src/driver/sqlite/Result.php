<?php

namespace vakata\database\driver\sqlite;

use \vakata\database\DBException;
use \vakata\database\ResultInterface;

class Result implements ResultInterface
{
    protected $statement;
    protected $row = [];
    protected $last = null;
    protected $fetched = -1;
    protected $iid = null;
    protected $aff = 0;

    public function __construct(\SQLite3Result $statement, $iid, $aff)
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
    public function insertID(string $sequence = null)
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
        $this->last = $this->statement->fetchArray(\SQLITE3_ASSOC);
    }
    public function valid(): bool
    {
        return !!$this->last;
    }
}
