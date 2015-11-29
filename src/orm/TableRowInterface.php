<?php
namespace vakata\database\orm;

interface TableRowInterface extends \JsonSerializable
{
	public function getID();
	public function toArray($full = true);
	public function fromArray(array $data);
	public function save();
	public function delete();
}