<?php

namespace vakata\database\driver\sphinx;

use \vakata\database\DriverInterface;
use \vakata\database\ResultInterface;
use \vakata\collection\Collection;

class Result implements ResultInterface
{
    protected $lnk = null;
    protected $row = [];
    protected $buff = true;
    protected $last = null;
    protected $fetched = -1;
    protected $result = null;
    protected $nativeDriver = false;

    public function __construct($lnk, $sql, bool $buff = true)
    {
        $this->lnk = $lnk;
        $this->result = $this->lnk->query($sql, $buff ? MYSQLI_STORE_RESULT : MYSQLI_USE_RESULT);
    }
    public function affected() : int
    {
        return $this->lnk->affected_rows;
    }
    public function insertID(string $sequence = null)
    {
        return $this->lnk->insert_id;
    }
    public function toArray() : array
    {
        return iterator_to_array($this);
    }

    public function count(): int
    {
        return $this->result->num_rows;
    }

    public function key()
    {
        return $this->fetched;
    }
    public function current()
    {
        return $this->last;
    }
    public function rewind(): void
    {
        if ($this->fetched >= 0) {
            $this->result->data_seek(0);
        }
        $this->last = null;
        $this->fetched = -1;
        $this->next();
    }
    public function next(): void
    {
        $this->fetched ++;
        $this->last = $this->result->fetch_assoc();
    }
    public function valid(): bool
    {
        return !!$this->last;
    }
}
