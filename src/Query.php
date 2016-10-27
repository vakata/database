<?php

namespace vakata\database;

/**
 * A simple database query wrapper class.
 * Do not create manually - the `\vakata\database\DB` class will create instances as needed.
 * An object of this type is returned by `\vakata\database\DB::prepare()`.
 */
class Query
{
    protected $drv = null;
    protected $sql = null;
    protected $prp = null;
    protected $rsl = null;

    public function __construct(driver\DriverInterface $drv, $sql)
    {
        $this->drv = $drv;
        $this->sql = $sql;
        $this->prp = $this->drv->prepare($sql);
    }
    /**
     * Execute the query, which was prepared using `\vakata\database\DB::prepare()`.
     * @param  array  $data optional parameter - the data needed for the query if it has placeholders
     * @return \vakata\database\QueryResult  the result of the query
     */
    public function execute($data = null)
    {
        return new QueryResult($this->drv, $this->prp, $data);
    }
}
