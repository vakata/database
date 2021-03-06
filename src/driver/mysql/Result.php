<?php

namespace vakata\database\driver\mysql;

use \vakata\database\DriverInterface;
use \vakata\database\ResultInterface;
use \vakata\collection\Collection;

class Result implements ResultInterface
{
    protected $statement;
    protected $row = [];
    protected $last = null;
    protected $fetched = -1;
    protected $result = null;
    protected $nativeDriver = false;

    public function __construct(\mysqli_stmt $statement, bool $buff = true)
    {
        $this->nativeDriver = $buff && function_exists('\mysqli_fetch_all');
        $this->statement = $statement;
        try {
            if ($this->nativeDriver) {
                $this->result = $this->statement->get_result();
            } else {
                if ($buff) {
                    $this->statement->store_result();
                }
                $columns = [];
                $temp = $this->statement->result_metadata();
                if ($temp) {
                    $temp = $temp->fetch_fields();
                    if ($temp) {
                        $columns = array_map(function ($v) {
                            return $v->name;
                        }, $temp);
                    }
                }
                if (count($columns)) {
                    $this->row = array_combine($columns, array_fill(0, count($columns), null));
                    if ($this->row !== false) {
                        $temp = [];
                        foreach ($this->row as $k => $v) {
                            $temp[] = &$this->row[$k];
                        }
                        call_user_func_array(array($this->statement, 'bind_result'), $temp);
                    }
                }
            }
        } catch (\Exception $ignore) {
        }
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
        return $this->nativeDriver && $this->result ? $this->result->num_rows : $this->statement->num_rows;
    }

    public function key()
    {
        return $this->fetched;
    }
    public function current()
    {
        return $this->nativeDriver ? $this->last : array_map(function ($v) {
            return $v;
        }, $this->row);
    }
    public function rewind()
    {
        if ($this->fetched >= 0) {
            if ($this->nativeDriver) {
                $this->result->data_seek(0);
            } else {
                $this->statement->data_seek(0);
            }
        }
        $this->last = null;
        $this->fetched = -1;
        $this->next();
    }
    public function next()
    {
        $this->fetched ++;
        $this->last = $this->nativeDriver ? $this->result->fetch_assoc() : $this->statement->fetch();
    }
    public function valid()
    {
        return !!$this->last;
    }
}
