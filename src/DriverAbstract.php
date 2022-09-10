<?php

namespace vakata\database;

use \vakata\database\schema\Table;
use \vakata\database\schema\TableQuery;

abstract class DriverAbstract implements DriverInterface
{
    protected $connection;
    
    protected function expand(string $sql, $par = null) : array
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
    public function query(string $sql, $par = null, bool $buff = true) : ResultInterface
    {
        $par = isset($par) ? (is_array($par) ? $par : [$par]) : [];
        if (strpos($sql, '??') && count($par)) {
            list($sql, $par) = $this->expand($sql, $par);
        }
        return $this->prepare($sql)->execute($par, $buff);
    }
    public function name() : string
    {
        return $this->connection['name'];
    }
    public function option(string $key, $default = null)
    {
        return isset($this->connection['opts'][$key]) ? $this->connection['opts'][$key] : $default;
    }
    
    public function begin() : bool
    {
        $this->connect();
        try {
            $this->query("START TRANSACTION");
            return true;
        } catch (DBException $e) {
            return false;
        }
    }
    public function commit() : bool
    {
        try {
            $this->query("COMMIT");
            return true;
        } catch (DBException $e) {
            return false;
        }
    }
    public function rollback() : bool
    {
        try {
            $this->query("ROLLBACK");
            return true;
        } catch (DBException $e) {
            return false;
        }
    }
    public function raw(string $sql)
    {
        $this->connect();
        return $this->query($sql);
    }
    
    abstract public function connect();
    abstract public function prepare(string $sql, ?string $name = null) : StatementInterface;
    abstract public function test() : bool;
    public function disconnect()
    {
    }

    public function table(string $table, bool $detectRelations = true) : Table
    {
        throw new DBException('Not supported');
    }
    public function tables() : array
    {
        throw new DBException('Not supported');
    }
}
