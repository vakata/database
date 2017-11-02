<?php

namespace vakata\database\driver\odbc;

use \vakata\database\DBException;
use \vakata\database\DriverInterface;
use \vakata\database\ResultInterface;
use \vakata\collection\Collection;

class Result implements ResultInterface
{
    protected $statement;
    protected $data;
    protected $charIn;
    protected $charOut;
    protected $columns;
    protected $last = null;
    protected $fetched = -1;
    protected $iid = null;

    public function __construct($statement, $data, $iid, $charIn = null, $charOut = null)
    {
        $this->statement = $statement;
        $this->data = $data;
        $this->iid = $iid;
        $this->charIn = $charIn;
        $this->charOut = $charOut;
        $this->columns = [];
        $i = 0;
        try {
            while ($temp = \odbc_field_name($this->statement, ++$i)) {
                $this->columns[] = $temp;
            }
        } catch (\Exception $ignore) {
        }
    }
    public function __destruct()
    {
        \odbc_free_result($this->statement);
    }
    public function affected() : int
    {
        return \odbc_num_rows($this->statement);
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
        return \odbc_num_rows($this->statement);
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
            $temp = \odbc_execute($this->statement, $this->data);
            if (!$temp) {
                throw new DBException('Could not execute query : '.\odbc_errormsg());
            }
        }
        $this->last = null;
        $this->fetched = -1;
        $this->next();
    }
    public function next()
    {
        $this->fetched ++;
        $temp = \odbc_fetch_row($this->statement);
        if (!$temp) {
            $this->last = false;
        } else {
            $this->last = [];
            foreach ($this->columns as $col) {
                $this->last[$col] = \odbc_result($this->statement, $col);
            }
            $this->last = $this->convert($this->last);
        }
    }
    public function valid()
    {
        return !!$this->last;
    }
    protected function convert($data)
    {
        if (!is_callable("\iconv") || !isset($this->charIn) || !isset($this->charOut)) {
            return $data;
        }
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = $this->convert($v);
            }
            return $data;
        }
        if (is_string($data)) {
            return \iconv($this->charIn, $this->charOut, $data);
        }
        return $data;
    }
}