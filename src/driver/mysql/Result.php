<?php

namespace vakata\database\driver\mysql;

use mysqli_stmt;
use \vakata\database\DriverInterface;
use \vakata\database\ResultInterface;
use \vakata\collection\Collection;

class Result implements ResultInterface
{
    protected mysqli_stmt $statement;
    protected array $row = [];
    protected ?array $last = null;
    protected int $fetched = -1;
    protected mixed $result = null;
    protected bool $nativeDriver = false;

    public function __construct(mysqli_stmt $statement, bool $buff = true)
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
        return (int)$this->statement->affected_rows;
    }
    public function insertID(string $sequence = null): mixed
    {
        return $this->statement->insert_id;
    }
    public function toArray() : array
    {
        return iterator_to_array($this);
    }

    public function count(): int
    {
        return $this->nativeDriver && $this->result ? $this->result->num_rows : $this->statement->num_rows;
    }

    public function key(): mixed
    {
        return $this->fetched;
    }
    public function current(): mixed
    {
        return $this->nativeDriver ? $this->last : array_map(function ($v) {
            return $v;
        }, $this->row);
    }
    public function rewind(): void
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
    public function next(): void
    {
        $this->fetched ++;
        $this->last = $this->nativeDriver ? ($this->result->fetch_assoc()?:null) : $this->statement->fetch();
    }
    public function valid(): bool
    {
        return !!$this->last;
    }
}
