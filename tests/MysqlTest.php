<?php

namespace vakata\database\test;

class MysqlTest extends DB
{
    protected function getConnectionString()
    {
        return "mysql://root@127.0.0.1/test?charset=utf8";
    }
}