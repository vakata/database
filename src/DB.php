<?php

namespace vakata\database;

class DB implements DatabaseInterface
{
    protected $drv = null;

    /**
     * Create an instance.
     *
     * @method __construct
     *
     * @param mixed $drv Driver instance or connection string (DSN)
     */
    public function __construct($drv = null)
    {
        if (!$drv) {
            throw new DatabaseException('Could not create database (no settings)');
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
    /**
     * Prepare a statement.
     * Use only if you need a single query to be performed multiple times with different parameters
     *
     * @method prepare
     *
     * @param String $sql The query to prepare - use ? for arguments
     *
     * @return Query The prepared statement
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
     * @method query
     *
     * @param string $sql  SQL query
     * @param array  $data parameters
     *
     * @return QueryResult The result of the execution
     */
    public function query($sql, $data = null)
    {
        try {
            return $this->prepare($sql)->execute($data);
        } catch (\Exception $e) {
            throw new DatabaseException($e->getMessage(), 4);
        }
    }
    /**
     * Run a SELECT query and get an array-like result
     *
     * @method get
     *
     * @param string $sql      SQL query
     * @param array  $data     Parameters
     * @param string $key      Column name to use as the array index
     * @param bool   $skip     Do not include the column used as index in the value (defaults to false)
     * @param string $mode     Result mode - "assoc" by default, but "num" can be used
     * @param bool   $opti     If a single column is returned - do not use an array wrapper (defaults to true)
     *
     * @return ArrayLike The result of the execution
     */
    public function get($sql, $data = null, $key = null, $skip = false, $mode = 'assoc', $opti = true)
    {
        return (new Query($this->drv, $sql))->execute($data)->result($key, $skip, $mode, $opti);
    }
    /**
     * Run a SELECT query and get an array result
     *
     * @method all
     *
     * @param string $sql      SQL query
     * @param array  $data     Parameters
     * @param string $key      Column name to use as the array index
     * @param bool   $skip     Do not include the column used as index in the value (defaults to false)
     * @param string $mode     Result mode - "assoc" by default, but "num" can be used
     * @param bool   $opti     If a single column is returned - do not use an array wrapper (defaults to true)
     *
     * @return array the result of the execution
     */
    public function all($sql, $data = null, $key = null, $skip = false, $mode = 'assoc', $opti = true)
    {
        return $this->get($sql, $data, $key, $skip, $mode, $opti)->get();
    }
    /**
     * Run a SELECT query and get the first row
     *
     * @method one
     *
     * @param string $sql      SQL query
     * @param array  $data     Parameters
     * @param string $mode     Result mode - "assoc" by default, but "num" can be used
     * @param bool   $opti     If a single column is returned - do not use an array wrapper (defaults to true)
     *
     * @return mixed the result of the execution
     */
    public function one($sql, $data = null, $mode = 'assoc', $opti = true)
    {
        return $this->get($sql, $data, null, false, $mode, $opti)->one();
    }
    /**
     * Get the current driver name
     *
     * @method driver
     *
     * @return string the current driver name
     */
    public function driver()
    {
        return $this->drv->settings()->type;
    }
    /**
     * Begin a transaction
     *
     * @method begin
     *
     * @return bool true if a transaction was opened, false otherwise
     */
    public function begin()
    {
        if ($this->drv->isTransaction()) {
            return false;
        }

        return $this->drv->begin();
    }
    /**
     * Commit a transaction
     *
     * @method commit
     *
     * @return bool was the commit successful
     */
    public function commit($isTransaction = true)
    {
        return $isTransaction && $this->drv->isTransaction() && $this->drv->commit();
    }
    /**
     * Rollback a transaction
     *
     * @method rollback
     *
     * @return bool was the rollback successful
     */
    public function rollback($isTransaction = true)
    {
        return $isTransaction && $this->drv->isTransaction() && $this->drv->rollback();
    }
    /**
     * Is a transaction open
     *
     * @method isTransaction
     *
     * @return bool open
     */
    public function isTransaction()
    {
        $this->drv->isTransaction();
    }
}
