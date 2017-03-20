<?php

namespace vakata\database\mysql;

use \vakata\database\DriverInterface;
use \vakata\database\ResultInterface;
use \vakata\collection\Collection;

class Result implements ResultInterface
{
    protected $statement;
    protected $row = [];
    protected $last = null;
    protected $fetched = -1;

    public function __construct(\mysqli_stmt $statement)
    {
        $this->statement = $statement;
        try {
            $columns = [];
            $temp = $this->statement->result_metadata();
            if ($temp) {
                $temp = $temp->fetch_fields();
                if ($temp) {
                    $columns = array_map(function ($v) { return $v->name; }, $temp);
                }
            }
            if (count($columns)) {
                $this->row = array_combine($columns, array_fill(0, count($columns), null));
                $temp = [];
                foreach ($this->row as $k => $v) {
                    $temp[] = &$this->row[$k];
                }
                call_user_func_array(array($this->statement, 'bind_result'), $temp);
            }
        } catch (\Exception $e) { }
    }
    public function __destruct()
    {
        $this->statement->free_result();
        $this->statement->close();
    }
    public function affected() : int
    {
        return $this->statement->affected_rows;
    }
    public function insertID()
    {
        return $this->statement->insert_id;
    }
    public function toArray() : array
    {
        return iterator_to_array($this);
    }

    public function count()
    {
        return $this->statement->num_rows;
    }

    public function key()
    {
        return ++ $this->fetched;
    }
    public function current()
    {
        return array_map(function ($v) { return $v; }, $this->row);
    }
    public function rewind()
    {
        if ($this->fetched >= 0) {
            $this->statement->data_seek(0);
        }
        $this->last = null;
        $this->fetched = -1;
        $this->next();
    }
    public function next()
    {
        $this->last = $this->statement->fetch();
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