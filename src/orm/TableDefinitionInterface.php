<?php
namespace vakata\database\orm;

interface TableDefinitionInterface
{
	public function getName();
	public function getPrimaryKey();
	public function getColumns();
}
