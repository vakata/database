<?php

namespace vakata\database;

interface ResultInterface extends \Iterator, \Countable
{
    public function affected() : int;
    public function toArray() : array;
    public function insertID();
}
