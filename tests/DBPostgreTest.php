<?php

namespace vakata\database\test;

class DBPostgreTest extends DB
{
    protected function getConnectionString()
    {
        return "postgre://postgres:postgres@".gethostname().".local/test?charset=utf8";
    }
}
