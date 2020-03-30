<?php

namespace vakata\database\driver\postgre;

use \vakata\database\DBException;
use \vakata\database\DriverInterface;
use \vakata\database\StatementInterface;
use \vakata\database\ResultInterface;

class Statement implements StatementInterface
{
    protected $statement;
    protected $driver;

    public function __construct(string $statement, $driver)
    {
        $this->statement = $statement;
        $this->driver = $driver;
    }
    public function execute(array $data = [], bool $buff = true) : ResultInterface
    {
        if (!is_array($data)) {
            $data = array();
        }
        $temp = (is_array($data) && count($data)) ?
            \pg_query_params($this->driver, $this->statement, $data) :
            \pg_query_params($this->driver, $this->statement, array());
        if (!$temp) {
            throw new DBException('Could not execute query : '.\pg_last_error($this->driver).' <'.$this->statement.'>');
        }
        $iid = null;
        $aff = 0;
        if (preg_match('@^\s*(INSERT|REPLACE)\s+INTO@i', $this->statement)) {
            $iid = @\pg_query($this->driver, 'SELECT lastval()');
            if ($iid) {
                $res = \pg_fetch_row($iid);
                $iid = $res[0];
            }
        }
        if (preg_match('@^\s*(INSERT|REPLACE|UPDATE|DELETE)\s+@i', $this->statement)) {
            $aff = @\pg_affected_rows($temp);
        }

        return new Result($temp, $iid, $aff);
    }
}
