<?php

namespace vakata\database\driver\postgre;

use \vakata\database\DBException;
use \vakata\database\DriverInterface;
use \vakata\database\ResultInterface;
use \vakata\collection\Collection;

class Result implements ResultInterface
{
    protected $statement;
    protected $last = null;
    protected $fetched = -1;
    protected $iid = null;
    protected $aff = 0;

    public function __construct($statement, $iid, $aff)
    {
        $this->statement = $statement;
        $this->iid = $iid;
        $this->aff = $aff;
    }
    public function __destruct()
    {
        @\pg_free_result($this->statement);
    }
    public function affected() : int
    {
        return $this->aff;
    }
    public function insertID()
    {
        return $this->iid;
    }
    public function toArray() : array
    {
        return iterator_to_array($this);
    }

    public function count()
    {
        return \pg_num_rows($this->statement);
    }

    public function key()
    {
        return $this->fetched;
    }
    public function current()
    {
        return $this->last;
    }
    public function rewind()
    {
        if ($this->fetched >= 0) {
            \pg_result_seek($this->statement, 0);
        }
        $this->last = null;
        $this->fetched = -1;
        $this->next();
    }
    public function next()
    {
        $this->fetched ++;
        $this->last = \pg_fetch_array($this->statement, null, \PGSQL_ASSOC);
    }
    public function valid()
    {
        return !!$this->last;
    }
}