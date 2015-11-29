<?php
namespace vakata\database\test;

class DatabaseTest extends \PHPUnit_Framework_TestCase
{
	protected static $db = null;

	public static function setUpBeforeClass() {
		self::$db = new \vakata\database\DB('mysqli://root@127.0.0.1/system');
		self::$db->query("
			CREATE TEMPORARY TABLE IF NOT EXISTS test (
				id int(10) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(255) NOT NULL,
				PRIMARY KEY (id)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8;
		");
	}
	public static function tearDownAfterClass() {
		self::$db->query("
			DROP TEMPORARY TABLE test;
		");
	}
	protected function setUp() {
		// self::$db->query("TRUNCATE TABLE test;");
	}
	protected function tearDown() {
		// self::$db->query("TRUNCATE TABLE test;");
	}


	public function testQuery() {
		$this->assertEquals(1, self::$db->query('INSERT INTO test VALUES(NULL, ?)', ['user1'])->affected());
		$this->assertEquals(1, self::$db->query('INSERT INTO test VALUES(NULL, ?)', ['user2'])->affected());
		$this->assertEquals(1, self::$db->query('INSERT INTO test VALUES(NULL, ?)', ['user3'])->affected());
	}
	/**
	 * @depends testQuery
	 */
	public function testInsertId() {
		$this->assertEquals(3, self::$db->insertId());
	}

	/**
	 * @depends testQuery
	 */
	public function testAll() {
		$this->assertEquals([
			['id' => 1,'name' => 'user1'],
			['id' => 2,'name' => 'user2'],
			['id' => 3,'name' => 'user3']
		], self::$db->all('SELECT * FROM test'));
	}
	/**
	 * @depends testQuery
	 */
	public function testOne() {
		$this->assertEquals(['id' => 2,'name' => 'user2'], self::$db->one('SELECT * FROM test WHERE name = ?', ['user2']));
	}
	/**
	 * @depends testQuery
	 */
	public function testGet() {
		$temp = [];
		foreach(self::$db->get('SELECT * FROM test') as $v) {
			$temp[] = $v;
		}
		$this->assertEquals([
			['id' => 1,'name' => 'user1'],
			['id' => 2,'name' => 'user2'],
			['id' => 3,'name' => 'user3']
		], $temp);
	}

	/**
	 * @depends testQuery
	 */
	public function testAllSingle() {
		$this->assertEquals([
			'user2',
			'user3'
		], self::$db->all('SELECT name FROM test WHERE id > 1'));
	}

	/**
	 * @depends testQuery
	 */
	public function testOneSingle() {
		$this->assertEquals('user2', self::$db->one('SELECT name FROM test WHERE id = 2'));
	}
	/**
	 * @depends testQuery
	 */
	public function testGetSingle() {
		$temp = [];
		foreach(self::$db->get('SELECT id FROM test') as $v) {
			$temp[] = $v;
		}
		$this->assertEquals([1,2,3], $temp);
	}

	/**
	 * @depends testQuery
	 */
	public function testAllArrayAccess() {
		$this->assertEquals('user3', self::$db->all('SELECT id, name FROM test WHERE id IN (1, 3)', null, 'id', true)[3]);
	}
	/**
	 * @depends testQuery
	 */
	public function testGetArrayAccess() {
		$this->assertEquals('user3', self::$db->get('SELECT id, name FROM test WHERE id IN (1, 3)', null, 'id', true)[3]);
	}

	/**
	 * @depends testQuery
	 */
	public function testPrepare() {
		$q1 = self::$db->prepare('SELECT id FROM test WHERE id = ?');
		$q2 = self::$db->prepare('SELECT name FROM test WHERE id = ?');
		$this->assertEquals('user1', $q2->execute([1])->result()->get()[0]);
		$this->assertEquals(1, $q1->execute([1])->result()->get()[0]);
	}
}
