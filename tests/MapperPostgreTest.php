<?php

namespace vakata\database\test;

class MapperPostgreTest extends Mapper
{
    protected function getConnectionString()
    {
        return "postgre://postgres:postgres@DESKTOP.local/test?schema=public";
    }
}
