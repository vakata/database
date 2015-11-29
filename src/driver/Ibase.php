<?php
namespace vakata\database\driver;
use vakata\database\DatabaseException;

class Ibase extends AbstractDriver
{
	protected $aff = 0;
	protected $transaction = null;

	public function __construct($settings) {
		parent::__construct($settings);
		if (!is_file($this->settings->database) && is_file('/'.$this->settings->database)) {
			$this->settings->database = '/'.$this->settings->database;
		}
		$this->settings->servername = ($this->settings->servername === 'localhost' || $this->settings->servername === '') ?
			'' :
			$this->settings->servername . ':';
	}
	protected function connect() {
		if ($this->lnk === null) {
			$this->lnk = ($this->settings->persist) ?
					@\ibase_pconnect(
									$this->settings->servername . $this->settings->database,
									$this->settings->username,
									$this->settings->password,
									strtoupper($this->settings->charset)
					) :
					@\ibase_connect(
									$this->settings->servername . $this->settings->database,
									$this->settings->username,
									$this->settings->password,
									strtoupper($this->settings->charset)
					);
			if ($this->lnk === false) {
				throw new DatabaseException('Connect error: ' . \ibase_errmsg());
			}
		}
	}
	protected function disconnect() {
		if (is_resource($this->lnk)) {
			\ibase_close($this->lnk);
		}
	}

	protected function real($sql) {
		$this->connect();
		$temp = \ibase_query($this->transaction !== null ? $this->transaction : $this->lnk, $sql);
		if (!$temp) {
			throw new DatabaseException('Could not execute query : ' . \ibase_errmsg() . ' <'.$sql.'>');
		}
		$this->aff = \ibase_affected_rows($this->lnk);
		return $temp;
	}
	public function prepare($sql) {
		$this->connect();
		return \ibase_prepare($this->transaction !== null ? $this->transaction : $this->lnk, $sql);
	}
	public function execute($sql, array $data = null) {
		$this->connect();
		if (!is_array($data)) {
			$data = array();
		}
		$data = array_values($data);
		foreach ($data as $i => $v) {
			switch (gettype($v)) {
				case "boolean":
				case "integer":
					$data[$i] = (int)$v;
					break;
				case "array":
					$data[$i] = implode(',', $v);
					break;
				case "object":
				case "resource":
					$data[$i] = serialize($data[$i]);
					break;
			}
		}
		array_unshift($data, $sql);
		$temp = call_user_func_array("\ibase_execute", $data);
		if (!$temp) {
			throw new DatabaseException('Could not execute query : ' . \ibase_errmsg() . ' <'.$sql.'>');
		}
		$this->aff = \ibase_affected_rows($this->lnk);
		return $temp;
	}
	public function nextr($result) {
		return \ibase_fetch_assoc($result, \ibase_TEXT);
	}
	public function affected() {
		return $this->aff;
	}

	public function begin() {
		$this->connect();
		$this->transaction = \ibase_trans($this->lnk);
		if ($this->transaction === false) {
			$this->transaction === null;
		}
		return ($this->transaction !== null);
	}
	public function commit() {
		$this->connect();
		if ($this->transaction === null) {
			return false;
		}
		if (!\ibase_commit($this->transaction)) {
			return false;
		}
		$this->transaction = null;
		return true;
	}
	public function rollback() {
		$this->connect();
		if ($this->transaction === null) {
			return false;
		}
		if (!\ibase_rollback($this->transaction)) {
			return false;
		}
		$this->transaction = null;
		return true;
	}
	public function isTransaction() {
		return ($this->transaction !== null);
	}
	public function free($result) {
		@\ibase_free_result($result);
	}
}