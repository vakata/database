<?php

namespace vakata\database;

use \vakata\database\schema\Table;

abstract class DriverAbstract implements DriverInterface
{
    protected array $connection;
    protected int $softTransaction = 0;
    
    protected function expand(string $sql, mixed $par = null): array
    {
        $new = '';
        $par = array_values($par);
        if (substr_count($sql, '?') === 2 && !is_array($par[0])) {
            $par = [ $par ];
        }
        $parts = explode('??', $sql);
        $index = 0;
        foreach ($parts as $part) {
            $tmp = explode('?', $part);
            $new .= $part;
            $index += count($tmp) - 1;
            if (isset($par[$index])) {
                if (!is_array($par[$index])) {
                    $par[$index] = [ $par[$index] ];
                }
                $params = $par[$index];
                array_splice($par, $index, 1, $params);
                $index += count($params);
                $new .= implode(',', array_fill(0, count($params), '?'));
            }
        }
        return [ $new, $par ];
    }
    /**
     * Run a query (prepare & execute).
     * @param string $sql  SQL query
     * @param mixed  $par  parameters (optional)
     * @return ResultInterface the result of the execution
     */
    public function query(string $sql, mixed $par = null, bool $buff = true): ResultInterface
    {
        $this->softDetect($sql);
        $par = isset($par) ? (is_array($par) ? $par : [$par]) : [];
        if (strpos($sql, '??') && count($par)) {
            list($sql, $par) = $this->expand($sql, $par);
        }
        return $this->prepare($sql)->execute($par, $buff);
    }
    public function name(): string
    {
        return $this->connection['name'];
    }
    public function option(string $key, mixed $default = null): mixed
    {
        return isset($this->connection['opts'][$key]) ? $this->connection['opts'][$key] : $default;
    }
    
    public function begin() : bool
    {
        $this->connect();
        if ($this->softTransaction === 1) {
            $this->softTransaction = 0;
        }
        try {
            $this->query("START TRANSACTION");
            return true;
        } catch (DBException $e) {
            return false;
        }
    }
    public function commit() : bool
    {
        if ($this->softTransaction) {
            $this->softTransaction = 0;
        }
        try {
            $this->query("COMMIT");
            return true;
        } catch (DBException $e) {
            return false;
        }
    }
    public function rollback() : bool
    {
        if ($this->softTransaction) {
            $this->softTransaction = 0;
        }
        try {
            $this->query("ROLLBACK");
            return true;
        } catch (DBException $e) {
            return false;
        }
    }

    public function softBegin(): void
    {
        if (!property_exists($this, 'transaction') || !$this->{'transaction'}) {
            $this->softTransaction = max($this->softTransaction, 1);
        }
    }
    public function softCommit(): void
    {
        if ($this->softTransaction > 1) {
            $this->commit();
        }
        $this->softTransaction = 0;
    }
    public function softRollback(): void
    {
        if ($this->softTransaction > 1) {
            $this->rollback();
        }
        $this->softTransaction = 0;
    }
    public function softDetect(string $sql): void
    {
        if ($this->softTransaction === 1 && preg_match('(^(BEGIN|ROLLBACK|COMMIT))i', $sql)) {
            $this->softTransaction = 0;
        }
        if ($this->softTransaction === 1 && preg_match('(^(INSERT|UPDATE|DELETE|REPLACE) )i', $sql)) {
            $this->softTransaction = 2;
            $this->begin();
        }
    }

    public function raw(string $sql): mixed
    {
        $this->connect();
        $this->softDetect($sql);
        return $this->query($sql);
    }
    
    abstract public function connect(): void;
    abstract public function prepare(string $sql, ?string $name = null) : StatementInterface;
    abstract public function test() : bool;
    public function disconnect(): void
    {
    }

    public function table(string $table, bool $detectRelations = true): Table
    {
        throw new DBException('Not supported');
    }
    public function tables(): array
    {
        throw new DBException('Not supported');
    }
}
