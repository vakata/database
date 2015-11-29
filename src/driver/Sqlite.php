<?php
namespace vakata\database\driver;
use vakata\database\DatabaseException;

class Sqlite extends AbstractDriver
{
	protected $iid = 0;
	protected $aff = 0;
	protected $transaction = false;

	public function __construct($settings) {
		parent::__construct($settings);
		$this->settings->database = explode('://', $this->settings->original, 2)[1];
		if (!is_file($this->settings->database) && is_file('/' . $this->settings->database) && is_readable('/' . $this->settings->database)) {
			$this->settings->database = '/' . $this->settings->database;
		}
	}

	protected function connect() {
		if ($this->lnk === null) {
			try {
				$this->lnk = new \SQLite3($this->settings->database);
				$this->lnk->exec('PRAGMA encoding = "'.$this->settings->charset.'"');
			}
			catch(\Exception $e) {
				throw new DatabaseException('Connect error: ' . $this->lnk->lastErrorMsg());
			}
		}
	}
	protected function disconnect() {
		if ($this->lnk !== null) {
			@$this->lnk->close();
		}
	}

	public function prepare($sql) {
		$this->connect();
		$binder = '?';
		if (strpos($sql, $binder) !== false) {
			$tmp = explode($binder, $sql);
			$sql = '';
			foreach ($tmp as $i => $v) {
				$sql .= $v;
				if (isset($tmp[($i + 1)])) {
					$sql .= ':i' . $i;
				}
			}
		}
		$temp = $this->lnk->prepare($sql);
		if (!$temp) {
			throw new DatabaseException('Could not prepare : ' . $this->lnk->lastErrorMsg() . ' <'.$sql.'>');
		}
		return $temp;
	}
	public function execute($sql, array $data = null) {
		$this->connect();
		if (!is_array($data)) {
			$data = array();
		}
		if (is_string($sql)) {
			return parent::execute($sql, $data);
		}
		$data = array_values($data);
		if ($sql->paramCount()) {
			if (count($data) < $sql->paramCount()) {
				throw new DatabaseException('Prepared execute - not enough parameters.');
			}
			foreach ($data as $i => $v) {
				switch (gettype($v)) {
					case "boolean":
					case "integer":
						$sql->bindValue(':i'.$i, (int)$v, SQLITE3_INTEGER);
						break;
					case "double":
						$sql->bindValue(':i'.$i, $v, SQLITE3_FLOAT);
						break;
					case "array":
						$sql->bindValue(':i'.$i, implode(',', $v), SQLITE3_TEXT);
						break;
					case "object":
					case "resource":
						$sql->bindValue(':i'.$i, serialize($v), SQLITE3_TEXT);
						break;
					case "NULL":
						$sql->bindValue(':i'.$i, null, SQLITE3_NULL);
						break;
					default:
						$sql->bindValue(':i'.$i, (string)$v, SQLITE3_TEXT);
						break;
				}
			}
		}
		$rtrn = $sql->execute();
		if (!$rtrn) {
			throw new DatabaseException('Prepared execute error : ' . $this->lnk->lastErrorMsg());
		}
		$this->iid = $this->lnk->lastInsertRowID();
		$this->aff = $this->lnk->changes();
		return $rtrn;
	}
	protected function real($sql) {
		$this->connect();
		$temp = $this->lnk->query($sql);
		if (!$temp) {
			throw new DatabaseException('Could not execute query : ' . $this->lnk->lastErrorMsg() . ' <'.$sql.'>');
		}
		$this->iid = $this->lnk->lastInsertRowID();
		$this->aff = $this->lnk->changes();
		return $temp;
	}
	public function escape($input) {
		$this->connect();
		if (is_array($input)) {
			foreach ($input as $k => $v) {
				$input[$k] = $this->escape($v);
			}
			return implode(',', $input);
		}
		if (is_string($input)) {
			$input = $this->lnk->escapeString($input);
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

	public function nextr($result) {
		return $result->fetchArray(SQLITE3_BOTH);
	}
	public function affected() {
		return $this->aff;
	}
	public function insertId() {
		return $this->iid;
	}

	public function seekable() {
		return false;
	}
	public function countable() {
		return false;
	}

	public function begin() {
		$this->connect();
		try {
			$this->transaction = true;
			$this->query('BEGIN TRANSACTION');
		} catch(DatabaseException $e) {
			$this->transaction = false;
			return false;
		}
		return true;
	}
	public function commit() {
		$this->connect();
		$this->transaction = false;
		try {
			$this->query('COMMIT');
		} catch(DatabaseException $e) {
			return false;
		}
		return true;
	}
	public function rollback() {
		$this->connect();
		$this->transaction = false;
		try {
			$this->query('ROLLBACK');
		} catch(DatabaseException $e) {
			return false;
		}
		return true;
	}
	public function isTransaction() {
		return $this->transaction;
	}

	public function free($result) {
		return @$result->finalize();
	}
}