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
    protected $drv;

    public function __construct(string $statement, $lnk, $drv)
    {
        $this->statement = $statement;
        $this->driver = $lnk;
        $this->drv = $drv;
    }
    public function execute(array $data = [], bool $buff = true) : ResultInterface
    {
        if (!is_array($data)) {
            $data = array();
        }
        $log = $this->drv->option('log_file');
        if ($log) {
            $tm = microtime(true);
        }
        $temp = (is_array($data) && count($data)) ?
            \pg_query_params($this->driver, $this->statement, $data) :
            \pg_query_params($this->driver, $this->statement, array());
        if (!$temp) {
            if ($log && (int)$this->drv->option('log_errors', 1)) {
                @file_put_contents(
                    $log,
                    '--' . date('Y-m-d H:i:s') . ' ERROR: ' . \pg_last_error($this->driver) . "\r\n" .
                    $this->statement . "\r\n" .
                    "\r\n",
                    FILE_APPEND
                );
            }
            throw new DBException('Could not execute query : '.\pg_last_error($this->driver).' <'.$this->statement.'>');
        }
        if ($log) {
            $tm = microtime(true) - $tm;
            if ($tm >= (float)$this->drv->option('log_slow', 0)) {
                @file_put_contents(
                    $log,
                    '--' . date('Y-m-d H:i:s') . ' ' . sprintf('%01.6f', $tm) . "s\r\n" .
                    $this->statement . "\r\n" .
                    "\r\n",
                    FILE_APPEND
                );
            }
        }
        $aff = 0;
        if (preg_match('@^\s*(INSERT|UPDATE|DELETE)\s+@i', $this->statement)) {
            $aff = @\pg_affected_rows($temp);
        }

        return new Result($temp, $this->driver, $aff);
    }
}
