<?php

namespace vakata\database\test;

class SchemaPostgreTest extends Schema
{
    protected function getConnectionString()
    {
        return "postgre://postgres:postgres@DESKTOP.local/test?schema=public";
    }
}
