<?php

namespace vakata\database\test;

class SchemaPostgreTest extends Schema
{
    protected function getConnectionString()
    {
        return "postgre://postgres@127.0.0.1/test?schema=public";
    }
}
