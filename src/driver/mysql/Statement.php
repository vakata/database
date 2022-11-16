<?php

namespace vakata\database\driver\mysql;

use mysqli_stmt;
use \vakata\database\DBException;
use \vakata\database\DriverInterface;
use \vakata\database\StatementInterface;
use \vakata\database\ResultInterface;

class Statement implements StatementInterface
{
    protected mysqli_stmt $statement;
    protected Driver $driver;
    protected string $sql;

    public function __construct(\mysqli_stmt $statement, Driver $driver, string $sql = '')
    {
        $this->statement = $statement;
        $this->driver = $driver;
        $this->sql = $sql;
    }
    public function __destruct()
    {
        // used to close statement here
    }
    public function execute(array $data = [], bool $buff = true) : ResultInterface
    {
        $data = array_values($data);
        $this->statement->reset();
        if ($this->statement->param_count) {
            if (count($data) < $this->statement->param_count) {
                throw new DBException('Prepared execute - not enough parameters.');
            }
            $lds = 32 * 1024;
            $ref = array('');
            $lng = array();
            $nul = null;
            foreach ($data as $i => $v) {
                switch (gettype($v)) {
                    case 'boolean':
                    case 'integer':
                        $data[$i] = (int) $v;
                        $ref[0] .= 'i';
                        $ref[$i + 1] = &$data[$i];
                        break;
                    case 'NULL':
                        $ref[0] .= 's';
                        $ref[$i + 1] = &$data[$i];
                        break;
                    case 'double':
                        $ref[0] .= 'd';
                        $ref[$i + 1] = &$data[$i];
                        break;
                    default:
                        if (is_resource($data[$i]) && get_resource_type($data[$i]) === 'stream') {
                            $ref[0] .= 'b';
                            $ref[$i + 1] = &$nul;
                            $lng[] = $i;
                            break;
                        }
                        if (!is_string($data[$i])) {
                            $data[$i] = serialize($data[$i]);
                        }
                        if (strlen($data[$i]) > $lds) {
                            $ref[0] .= 'b';
                            $ref[$i + 1] = &$nul;
                            $lng[] = $i;
                        } else {
                            $ref[0] .= 's';
                            $ref[$i + 1] = &$data[$i];
                        }
                        break;
                }
            }
            $this->statement->bind_param(...$ref);
            foreach ($lng as $index) {
                if (is_resource($data[$index]) && get_resource_type($data[$index]) === 'stream') {
                    while (!feof($data[$index])) {
                        $this->statement->send_long_data($index, (string)fread($data[$index], $lds));
                    }
                } else {
                    $data[$index] = str_split($data[$index], $lds);
                    foreach ($data[$index] as $chunk) {
                        $this->statement->send_long_data($index, $chunk);
                    }
                }
            }
        }
        $log = $this->driver->option('log_file');
        if ($log) {
            $tm = microtime(true);
        }
        if (!$this->statement->execute()) {
            if ($log && (int)$this->driver->option('log_errors', 1)) {
                @file_put_contents(
                    $log,
                    '--' . date('Y-m-d H:i:s') . ' ERROR: ' . $this->statement->error . "\r\n" .
                    $this->sql . "\r\n" .
                    "\r\n",
                    FILE_APPEND
                );
            }
            throw new DBException('Prepared execute error: ' . $this->statement->error);
        }
        if ($log) {
            $tm = microtime(true) - $tm;
            if ($tm >= (float)$this->driver->option('log_slow', 0)) {
                @file_put_contents(
                    $log,
                    '--' . date('Y-m-d H:i:s') . ' ' . sprintf('%01.6f', $tm) . "s\r\n" .
                    $this->sql . "\r\n" .
                    "\r\n",
                    FILE_APPEND
                );
            }
        }
        return new Result($this->statement, $buff);
    }
}
