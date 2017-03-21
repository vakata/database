<?php

namespace vakata\database;

use \vakata\database\schema\Table;
use \vakata\database\schema\TableQuery;

abstract class DriverAbstract implements DriverInterface
{
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
     * @param array  $data parameters (optional)
     * @return ResultInterface the result of the execution
     */
    public function query(string $sql, $par = null) : ResultInterface
    {
        $par = isset($par) ? (is_array($par) ? $par : [$par]) : [];
        if (strpos($sql, '??') && count($par)) {
            list($sql, $par) = $this->expand($sql, $par);
        }
        return $this->prepare($sql)->execute($par);
    }
    public function name() : string
    {
        return $this->connection['name'];
    }
    public function option($key, $default = null)
    {
        return isset($this->connection['opts'][$key]) ? $this->connection['opts'][$key] : $default;
    }
    
    public function begin() : bool
    {
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
    abstract public function prepare(string $sql) : StatementInterface;
    abstract public function table(string $table, bool $detectRelations = true) : Table;
    abstract public function tables() : array;
}