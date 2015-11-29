<?php
namespace vakata\database\driver;
use vakata\database\DatabaseException;

/*
$db = new \vakata\database\DB('sphinx://root@127.0.0.1:9306/raichu');
$db->query("INSERT INTO rt (id, title, content) VALUES(4, 'асдф','asdf')");
var_dump($db->all("SELECT *, WEIGHT() w FROM rt WHERE MATCH(?)", ['асдф']));
 */

class Sphinx extends AbstractDriver
{
	protected $iid = 0;
	protected $aff = 0;
	protected $mnd = false;
	protected $transaction = false;

	public function __construct($settings) {
		parent::__construct($settings);
		if (!$this->settings->serverport) {
			$this->settings->serverport = 9306;
		}
		$this->mnd = function_exists('mysqli_fetch_all');
	}

	protected function connect() {
		if ($this->lnk === null) {
			$this->lnk = new \mysqli(
							($this->settings->persist ? 'p:' : '') . $this->settings->servername,
							$this->settings->username,
							$this->settings->password,
							$this->settings->database,
							$this->settings->serverport
			);
			if ($this->lnk->connect_errno) {
				throw new DatabaseException('Connect error: ' . $this->lnk->connect_errno);
			}
			if (!$this->lnk->set_charset($this->settings->charset)) {
				throw new DatabaseException('Charset error: ' . $this->lnk->connect_errno);
			}
		}
	}
	protected function disconnect() {
		if ($this->lnk !== null) {
			@$this->lnk->close();
		}
	}

	protected function real($sql) {
		$this->connect();
		$temp = $this->lnk->query($sql);
		if (!$temp) {
			throw new DatabaseException('Could not execute query : ' . $this->lnk->error . ' <'.$sql.'>');
		}
		$this->iid = $this->lnk->insert_id;
		$this->aff = $this->lnk->affected_rows;
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
			$input = $this->lnk->real_escape_string($input);
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
		if ($this->mnd) {
			return $result->fetch_array(MYSQL_BOTH);
		}
		$ref = $result->result_metadata();
		if (!$ref) {
			return false;
		}
		$tmp = mysqli_fetch_fields($ref);
		if (!$tmp) {
			return false;
		}
		$ref = [];
		foreach ($tmp as $col) {
			$ref[] = [$col->name, null];
		}
		$tmp = [];
		foreach ($ref as $k => $v) {
			$tmp[] =& $ref[$k][1];
		}
		try {
			if (!call_user_func_array(array($result, 'bind_result'), $tmp)) {
				return false;
			}
		}
		catch(\Exception $e) {}
		if (!$result->fetch()) {
			return false;
		}
		$tmp = [];
		$i = 0;
		foreach ($ref as $k => $v) {
			$tmp[$i++] = $v[1];
			$tmp[$v[0]] = $v[1];
		}
		return $tmp;
	}
	public function seek($result, $row) {
		return $result->data_seek($row);
	}
	public function count($result) {
		return $result->num_rows;
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

	public function isTransaction() {
		return $this->transaction;
	}
	public function free($result) {
		return $this->mnd ? @$result->free() : @$result->free_result();
	}
}