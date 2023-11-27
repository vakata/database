<?php

namespace vakata\database\driver\postgre;

use \vakata\database\DBException;
use \vakata\database\DriverInterface;
use \vakata\database\StatementInterface;
use \vakata\database\ResultInterface;

class Statement implements StatementInterface
{
    protected string $statement;
    protected mixed $driver;
    protected Driver $drv;
    protected ?string $name;

    public function __construct(string $statement, mixed $lnk, Driver $drv, ?string $name = null)
    {
        $this->statement = $statement;
        $this->driver = $lnk;
        $this->drv = $drv;
        $this->name = $name;
        if (strpos(strtolower($statement), 'prepare') === 0) {
            $this->drv->raw($this->statement);
            if (!isset($this->name)) {
                $this->name = trim((preg_split('(\s+)', trim($this->statement))?:[])[1]??'', '"');
            }
        } elseif ($this->name !== null) {
            $temp = \pg_prepare($this->driver, $this->name, $this->statement);
            if (!$temp) {
                $log = $this->drv->option('log_file');
                if ($log && (int)$this->drv->option('log_errors', 1)) {
                    @file_put_contents(
                        $log,
                        '--' . date('Y-m-d H:i:s') . ' ERROR PREPARING: ' . \pg_last_error($this->driver) . "\r\n" .
                        $this->statement . "\r\n" .
                        "\r\n",
                        FILE_APPEND
                    );
                }
                throw new DBException(
                    'Could not prepare query : '.\pg_last_error($this->driver).' <'.$this->statement.'>'
                );
            }
        }
    }
    public function __destruct()
    {
        if ($this->name !== null) {
            @\pg_query($this->driver, "DEALLOCATE ".\pg_escape_identifier($this->driver, $this->name));
        }
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
        try {
            if ($this->name !== null) {
                $temp = (is_array($data) && count($data)) ?
                    \pg_execute($this->driver, $this->name, $data) :
                    \pg_execute($this->driver, $this->name, array());
            } else {
                $temp = (is_array($data) && count($data)) ?
                    \pg_query_params($this->driver, $this->statement, $data) :
                    \pg_query_params($this->driver, $this->statement, array());
            }
        } catch (\Exception $e) {
            $temp = false;
        }
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
