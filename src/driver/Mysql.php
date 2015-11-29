<?php
namespace vakata\database\driver;
use vakata\database\DatabaseException;

class Mysql extends AbstractDriver
{
	protected $iid = 0;
	protected $aff = 0;
	public function __construct($settings) {
		parent::__construct($settings);
		if (!$this->settings->serverport) {
			$this->settings->serverport = 3306;
		}
	}
	protected function connect() {
		if ($this->lnk === null) {
			$this->lnk = ($this->settings->persist) ?
					@mysql_pconnect(
									$this->settings->servername.':'.$this->settings->serverport,
									$this->settings->username,
									$this->settings->password
					) :
					@mysql_connect(
									$this->settings->servername.':'.$this->settings->serverport,
									$this->settings->username,
									$this->settings->password
					);

			if ($this->lnk === false || !mysql_select_db($this->settings->database, $this->lnk) || !mysql_query("SET NAMES '".$this->settings->charset."'", $this->lnk)) {
				throw new DatabaseException('Connect error: ' . mysql_error());
			}
			if ($this->settings->timezone) {
				@mysql_query("SET time_zone = '" . addslashes($this->settings->timezone) . "'", $this->lnk);
			}
		}
	}
	protected function disconnect() {
		if (is_resource($this->lnk)) {
			mysql_close($this->lnk);
		}
	}

	protected function real($sql) {
		$this->connect();
		$temp = mysql_query($sql, $this->lnk);
		if (!$temp) {
			throw new DatabaseException('Could not execute query : ' . mysql_error($this->lnk) . ' <'.$sql.'>');
		}
		$this->iid = mysql_insert_id($this->lnk);
		$this->aff = mysql_affected_rows($this->lnk);
		return $temp;
	}
	public function nextr($result) {
		return mysql_fetch_array($result, MYSQL_BOTH);
	}
	public function seek($result, $row) {
		return @mysql_data_seek($result, $row);
	}
	public function count($result) {
		return mysql_num_rows($result);
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

	public function escape($input) {
		if (is_array($input)) {
			foreach ($input as $k => $v) {
				$input[$k] = $this->escape($v);
			}
			return implode(',', $input);
		}
		if (is_string($input)) {
			$input = mysql_real_escape_string($input, $this->lnk);
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
	public function free($result) {
		@mysql_free_result($result);
	}
}