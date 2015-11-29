<?php
namespace vakata\database\driver;
use vakata\database\Settings;
use vakata\database\DatabaseException;

abstract class AbstractDriver implements DriverInterface
{
	protected $lnk = null;
	protected $settings = null;

	public function __construct(Settings $settings) {
		$this->settings = $settings;
	}
	public function __destruct() {
		$this->disconnect();
	}
	public function settings() {
		return $this->settings;
	}

	protected function connect() {
	}
	protected function disconnect() {
	}

	public function query($sql, array $data = null) {
		return $this->execute($this->prepare($sql), $data);
	}
	public function prepare($sql) {
		$this->connect();
		return $sql;
	}
	public function execute($sql, array $data = null) {
		$this->connect();
		$binder = '?';
		if (strpos($sql, $binder) !== false && is_array($data) && count($data)) {
			$tmp = explode($binder, $sql);
			$data = array_values($data);
			if (count($data) >= count($tmp)) {
				$data = array_slice($data, 0, count($tmp) - 1);
			}
			$sql = $tmp[0];
			foreach ($data as $i => $v) {
				$sql .= $this->escape($v) . $tmp[($i + 1)];
			}
		}
		return $this->real($sql);
	}
	public function escape($input) {
		if (is_array($input)) {
			foreach ($input as $k => $v) {
				$input[$k] = $this->escape($v);
			}
			return implode(',', $input);
		}
		if (is_string($input)) {
			$input = addslashes($input);
			return "'".$input."'";
		}
		if (is_bool($input)) {
			return $input === false ? 0 : 1;
		}
		if (is_null($input)) {
			return 'NULL';
		}
		return $input;
	}

	public function seekable() {
		return false;
	}
	public function countable() {
		return false;
	}

	// fail silently and just execute queries
	public function begin() {
		return false;
	}
	public function commit() {
		return false;
	}
	public function rollback() {
		return false;
	}
	public function isTransaction() {
		return false;
	}
	public function free($result) {
	}

	public function count($result) {
		throw new DatabaseException('Driver does not support result count');
	}
	public function seek($result, $row) {
		throw new DatabaseException('Driver does not support seek');
	}
	public function affected() {
		throw new DatabaseException('Driver does not support affected count');
	}
	public function insertId() {
		return null;
	}

	abstract protected function real($sql);
	abstract public function nextr($result);
}
