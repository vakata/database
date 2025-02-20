<?php

namespace vakata\database\driver\pdo;

use PDO;
use PDOStatement;
use \vakata\database\DBException;
use \vakata\database\DriverInterface;
use \vakata\database\StatementInterface;
use \vakata\database\ResultInterface;

class Statement implements StatementInterface
{
    protected PDOStatement $statement;
    protected PDO $driver;
    protected ?array $map = null;

    public function __construct(PDOStatement $statement, PDO $driver, ?array $map = null)
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
        foreach ($data as $i => $v) {
            switch (gettype($v)) {
                case 'boolean':
                    $this->statement->bindValue($i+1, $v, \PDO::PARAM_BOOL);
                    break;
                case 'integer':
                    $this->statement->bindValue($i+1, $v, \PDO::PARAM_INT);
                    break;
                case 'NULL':
                    $this->statement->bindValue($i+1, $v, \PDO::PARAM_NULL);
                    break;
                case 'double':
                    $this->statement->bindValue($i+1, $v);
                    break;
                default:
                    // keep in mind oracle needs a transaction when inserting LOBs, aside from the specific syntax:
                    // INSERT INTO table (column, lobcolumn) VALUES (?, ?, EMPTY_BLOB()) RETURNING lobcolumn INTO ?
                    if (is_resource($v) && get_resource_type($v) === 'stream') {
                        $this->statement->bindParam($i+1, $v, \PDO::PARAM_LOB);
                        break;
                    }
                    if (!is_string($data[$i])) {
                        $data[$i] = serialize($data[$i]);
                    }
                    $this->statement->bindValue($i+1, $v);
                    break;
            }
        }
        try {
            if (!$this->statement->execute()) {
                throw new DBException('Prepared execute error');
            }
        } catch (\Exception $e) {
            throw new DBException($e->getMessage());
        }
        return new Result($this->statement, $this->driver);
    }
}
