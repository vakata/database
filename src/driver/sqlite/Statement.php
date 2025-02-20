<?php

namespace vakata\database\driver\sqlite;

use SQLite3;
use SQLite3Stmt;
use \vakata\database\DBException;
use \vakata\database\DriverInterface;
use \vakata\database\StatementInterface;
use \vakata\database\ResultInterface;

class Statement implements StatementInterface
{
    protected SQLite3Stmt $statement;
    protected SQLite3 $driver;
    protected ?array $map = null;

    public function __construct(SQLite3Stmt $statement, SQLite3 $driver, ?array $map = null)
    {
        $this->statement = $statement;
        $this->driver = $driver;
        $this->map = $map;
    }
    public function execute(array $data = [], bool $buff = true) : ResultInterface
    {
        if (isset($this->map)) {
            $par = [];
            foreach ($this->map as $key) {
                $par[] = $data[$key] ?? throw new DBException('Missing param ' . $key);
            }
            $data = $par;
        }
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
        try {
            $rtrn = $this->statement->execute();
        } catch (\Exception $e) {
            $rtrn = false;
        }
        if (!$rtrn) {
            throw new DBException('Prepared execute error : '.$this->driver->lastErrorMsg());
        }
        return new Result($rtrn, $this->driver->lastInsertRowID(), $this->driver->changes());
    }
}
