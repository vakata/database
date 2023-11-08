<?php

namespace vakata\database\test;

class MapperPostgreTest extends Mapper
{
    protected function getConnectionString()
    {
        return "postgre://postgres:postgres@".gethostname().".local/test?schema=public";
    }
}
