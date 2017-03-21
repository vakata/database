<?php

namespace vakata\database\driver\oracle;

use \vakata\database\DBException;
use \vakata\database\DriverInterface;
use \vakata\database\ResultInterface;
use \vakata\collection\Collection;

class Result implements ResultInterface
{
    protected $statement;
    protected $last = null;
    protected $fetched = -1;

    public function __construct($statement)
    {
        $this->statement = $statement;
    }
    public function __destruct()
    {
        @oci_free_statement($this->statement);
    }
    public function affected() : int
    {
        return oci_num_rows($this->statement);
    }
    public function insertID()
    {
        return null;
    }
    public function toArray() : array
    {
        return iterator_to_array($this);
    }

    public function count()
    {
        return oci_num_rows($this->statement);
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
            if (!oci_execute($this->statement)) {
                $err = oci_error($this->statement);
                if (!$err) {
                    $err = [];
                }
                throw new DBException('Could not execute query : '.implode(',', $err));
            }
        }
        $this->last = null;
        $this->fetched = -1;
        $this->next();
    }
    public function next()
    {
        $this->fetched ++;
        $this->last = oci_fetch_array($this->statement, OCI_ASSOC + OCI_RETURN_NULLS + OCI_RETURN_LOBS);
    }
    public function valid()
    {
        return !!$this->last;
    }

    public function collection() : Collection
    {
        return new Collection($this);
    }
}