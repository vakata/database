<?php

namespace vakata\database\test;

class DBMysqlTest extends DB
{
    protected function getConnectionString()
    {
        return "mysql://root@".gethostname().".local/test?charset=utf8";
    }
}
