<?php

namespace vakata\database;

use \vakata\collection\Collection;

interface ResultInterface extends \Iterator, \Countable
{
    public function affected() : int;
    public function toArray() : array;
    public function insertID();
    public function collection() : Collection;
}
