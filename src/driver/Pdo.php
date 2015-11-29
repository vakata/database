<?php
namespace vakata\database\driver;
use vakata\database\DatabaseException;

class Pdo extends AbstractDriver
{
	protected $aff = 0;

	public function __construct($settings) {
		parent::__construct($settings);
		$this->settings->type = @current(explode(':', $this->settings->original, 2));
	}

	protected function connect() {
		if ($this->lnk === null) {
			try {
				$this->lnk = new \PDO($this->settings->original, $this->settings->username, $this->settings->password, $this->settings->options);
			} catch(\Exception $e) {
				throw new DatabaseException('Connect error: ' . $e->getMessage());
			}
		}
	}
	protected function disconnect() {
		if ($this->lnk !== null) {
			unset($this->lnk);
			$this->lnk = null;
		}
	}
	protected function real($sql) {
		$this->connect();
		$temp = $this->lnk->query($sql);
		if (!$temp) {
			throw new DatabaseException('Could not execute query : ' . $this->lnk->errorInfo() . ' <'.$sql.'>');
		}
		$this->aff = $temp->rowCount();
		return $temp;
	}

	public function nextr($result) {
		return $result->fetch(\PDO::FETCH_BOTH);
	}

	public function affected() {
		return $this->aff;
	}
	public function insertId() {
		return $this->lnk->lastInsertId(null);
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
			$sql = $this->prepare($sql);
		}
		$rtrn = $sql->execute(array_values($data));
		if (!$rtrn) {
			throw new DatabaseException('Prepared execute error : ' . $sql->errorInfo());
		}
		$this->aff = $sql->rowCount();
		return $sql->columnCount() ? $sql : $rtrn;
	}
	public function escape($input) {
		if (is_array($input)) {
			foreach ($input as $k => $v) {
				$input[$k] = $this->escape($v);
			}
			return implode(',', $input);
		}
		if (is_string($input)) {
			$input = $this->lnk->quote($input);
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
	public function begin() {
		$this->connect();
		return $this->lnk->beginTransaction();
	}
	public function commit() {
		$this->connect();
		return $this->lnk->commit();
	}
	public function rollback() {
		$this->connect();
		return $this->lnk->rollBack();
	}
	public function isTransaction() {
		return $this->lnk->inTransaction();
	}
	public function free($result) {
		unset($result);
		$result = null;
		return true;
	}
}