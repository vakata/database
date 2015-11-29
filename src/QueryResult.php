<?php

namespace vakata\database;

class QueryResult
{
    protected $drv = null;
    protected $prp = null;
    protected $rsl = null;
    protected $row = null;
    protected $num = null;
    protected $aff = null;
    protected $iid = null;

    public function __construct(driver\DriverInterface $drv, $prp, $data = null)
    {
        $data = !is_null($data) && !is_array($data) ? [$data] : $data;
        $this->drv = $drv;
        $this->prp = $prp;
        $this->rsl = $this->drv->execute($this->prp, $data);
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
    public function __destruct()
    {
        if (is_object($this->rsl) || is_resource($this->rsl)) {
            $this->drv->free($this->rsl);
        }
    }
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
    public function affected()
    {
        return $this->aff;
    }
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
