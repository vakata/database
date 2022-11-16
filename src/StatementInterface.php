<?php

namespace vakata\database;

interface StatementInterface
{
    public function execute(array $par = [], bool $buff = true): ResultInterface;
}
