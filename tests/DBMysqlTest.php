<?php

namespace vakata\database\test;

class DBMysqlTest extends DB
{
    protected function getConnectionString()
    {
        return "mysql://test@localhost/test?charset=utf8";
    }
}
