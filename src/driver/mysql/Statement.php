<?php

namespace vakata\database\driver\mysql;

use \vakata\database\DBException;
use \vakata\database\DriverInterface;
use \vakata\database\StatementInterface;
use \vakata\database\ResultInterface;

class Statement implements StatementInterface
{
    protected $statement;

    public function __construct(\mysqli_stmt $statement)
    {
        $this->statement = $statement;
    }
    public function execute(array $data = []) : ResultInterface
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
                            continue;
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
            call_user_func_array(array($this->statement, 'bind_param'), $ref);
            foreach ($lng as $index) {
                if (is_resource($data[$index]) && get_resource_type($data[$index]) === 'stream') {
                    while (!feof($data[$index])) {
                        $this->statement->send_long_data($index, fread($data[$index], $lds));
                    }
                } else {
                    $data[$index] = str_split($data[$index], $lds);
                    foreach ($data[$index] as $chunk) {
                        $this->statement->send_long_data($index, $chunk);
                    }
                }
            }
        }
        if (!$this->statement->execute()) {
            throw new DBException('Prepared execute error : '.$this->lnk->error);
        }
        return new Result($this->statement);
    }
}