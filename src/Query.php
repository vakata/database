<?php
namespace vakata\database;

class Query
{
	protected $drv = null;
	protected $sql = null;
	protected $prp = null;
	protected $rsl = null;

	public function __construct(driver\DriverInterface $drv, $sql) {
		$this->drv = $drv;
		$this->sql = $sql;
		$this->prp = $this->drv->prepare($sql);
	}
	public function execute($data = null) {
		return new QueryResult($this->drv, $this->prp, $data);
	}
}