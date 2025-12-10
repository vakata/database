<?php

namespace vakata\database\driver\sphinx;

use \vakata\database\DBException;
use \vakata\database\DriverInterface;
use \vakata\database\StatementInterface;
use \vakata\database\ResultInterface;

class Statement implements StatementInterface
{
    protected mixed $lnk;
    protected string $sql;
    protected ?array $map = null;

    public function __construct(mixed $lnk, string $sql = '', ?array $map = null)
    {
        $this->lnk = $lnk;
        $this->sql = $sql;
        $this->map = $map;
    }
    public function __destruct()
    {
        // used to close statement here
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
        $binder = '?';
        $sql = $this->sql;
        if (strpos($this->sql, $binder) !== false) {
            $tmp = explode($binder, $this->sql);
            $sql = '';
            foreach ($tmp as $i => $v) {
                $sql .= $v;
                if (isset($tmp[($i + 1)])) {
                    $par = array_shift($data);
                    if ($par instanceof \BackedEnum) {
                        $par = $par->value;
                    }
                    switch (gettype($par)) {
                        case 'boolean':
                        case 'integer':
                            $par = (int) $par;
                            break;
                        case 'array':
                            $par = implode(',', $par);
                            $par = "'" . $this->lnk->escape_string($par) . "'";
                            break;
                        case 'object':
                            $par = serialize($par);
                            $par = "'" . $this->lnk->escape_string($par) . "'";
                            break;
                        case 'resource':
                            if (is_resource($par) && get_resource_type($par) === 'stream') {
                                $par = stream_get_contents($par);
                                $par = "'" . $this->lnk->escape_string($par) . "'";
                            } else {
                                $par = serialize($par);
                                $par = "'" . $this->lnk->escape_string($par) . "'";
                            }
                            break;
                        default:
                            $par = "'" . $this->lnk->escape_string((string)$par) . "'";
                            break;
                    }
                    $sql .= $par;
                }
            }
        }
        return new Result($this->lnk, $sql, $buff);
    }
}
