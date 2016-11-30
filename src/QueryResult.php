<?php

namespace vakata\database;

/**
 * A wrapper class for the result of a query.
 * Do not create manually - the `\vakata\database\DB` class will create instances as needed.
 * An object of this type is returned by `\vakata\database\DB::query()` used for `UPDATE / INSERT / DELETE` queries
 */
class QueryResult
{
    protected $drv = null;
    protected $prp = null;
    protected $rsl = null;
    protected $row = null;
    protected $num = null;
    protected $aff = null;
    protected $iid = null;
    protected $dat = null;

    public function __construct(driver\DriverInterface $drv, $prp, $data = null)
    {
        $this->dat = !is_null($data) && !is_array($data) ? [$data] : $data;
        $this->drv = $drv;
        $this->prp = $prp;
        $this->reset();
    }
    public function __destruct()
    {
        if (is_object($this->rsl) || is_resource($this->rsl)) {
            $this->drv->free($this->rsl);
        }
    }
    public function reset()
    {
        $this->rsl = $this->drv->execute($this->prp, $this->dat);
        $this->num = (is_object($this->rsl) || is_resource($this->rsl)) && $this->drv->countable() ?
            (int) @$this->drv->count($this->rsl) :
            0;
        $this->aff = $this->drv->affected();
        try {
            $this->iid = $this->drv->insertId();
        } catch (\Exception $e) {
            $this->iid = null;
        }
    }
    /**
     * Get an array-like result.
     * Instead of using this method - call `\vakata\database\DB::get()` and `\vakata\database\DB::all()`
     *
     *
     * @param string $key      column name to use as the array index
     * @param bool   $skip     do not include the column used as index in the value (defaults to `false`)
     * @param string $mode     result mode - `"assoc"` by default, could be `"num"`, `"both"`, `"assoc_ci"`, `"assoc_lc"`, `"assoc_uc"`
     * @param bool   $opti     if a single column is returned - do not use an array wrapper (defaults to `true`)
     *
     * @return \vakata\database\Result the result of the execution - use as a normal array
     */
    public function result($key = null, $skip = false, $mode = 'assoc', $opti = true)
    {
        return new Result($this, $key, $skip, $mode, $opti);
    }
    public function row()
    {
        return $this->row;
    }
    public function nextr()
    {
        $this->row = $this->drv->nextr($this->rsl);

        return $this->row !== false && $this->row !== null;
    }
    public function seek($offset)
    {
        return @$this->drv->seek($this->rsl, $offset) ? true : false;
    }
    public function count()
    {
        return $this->num;
    }
    /**
     * The number of rows affected by the query
     * @return int   the number of affected rows
     */
    public function affected()
    {
        return $this->aff;
    }
    /**
     * The last inserted ID in the current session.
     * @param  string   $name optional parameter for drivers which need a sequence name (oracle for example)
     * @return mixed         the last created ID
     */
    public function insertId($name = null)
    {
        return $this->iid ? $this->iid : $this->drv->insertId($name);
    }
    public function seekable()
    {
        return $this->drv->seekable();
    }
    public function countable()
    {
        return $this->drv->countable();
    }
}
