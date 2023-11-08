<?php

namespace vakata\database\test;

class SchemaMysqlTest extends Schema
{
    protected function getConnectionString()
    {
        return "mysql://root@".gethostname().".local/test?charset=utf8";
    }
}
