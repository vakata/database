<?php

namespace vakata\database\test;

use vakata\database\DB as DBI;
use vakata\database\DBException as DBE;

class SchemaPDOMysqlTest extends Schema
{
    protected function getConnectionString()
    {
        return "pdo://test@mysql:host=localhost;dbname=test?charset=utf8&schema=test";
    }
    protected function importFile(DBI $dbc, string $path)
    {
        $path = __DIR__ . '/data/mysql_schema.sql';
        $sql = file_get_contents($path);
        $sql = str_replace("\r", '', $sql);
        $sql = preg_replace('(--.*\n)', '', $sql);
        $sql = preg_replace('(\n+)', "\n", $sql);
        $sql = explode(';', $sql);
        foreach (array_filter(array_map("trim", $sql)) as $q) {
            $dbc->query($q);
        }
    }
}
