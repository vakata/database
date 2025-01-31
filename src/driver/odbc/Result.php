<?php

namespace vakata\database\driver\odbc;

use \vakata\database\DBException;
use \vakata\database\DriverInterface;
use \vakata\database\ResultInterface;
use \vakata\collection\Collection;

class Result implements ResultInterface
{
    protected mixed $statement;
    protected array $data;
    protected ?string $charIn;
    protected ?string $charOut;
    protected array $columns;
    protected ?array $last = null;
    protected int $fetched = -1;
    protected mixed $iid = null;

    public function __construct(
        mixed $statement,
        array $data,
        mixed $iid,
        ?string $charIn = null,
        ?string $charOut = null
    ) {
        $this->statement = $statement;
        $this->data = $data;
        $this->iid = $iid;
        $this->charIn = $charIn;
        $this->charOut = $charOut;
        $this->columns = [];
        $i = 0;
        try {
            while ($temp = @\odbc_field_name($this->statement, ++$i)) {
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
    public function insertID(?string $sequence = null): mixed
    {
        return $this->iid;
    }
    public function toArray() : array
    {
        return iterator_to_array($this);
    }

    public function count(): int
    {
        return \odbc_num_rows($this->statement);
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
            $temp = \odbc_execute($this->statement, $this->data);
            if (!$temp) {
                throw new DBException('Could not execute query : '.\odbc_errormsg());
            }
        }
        $this->last = null;
        $this->fetched = -1;
        $this->next();
    }
    public function next(): void
    {
        $this->fetched ++;
        $temp = \odbc_fetch_row($this->statement);
        if (!$temp) {
            $this->last = null;
        } else {
            $this->last = [];
            foreach ($this->columns as $col) {
                $this->last[$col] = \odbc_result($this->statement, $col);
            }
            $this->last = $this->convert($this->last);
        }
    }
    public function valid(): bool
    {
        return !!$this->last;
    }
    protected function convert(mixed $data): mixed
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
