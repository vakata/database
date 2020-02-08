<?php

namespace vakata\database;

interface StatementInterface
{
    public function execute(array $par = []) : ResultInterface;
}
