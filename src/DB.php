<?php
namespace vakata\database;

class DB implements DatabaseInterface
{
	protected $drv = null;
	protected $que = null;
	protected $rsl = null;

	/**
	 * Създаване на инстанция
	 * @method __construct
	 * @param  mixed      $drv Инстанция на драйвър или connection string, а ако не е подадено се използва константата DATABASE
	 */
	public function __construct($drv = null) {
		if (!$drv) {
			throw new DatabaseException('Could not create database (no settings)');
		}
		if (is_string($drv)) {
			$drv = new Settings($drv);
		}
		if ($drv instanceof Settings) {
			try {
				$tmp = '\\vakata\\database\\driver\\' . ucfirst($drv->type);
				$drv = new $tmp($drv);
			} catch (\Exception $e) {
				throw new DatabaseException('Could not create database driver - ' . $e);
			}
		}
		if (!($drv instanceof driver\DriverInterface)) {
			throw new DatabaseException('Invalid database driver');
		}
		$this->drv = $drv;
	}
	/**
	 * Създаване на параметризирана заявка
	 * Да се използва само ако е необходимо една и съща заявка да се изпълни много пъти с различни параметри.
	 * @method prepare
	 * @param  String  $sql Заявката за подготовка, за placeholder на стойности се използва ?
	 * @return  Query       Потготвената заявка
	 */
	public function prepare($sql) {
		try {
			return $this->que = new Query($this->drv, $sql);
		} catch (\Exception $e) {
			throw new DatabaseException($e->getMessage(), 2);
		}
	}
	/**
	 * Изпълненение на последната параметризирана заявка с подадените параметри
	 * @method execute
	 * @param  array   $data Параметри за изпълнението
	 * @return QueryResult   Резултат от изпълнението на заявката
	 */
	public function execute($data = null) {
		try {
			return $this->rsl = $this->que->execute($data);
		} catch (\Exception $e) {
			throw new DatabaseException($e->getMessage(), 3);
		}
	}
	/**
	 * Изпълнение на заявка (параметризиране и изпълнение)
	 * @method query
	 * @param  string  $sql   SQL заявка
	 * @param  array   $data  Параметри за изпълнението
	 * @return QueryResult    Резултат от изпълнението на заявката
	 */
	public function query($sql, $data = null) {
		try {
			$this->prepare($sql);
			return $this->execute($data);
		}
		catch (\Exception $e) {
			throw new DatabaseException($e->getMessage(), 4);
		}
	}
	/**
	 * Изпълнение на SELECT заявка и връщане на ArrayLike резултат (който можем да достъпваме с [] или да подадем на foreach)
	 * @method get
	 * @param  string  $sql      SQL заявка
	 * @param  array   $data     Параметри за изпълнението
	 * @param  string  $key      Ключ за резултатния масив (ако искаме можем да използваме някоя от колоните за ключ)
	 * @param  boolean $skip_key Ако сме използвали колона за ключ можем да не я включим в списъка със стойности (по подразбиране е изключено)
	 * @param  string  $mode     Режим на извличане - по подразбиране е "assoc" но може да се подаде "num"
	 * @param  boolean $opti     Ако заявката връща само една стойност - да не се обгражда в масив (включено по подразбиране)
	 * @return ArrayLike         Резултат от заявката, който можем да подадем на foreach
	 */
	public function get($sql, $data = null, $key = null, $skip_key = false, $mode = 'assoc', $opti = true) {
		return (new Query($this->drv, $sql))->execute($data)->result($key, $skip_key, $mode, $opti);
	}
	/**
	 * Изпълнение на SELECT заявка и връщане на масив с резултата
	 * @method all
	 * @param  string  $sql      SQL заявка
	 * @param  array   $data     Параметри за изпълнението
	 * @param  string  $key      Ключ за резултатния масив (ако искаме можем да използваме някоя от колоните за ключ)
	 * @param  boolean $skip_key Ако сме използвали колона за ключ можем да не я включим в списъка със стойности (по подразбиране е изключено)
	 * @param  string  $mode     Режим на извличане - по подразбиране е "assoc" но може да се подаде "num"
	 * @param  boolean $opti     Ако заявката връща само една стойност - да не се обгражда в масив (включено по подразбиране)
	 * @return array             Резултат от изпълнението
	 */
	public function all($sql, $data = null, $key = null, $skip_key = false, $mode = 'assoc', $opti = true) {
		return $this->get($sql, $data, $key, $skip_key, $mode, $opti)->get();
	}
	/**
	 * Изпълнение на SELECT заявка и връщане на първия ред от резултата
	 * @method one
	 * @param  string  $sql  SQL заявка
	 * @param  array   $data Параметри за изпълнението
	 * @param  string  $mode Режим на извличане - по подразбиране е "assoc" но може да се подаде "num"
	 * @param  boolean $opti Ако заявката връща само една стойност - да не се обгражда в масив (включено по подразбиране)
	 * @return array         Резултат от изпълнението
	 */
	public function one($sql, $data = null, $mode = 'assoc', $opti = true) {
		return $this->get($sql, $data, null, false, $mode, $opti)->one();
	}
	/**
	 * Връща името нa текущо използвания драйвър (mysql, mysqli, postgre, oracle, ibase, pdo)
	 * @method get_driver
	 * @return string    името нa текущо използвания драйвър
	 */
	public function driver() {
		return $this->drv->settings()->type;
	}
	/**
	 * Подготвя string за вмъкване в заявка - по възможност да се използва параметризирана заявка, а не този метод
	 * @method escape
	 * @param  string $str string за подготовка
	 * @return string      Готов за вмъкване в заявка string
	 */
	public function escape($str) {
		return $this->drv->escape($str);
	}
	/**
	 * Начало на транзакция
	 * @method begin
	 * @return boolean Индикатор дали началото на транзакция е успешно
	 */
	public function begin() {
		if($this->drv->isTransaction()) {
			return false;
		}
		return $this->drv->begin();
	}
	/**
	 * Финализиране на транзакция
	 * @method commit
	 * @return boolean Индикатор дали финализирането е успешно
	 */
	public function commit($isTransaction = true) {
		return $isTransaction && $this->drv->isTransaction() && $this->drv->commit();
	}
	/**
	 * Връщане на транзакция до предишен стейт
	 * @method rollback
	 * @return boolean   Индикатор дали връщането е успешно
	 */
	public function rollback($isTransaction = true) {
		return $isTransaction && $this->drv->isTransaction() && $this->drv->rollback();
	}
	/**
	 * Връщане на транзакция до предишен стейт
	 * @method isTransaction
	 * @return boolean   Индикатор дали в момента сме в транзакция
	 */
	public function isTransaction() {
		$this->drv->isTransaction();
	}

	public function __call($method, $args) {
		if ($this->rsl && is_callable(array($this->rsl, $method))) {
			try {
				return call_user_func_array(array($this->rsl, $method), $args);
			} catch (\Exception $e) {
				throw new DatabaseException($e->getMessage(), 5);
			}
		}
	}
}
