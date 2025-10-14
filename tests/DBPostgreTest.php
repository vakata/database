<?php

namespace vakata\database\test;

class DBPostgreTest extends DB
{
    protected function getConnectionString()
    {
        return "postgre://postgres:postgres@".gethostname().".local/test?charset=utf8";
    }
    public function testNamed()
    {
        $this->assertEquals(
            1,
            $this->getDB()->one('SELECT usr FROM users WHERE usr = :id', ['id'=>1])
        );
        $this->assertEquals(
            1,
            $this->getDB()->val('SELECT usr FROM users WHERE usr IN (:ids)', ['ids'=>[1]])
        );
        $this->assertEquals(
            1,
            $this->getDB()->one('SELECT usr FROM users WHERE usr = \'1\'::int', ['int'=>2])
        );
    }
}
