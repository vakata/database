<?php

namespace vakata\database\test;

class MapperMysqlTest extends Mapper
{
    protected function getConnectionString()
    {
        return "mysql://root@127.0.0.1/test?charset=utf8";
    }
}
