<?php

namespace vakata\database\test;

class SchemaPostgreTest extends Schema
{
    protected function getConnectionString()
    {
        return "postgre://postgres:postgres@".gethostname().".local/test?schema=public";
    }
}
