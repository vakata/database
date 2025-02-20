<?php

namespace vakata\database\test;

class MapperMysqlTest extends Mapper
{
    protected function getConnectionString()
    {
        return "mysql://test@localhost/test?charset=utf8";
    }
}
