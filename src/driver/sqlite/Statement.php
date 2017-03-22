<?php

namespace vakata\database\driver\sqlite;

use \vakata\database\DBException;
use \vakata\database\DriverInterface;
use \vakata\database\StatementInterface;
use \vakata\database\ResultInterface;

class Statement implements StatementInterface
{
    protected $statement;
    protected $driver;

    public function __construct(\SQLite3Stmt $statement, \SQLite3 $driver)
    {
        $this->statement = $statement;
        $this->driver = $driver;
    }
    public function execute(array $data = []) : ResultInterface
    {
        $data = array_values($data);
        if ($this->statement->paramCount()) {
            if (count($data) < $this->statement->paramCount()) {
                throw new DBException('Prepared execute - not enough parameters.');
            }
            foreach ($data as $i => $v) {
                switch (gettype($v)) {
                    case 'boolean':
                    case 'integer':
                        $this->statement->bindValue(':i'.$i, (int) $v, \SQLITE3_INTEGER);
                        break;
                    case 'double':
                        $this->statement->bindValue(':i'.$i, $v, \SQLITE3_FLOAT);
                        break;
                    case 'array':
                        $this->statement->bindValue(':i'.$i, implode(',', $v), \SQLITE3_TEXT);
                        break;
                    case 'object':
                        $this->statement->bindValue(':i'.$i, serialize($v), \SQLITE3_TEXT);
                        break;
                    case 'resource':
                        if (is_resource($v) && get_resource_type($v) === 'stream') {
                            $this->statement->bindValue(':i'.$i, stream_get_contents($v), \SQLITE3_TEXT);
                        } else {
                            $this->statement->bindValue(':i'.$i, serialize($v), \SQLITE3_TEXT);
                        }
                        break;
                    case 'NULL':
                        $this->statement->bindValue(':i'.$i, null, \SQLITE3_NULL);
                        break;
                    default:
                        $this->statement->bindValue(':i'.$i, (string) $v, \SQLITE3_TEXT);
                        break;
                }
            }
        }
        $rtrn = $this->statement->execute();
        if (!$rtrn) {
            throw new DBException('Prepared execute error : '.$this->driver->lastErrorMsg());
        }
        return new Result($rtrn, $this->driver->lastInsertRowID(), $this->driver->changes());
    }
}