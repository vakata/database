<?php

namespace vakata\database\test;

class SchemaMysqlTest extends Schema
{
    protected function getConnectionString()
    {
        return "mysql://root@DESKTOP.local/test?charset=utf8";
    }
}
