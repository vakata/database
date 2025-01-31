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
    public function insertID(?string $sequence = null): mixed
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
        if (is_array($this->last)) {
            $this->cast();
        }
    }
    public function valid(): bool
    {
        return !!$this->last;
    }
    protected function cast(): void
    {
        if (!count($this->types)) {
            foreach (array_keys($this->last??[]) as $k => $v) {
                $this->types[$v] = \oci_field_type($this->statement, $k + 1);
                if ($this->types[$v] === 'NUMBER') {
                    $scale = \oci_field_scale($this->statement, $k + 1);
                    $precision = \oci_field_precision($this->statement, $k + 1);
                    // true float
                    if ((int)$scale === -127 && (int)$precision !== 0) {
                        $this->types[$v] = 'FLOAT';
                    }
                    // TODO: decimal
                    if ((int)$scale > 0) {
                        $this->types[$v] = 'DECIMAL';
                    }
                }
            }
        }
        foreach ($this->last??[] as $k => $v) {
            if (!isset($this->types[$k])) {
                continue;
            }
            if (is_null($v) &&
                (strpos($this->types[$k], 'CHAR') !== false || strpos($this->types[$k], 'CLOB') !== false)
            ) {
                $this->last[$k] = '';
                continue;
            }
            if (is_null($v)) {
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
