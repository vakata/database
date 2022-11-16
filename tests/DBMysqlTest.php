<?php

namespace vakata\database\test;

class DBMysqlTest extends DB
{
    protected function getConnectionString()
    {
        return "mysql://root@DESKTOP.local/test?charset=utf8";
    }
}
