<?php
namespace vakata\database\driver;
use vakata\database\DatabaseException;

class Mysqli extends AbstractDriver
{
	protected $iid = 0;
	protected $aff = 0;
	protected $mnd = false;
	protected $transaction = false;

	public function __construct($settings) {
		parent::__construct($settings);
		if (!$this->settings->serverport) {
			$this->settings->serverport = 3306;
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
			if ($this->settings->timezone) {
				@$this->lnk->query("SET time_zone = '" . addslashes($this->settings->timezone) . "'");
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
		$temp = $this->lnk->prepare($sql);
		if (!$temp) {
			throw new DatabaseException('Could not prepare : ' . $this->lnk->error . ' <'.$sql.'>');
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
		if ($sql->param_count) {
			if (count($data) < $sql->param_count) {
				throw new DatabaseException('Prepared execute - not enough parameters.');
			}
			$lds = 1024 * 1024;
			$ref = array('');
			$lng = array();
			$nul = null;
			foreach ($data as $i => $v) {
				switch (gettype($v)) {
					case "boolean":
					case "integer":
						$data[$i] = (int)$v;
						$ref[0] .= 'i';
						$ref[$i+1] =& $data[$i];
						break;
					case "NULL":
						$ref[0] .= 's';
						$ref[$i+1] =& $data[$i];
						break;
					case "double":
						$ref[0] .= 'd';
						$ref[$i+1] =& $data[$i];
						break;
					default:
						if (!is_string($data[$i])) {
							$data[$i] = serialize($data[$i]);
						}
						if (strlen($data[$i]) > $lds) {
							$ref[0] .= 'b';
							$ref[$i+1] =& $nul;
							$lng[] = $i;
						}
						else {
							$ref[0] .= 's';
							$ref[$i+1] =& $data[$i];
						}
						break;
				}
			}
			call_user_func_array(array($sql, 'bind_param'), $ref);
			foreach ($lng as $index) {
				$data[$index] = str_split($data[$index], $lds);
				foreach ($data[$index] as $chunk) {
					$sql->send_long_data($index, $chunk);
				}
			}
		}
		$rtrn = $sql->execute();
		if (!$this->mnd) {
			$sql->store_result();
		}
		if (!$rtrn) {
			throw new DatabaseException('Prepared execute error : ' . $this->lnk->error);
		}
		$this->iid = $this->lnk->insert_id;
		$this->aff = $this->lnk->affected_rows;
		if (!$this->mnd) {
			return $sql->field_count ? $sql : $rtrn;
		}
		return $sql->field_count ? $sql->get_result() : $rtrn;
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
			return $result->fetch_array(MYSQLI_BOTH);
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
		return $result->data_seek($row) !== false;
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

	public function begin() {
		$this->connect();
		$this->transaction = true;
		try {
			$this->lnk->autocommit(false);
			return true;
		} catch(DatabaseException $e) {
			$this->transaction = false;
			return false;
		}
	}
	public function commit() {
		$this->connect();
		$this->transaction = false;
		if (!$this->lnk->commit()) {
			return false;
		}
		return $this->lnk->autocommit(true);
	}
	public function rollback() {
		$this->connect();
		$this->transaction = false;
		if (!$this->lnk->rollback()) {
			return false;
		}
		return $this->lnk->autocommit(true);
	}
	public function isTransaction() {
		return $this->transaction;
	}
	public function free($result) {
		return $this->mnd ? @$result->free() : @$result->free_result();
	}
}
