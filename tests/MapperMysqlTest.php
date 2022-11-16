<?php

namespace vakata\database\test;

class MapperMysqlTest extends Mapper
{
    protected function getConnectionString()
    {
        return "mysql://root@DESKTOP.local/test?charset=utf8";
    }
}
