<?php
namespace vakata\database\driver;
use vakata\database\DatabaseException;

class Postgre extends AbstractDriver
{
	protected $iid = 0;
	protected $aff = 0;
	protected $transaction = false;
	public function __construct($settings) {
		parent::__construct($settings);
		if (!$this->settings->serverport) {
			$this->settings->serverport = 5432;
		}
	}
	protected function connect() {
		if ($this->lnk === null) {
			$this->lnk = ($this->settings->persist) ?
					@pg_pconnect(
									"host=" . $this->settings->servername . " " .
									"port=" . $this->settings->serverport . " " .
									"user=" . $this->settings->username . " " .
									"password=" . $this->settings->password . " " .
									"dbname=" . $this->settings->database . " " .
									"options='--client_encoding=".strtoupper($this->settings->charset)."' "
					) :
					@pg_connect(
									"host=" . $this->settings->servername . " " .
									"port=" . $this->settings->serverport . " " .
									"user=" . $this->settings->username . " " .
									"password=" . $this->settings->password . " " .
									"dbname=" . $this->settings->database . " " .
									"options='--client_encoding=".strtoupper($this->settings->charset)."' "
					);
			if ($this->lnk === false) {
				throw new DatabaseException('Connect error');
			}
			if ($this->settings->timezone) {
				@pg_query($this->lnk, "SET TIME ZONE '".addslashes($this->settings->timezone)."'");
			}
		}
	}
	protected function disconnect() {
		if (is_resource($this->lnk)) {
			pg_close($this->lnk);
		}
	}
	protected function real($sql) {
		return $this->query($sql);
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
					$sql .= '$' . ($i + 1);
				}
			}
		}
		return $sql;
	}
	public function execute($sql, array $data = null) {
		$this->connect();
		if (!is_array($data)) {
			$data = array();
		}
		$temp = (is_array($data) && count($data)) ? pg_query_params($this->lnk, $sql, $data) : pg_query_params($this->lnk, $sql, array());
		if (!$temp) {
			throw new DatabaseException('Could not execute query : ' . pg_last_error($this->lnk) . ' <'.$sql.'>');
		}
		if (preg_match('@^\s*(INSERT|REPLACE)\s+INTO@i', $sql)) {
			$this->iid = pg_query($this->lnk, 'SELECT lastval()');
			if ($this->iid) {
				$this->iid = pg_fetch_row($this->iid);
				$this->iid = $this->iid[0];
			}
			$this->aff = pg_affected_rows($temp);
		}
		return $temp;
	}

	public function nextr($result) {
		return pg_fetch_array($result, null, PGSQL_BOTH);
	}
	public function seek($result, $row) {
		return @pg_result_seek($result, $row);
	}
	public function count($result) {
		return pg_num_rows($result);
	}
	public function affected() {
		return $this->aff;
	}
	public function insertId() {
		return $this->iid;
	}

	public function seekable() {
		return true;
	}
	public function countable() {
		return true;
	}

	public function begin() {
		$this->connect();
		try {
			$this->transaction = true;
			$this->query('BEGIN');
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
		@pg_free_result($result);
	}
}