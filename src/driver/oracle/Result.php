<?php

namespace vakata\database\driver\oracle;

use \vakata\database\DBException;
use \vakata\database\DriverInterface;
use \vakata\database\ResultInterface;
use \vakata\collection\Collection;

class Result implements ResultInterface
{
    protected mixed $statement;
    protected ?array $last = null;
    protected int $fetched = -1;
    protected array $types = [];

    public function __construct(mixed $statement)
    {
        $this->statement = $statement;
    }
    public function __destruct()
    {
        \oci_free_statement($this->statement);
    }
    public function affected() : int
    {
        return (int)\oci_num_rows($this->statement);
    }
    public function insertID(string $sequence = null): mixed
    {
        return null;
    }
    public function toArray() : array
    {
        return iterator_to_array($this);
    }

    public function count(): int
    {
        return (int)\oci_num_rows($this->statement);
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
            if (!\oci_execute($this->statement)) {
                $err = \oci_error($this->statement);
                if (!is_array($err)) {
                    $err = [];
                }
                throw new DBException('Could not execute query : '.implode(',', $err));
            }
        }
        $this->last = null;
        $this->fetched = -1;
        $this->next();
    }
    public function next(): void
    {
        $this->fetched ++;
        $this->last = \oci_fetch_array($this->statement, \OCI_ASSOC + \OCI_RETURN_NULLS + \OCI_RETURN_LOBS)?:null;
        $this->cast();
    }
    public function valid(): bool
    {
        return !!$this->last;
    }
    protected function cast()
    {
        if (!count($this->types)) {
            foreach (array_keys($this->last) as $k => $v) {
                $this->types[$v] = \oci_field_type($this->statement, $k + 1);
                if ($this->types[$v] === 'NUMBER') {
                    $size = \oci_field_size($this->statement, $k + 1);
                    if ((int)(explode(',', $size, 2)[1] ?? '') > 0) {
                        $this->types[$v] === 'FLOAT';
                    }
                }
            }
        }
        foreach ($this->last as $k => $v) {
            if (is_null($v) || !isset($this->types[$k])) {
                continue;
            }
            switch ($this->types[$k]) {
                case 'NUMBER':
                    $this->last[$k] = (int)$v;
                    break;
                case 'FLOAT':
                    $this->last[$k] = (float)$v;
                    break;
            }
        }
    }
}
