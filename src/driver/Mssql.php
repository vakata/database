<?php
namespace vakata\database\driver;
use vakata\database\DatabaseException;

class Mssql extends AbstractDriver
{
	protected $iid = 0;
	protected $aff = 0;
	protected $transaction = false;

	protected function connect() {
		if ($this->lnk === null) {
			$options = $this->settings->options;
			$options["Database"] = $this->settings->database;
			$options["ReturnDatesAsStrings"] = true;
			if ($this->settings->username) {
				$options["UID"] = $this->settings->username;
			}
			if ($this->settings->password) {
				$options["PWD"] = $this->settings->password;
			}
			if ($this->settings->charset) {
				//$options["CharacterSet"] = strtoupper($this->settings->charset);
			}
			$server = $this->settings->servername;
			if (isset($this->settings->serverport) && $this->settings->serverport) {
				$server .= ', ' . $this->settings->serverport;
			}

			$this->lnk = @sqlsrv_connect($server, $options);

			if ($this->lnk === false) {
				throw new DatabaseException('Connect error');
			}
		}
	}
	protected function disconnect() {
		if (is_resource($this->lnk)) {
			sqlsrv_close($this->lnk);
		}
	}
	protected function real($sql) {
		return $this->query($sql);
	}
	public function prepare($sql) {
		return $sql;
	}
	public function execute($sql, array $data = null) {
		$this->connect();
		if (!is_array($data)) {
			$data = array();
		}
		$temp = sqlsrv_query($this->lnk, $sql, $data);
		if (!$temp) {
			throw new DatabaseException('Could not execute query : ' . json_encode(sqlsrv_errors()) . ' <'.$sql.'>');
		}
		if (preg_match('@^\s*(INSERT|REPLACE)\s+INTO@i', $sql)) {
			$this->iid = sqlsrv_query($this->lnk, 'SELECT SCOPE_IDENTITY()');
			if ($this->iid) {
				$this->iid = sqlsrv_fetch_array($this->iid, SQLSRV_FETCH_NUMERIC);
				$this->iid = $this->iid[0];
			}
			$this->aff = sqlsrv_rows_affected($temp);
		}
		return $temp;
	}

	public function nextr($result) {
		return sqlsrv_fetch_array($result, SQLSRV_FETCH_BOTH);
	}
	/*
	public function seek($result, $row) {
		return @sqlsrv_fetch_array($result, SQLSRV_FETCH_BOTH, SQLSRV_SCROLL_ABSOLUTE, $row);
	}
	public function count($result) {
		return sqlsrv_num_rows($result);
	}
	*/
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
			sqlsrv_begin_transaction($this->lnk);
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
			sqlsrv_commit($this->lnk);
		} catch(DatabaseException $e) {
			return false;
		}
		return true;
	}
	public function rollback() {
		$this->connect();
		$this->transaction = false;
		try {
			sqlsrv_rollback($this->lnk);
		} catch(DatabaseException $e) {
			return false;
		}
		return true;
	}
	public function isTransaction() {
		return $this->transaction;
	}
	public function free($result) {
		@sqlsrv_free_stmt($result);
	}
}