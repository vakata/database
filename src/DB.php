<?php

namespace vakata\database;

/**
 * A database abstraction with support for various drivers (mySQL, postgre, oracle, msSQL, sphinx, and even PDO).
 */
class DB implements DatabaseInterface
{
    protected $drv = null;

    /**
     * Create an instance.
     *
     *
     * @throws \vakata\database\DatabaseException if invalid settings are provided
     *
     * @param string $drv a connection string (like `"mysqli://user:pass@host/database?option=value"`)
     */
    public function __construct($drv)
    {
        if (!is_string($drv) && !($drv instanceof Settings)) {
            throw new DatabaseException('Could not create database (no or invalid settings)');
        }
        if (is_string($drv)) {
            $drv = new Settings($drv);
        }
        if ($drv instanceof Settings) {
            try {
                $tmp = '\\vakata\\database\\driver\\'.ucfirst($drv->type);
                $drv = new $tmp($drv);
            } catch (\Exception $e) {
                throw new DatabaseException('Could not create database driver - '.$e);
            }
        }
        if (!($drv instanceof driver\DriverInterface)) {
            throw new DatabaseException('Invalid database driver');
        }
        $this->drv = $drv;
    }

    protected function expand($sql, $data)
    {
        $new = '';
        if (!is_array($data)) {
            $data = [ $data ];
        }
        $data = array_values($data);
        if (substr_count($sql, '?') === 2 && !is_array($data[0])) {
            $data = [ $data ];
        }
        $parts = explode('??', $sql);
        $index = 0;
        foreach ($parts as $part) {
            $tmp = explode('?', $part);
            $new .= $part;
            $index += count($tmp) - 1;
            if (isset($data[$index])) {
                if (!is_array($data[$index])) {
                    $data[$index] = [ $data[$index] ];
                }
                $params = $data[$index];
                array_splice($data, $index, 1, $params);
                $index += count($params);
                $new .= implode(',', array_fill(0, count($params), '?'));
            }
        }
        return [ $new, $data ];
    }

    /**
     * Prepare a statement.
     * Use only if you need a single query to be performed multiple times with different parameters.
     *
     *
     * @param string $sql the query to prepare - use `?` for arguments
     *
     * @return vakata\database\Query the prepared statement
     */
    public function prepare($sql)
    {
        try {
            return new Query($this->drv, $sql);
        } catch (\Exception $e) {
            throw new DatabaseException($e->getMessage(), 2);
        }
    }
    /**
     * Run a query (prepare & execute).
     *
     *
     * @param string $sql  SQL query
     * @param array  $data parameters
     *
     * @return \vakata\database\QueryResult the result of the execution
     */
    public function query($sql, $data = null)
    {
        try {
            if (strpos($sql, '??') && $data !== null) {
                list($sql, $data) = $this->expand($sql, $data);
            }
            return $this->prepare($sql)->execute($data);
        } catch (\Exception $e) {
            throw new DatabaseException($e->getMessage(), 4);
        }
    }
    /**
     * Run a SELECT query and get an array-like result.
     * When using `get` the data is kept in the database client and fetched as needed (not in PHP memory as with `all`)
     *
     *
     * @param string $sql      SQL query
     * @param array  $data     parameters
     * @param string $key      column name to use as the array index
     * @param bool   $skip     do not include the column used as index in the value (defaults to `false`)
     * @param string $mode     result mode - `"assoc"` by default, could be `"num"`, `"both"`, `"assoc_ci"`, `"assoc_lc"`, `"assoc_uc"`
     * @param bool   $opti     if a single column is returned - do not use an array wrapper (defaults to `true`)
     *
     * @return \vakata\database\Result the result of the execution - use as a normal array
     */
    public function get($sql, $data = null, $key = null, $skip = false, $mode = 'assoc', $opti = true)
    {
        if (strpos($sql, '??') && $data !== null) {
            list($sql, $data) = $this->expand($sql, $data);
        }
        return (new Query($this->drv, $sql))->execute($data)->result($key, $skip, $mode, $opti);
    }
    /**
     * Run a SELECT query and get an array result.
     *
     *
     * @param string $sql      SQL query
     * @param array  $data     parameters
     * @param string $key      column name to use as the array index
     * @param bool   $skip     do not include the column used as index in the value (defaults to `false`)
     * @param string $mode     result mode - `"assoc"` by default, could be `"num"`, `"both"`, `"assoc_ci"`, `"assoc_lc"`, `"assoc_uc"`
     * @param bool   $opti     if a single column is returned - do not use an array wrapper (defaults to `true`)
     *
     * @return array the result of the execution
     */
    public function all($sql, $data = null, $key = null, $skip = false, $mode = 'assoc', $opti = true)
    {
        return $this->get($sql, $data, $key, $skip, $mode, $opti)->get();
    }
    /**
     * Run a SELECT query and get the first row.
     *
     *
     * @param string $sql      SQL query
     * @param array  $data     parameters
     * @param string $mode     result mode - `"assoc"` by default, could be `"num"`, `"both"`, `"assoc_ci"`, `"assoc_lc"`, `"assoc_uc"`
     * @param bool   $opti     if a single column is returned - do not use an array wrapper (defaults to `true`)
     *
     * @return mixed the result of the execution
     */
    public function one($sql, $data = null, $mode = 'assoc', $opti = true)
    {
        return $this->get($sql, $data, null, false, $mode, $opti)->one();
    }
    /**
     * Run a raw SQL query
     *
     *
     * @param string $sql      SQL query
     *
     * @return mixed the result of the execution
     */
    public function raw($sql, $data = null, $mode = 'assoc', $opti = true)
    {
        return $this->drv->real($sql);
    }
    /**
     * Get the current driver name (`"mysqli"`, `"postgre"`, etc).
     *
     *
     * @return string the current driver name
     */
    public function driver()
    {
        return $this->drv->settings()->type;
    }
    /**
     * Get the current database name.
     *
     *
     * @return string the current database name
     */
    public function name()
    {
        return $this->drv->settings()->database;
    }
    /**
     * Begin a transaction.
     *
     *
     * @return bool `true` if a transaction was opened, `false` otherwise
     */
    public function begin()
    {
        if ($this->drv->isTransaction()) {
            return false;
        }

        return $this->drv->begin();
    }
    /**
     * Commit a transaction.
     *
     *
     * @return bool was the commit successful
     */
    public function commit($isTransaction = true)
    {
        return $isTransaction && $this->drv->isTransaction() && $this->drv->commit();
    }
    /**
     * Rollback a transaction.
     *
     *
     * @return bool was the rollback successful
     */
    public function rollback($isTransaction = true)
    {
        return $isTransaction && $this->drv->isTransaction() && $this->drv->rollback();
    }
    /**
     * Check if a transaciton is currently open.
     *
     *
     * @return bool is a transaction currently open
     */
    public function isTransaction()
    {
        return $this->drv->isTransaction();
    }
}
