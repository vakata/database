<?php

namespace vakata\database\test;

class MapperPostgreTest extends Mapper
{
    protected function getConnectionString()
    {
        return "postgre://postgres@127.0.0.1/test?schema=public";
    }
}