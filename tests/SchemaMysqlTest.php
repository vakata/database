<?php

namespace vakata\database\test;

class SchemaMysqlTest extends Schema
{
    protected function getConnectionString()
    {
        return "mysql://test@localhost/test?charset=utf8";
    }
}
