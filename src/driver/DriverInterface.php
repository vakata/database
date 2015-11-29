<?php
namespace vakata\database\driver;

interface DriverInterface
{
	public function prepare($sql);
	public function execute($sql, array $data = null);
	public function query($sql, array $data = null);
	public function escape($input);

	public function nextr($result);
	public function seek($result, $row);
	public function count($result);
	public function free($result);

	public function affected();
	public function insertId();

	public function settings();
	public function seekable();
	public function countable();

	public function begin();
	public function commit();
	public function rollback();
	public function isTransaction();
}