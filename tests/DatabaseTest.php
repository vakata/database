<?php
namespace vakata\database\test;

class DatabaseTest extends \PHPUnit_Framework_TestCase
{
	protected static $db = null;

	public static function setUpBeforeClass() {
		//self::$db = new \vakata\database\DB('mysqli://root@127.0.0.1/test');
		//self::$db->query("
		//	CREATE TEMPORARY TABLE IF NOT EXISTS test (
		//		id int(10) unsigned NOT NULL AUTO_INCREMENT,
		//		name varchar(255) NOT NULL,
		//		PRIMARY KEY (id)
		//	) ENGINE=InnoDB  DEFAULT CHARSET=utf8;
		//");
	}
	public static function tearDownAfterClass() {
		self::$db->query("
			DROP TEMPORARY TABLE IF EXISTS test;
		");
	}
	protected function setUp() {
		// self::$db->query("TRUNCATE TABLE test;");
	}
	protected function tearDown() {
		// self::$db->query("TRUNCATE TABLE test;");
	}

	public function testInvalidCreate() {
		$this->setExpectedException('\vakata\database\DatabaseException');
		new \vakata\database\DB(1);
	}

	public function testCreate() {
		self::$db = new \vakata\database\DB('mysqli://root@127.0.0.1/test?charset=utf8');
		//self::$db = new \vakata\database\DB('pdo://root@mysql:dbname=test;host=127.0.0.1');
		$this->assertEquals(true, self::$db instanceof \vakata\database\DatabaseInterface);
		$this->assertEquals('mysqli', self::$db->driver());
		self::$db->query("
			CREATE TEMPORARY TABLE IF NOT EXISTS test (
				id int(10) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(255) NOT NULL,
				PRIMARY KEY (id)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8;
		");
	}

	public function testQuery() {
		$this->assertEquals(1, self::$db->query('INSERT INTO test VALUES(NULL, ?)', ['user1'])->affected());
		$this->assertEquals(1, self::$db->query('INSERT INTO test VALUES(NULL, ?)', ['user2'])->affected());
	}
	public function testInvalidQuery() {
		$this->setExpectedException('\vakata\database\DatabaseException');
		self::$db->query('INSERT INTO nonexisting.table VALUES(?)');
	}
	/**
	 * @depends testQuery
	 */
	public function testInsertId() {
		$this->assertEquals(3, self::$db->query('INSERT INTO test VALUES(NULL, ?)', ['user3'])->insertId());
	}
	/**
	 * @depends testInsertId
	 */
	public function testExpand() {
		$this->assertEquals(3, count(self::$db->all('SELECT * FROM test WHERE id IN (??)', [[1,2,3]])));
		$this->assertEquals(3, count(self::$db->all('SELECT * FROM test WHERE id IN (??)', [1,2,3])));
		$this->assertEquals(3, count(self::$db->all('SELECT * FROM test WHERE id > ? AND id IN (??) AND id < ?', [0, [1,2,3], 4])));
		$this->assertEquals(3, count(self::$db->all('SELECT * FROM test WHERE id > ? AND (id IN (??) OR id IN (??)) AND id < ?', [0, [1,2,3], [1,2,3], 4])));
		$this->assertEquals(2, count(self::$db->all('SELECT * FROM test WHERE id > ? AND (id IN (??) OR id IN (??)) AND id < ?', [0, 1, 2, 4])));
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

	public function testInvalidPrepare() {
		$this->setExpectedException('\vakata\database\DatabaseException');
		self::$db->prepare('INSERT INTO nonexisting.table VALUES(?)');
	}

	/**
	 * @depends testQuery
	 */
	public function testTransaction() {
		$this->assertEquals(true, self::$db->begin());
		$this->assertEquals(false, self::$db->begin());
		$this->assertEquals(4, self::$db->query('INSERT INTO test VALUES(NULL, ?)', ['user4'])->insertId());
		$this->assertEquals(true, self::$db->isTransaction());
		$this->assertEquals(true, self::$db->rollback());
		$this->assertEquals(false, self::$db->isTransaction());
		$this->assertEquals(3, self::$db->one('SELECT MAX(id) FROM test'));
		$this->assertEquals(true, self::$db->begin());
		$this->assertEquals(true, self::$db->query('INSERT INTO test VALUES(NULL, ?)', ['user4'])->insertId() > 3);
		$this->assertEquals(true, self::$db->commit());
		$this->assertEquals(true, self::$db->one('SELECT MAX(id) FROM test') > 3);
	}

	/**
	 * @depends testQuery
	 */
	public function testModes() {
		$this->assertEquals(['id'=>1,'name'=>'user1'], self::$db->one('SELECT id, name FROM test WHERE id = ?', [1], 'assoc'));
		$this->assertEquals(['id'=>1,'name'=>'user1'], self::$db->one('SELECT id, name FROM test WHERE id = ?', [1], 'assoc_lc'));
		$this->assertEquals([0=>1, 1=>'user1'], self::$db->one('SELECT id, name FROM test WHERE id = ?', [1], 'num'));
		$this->assertEquals([0=>1, 1=>'user1','id'=>1,'name'=>'user1'], self::$db->one('SELECT id, name FROM test WHERE id = ?', [1], 'both'));
		$this->assertEquals(['ID'=>1, 'NAME'=>'user1','id'=>1,'name'=>'user1'], self::$db->one('SELECT id, name FROM test WHERE id = ?', [1], 'assoc_ci'));
		$this->assertEquals(['ID'=>1, 'NAME'=>'user1'], self::$db->one('SELECT id, name FROM test WHERE id = ?', [1], 'assoc_uc'));
		$this->assertEquals([1=>'user1'], self::$db->all('SELECT id, name FROM test WHERE id = ?', [1], 'id', true, 'assoc_ci'));
	}

	/**
	 * @depends testQuery
	 */
	public function testResult() {
		$data = self::$db->get('SELECT * FROM test');
		$this->assertEquals(4, count($data));
		$this->assertEquals(true, isset($data[1]));
		$this->assertEquals(false, isset($data[10]));
		$this->assertEquals(null, @$data[10]);
		$this->assertEquals(['id'=>1,'name'=>'user1'], $data[0]);

		$cnt = 0;
		foreach ($data as $k => $v) {
			$cnt ++;
		}
		$this->assertEquals(4, $cnt);
		$this->assertEquals(null, $data->key());
		$cnt = 0;
		foreach ($data as $k => $v) {
			$cnt ++;
		}
		$this->assertEquals(4, $cnt);
		$this->assertEquals(null, $data->key());

		$data->get();
		$this->assertEquals(4, count($data));
		$this->assertEquals(true, isset($data[1]));
		$this->assertEquals(false, isset($data[10]));
		$this->assertEquals(['id'=>1,'name'=>'user1'], $data[0]);

		$cnt = 0;
		foreach ($data as $k => $v) {
			$cnt ++;
		}
		$this->assertEquals(4, $cnt);
		$this->assertEquals(null, $data->key());
		$cnt = 0;
		foreach ($data as $k => $v) {
			$cnt ++;
		}
		$this->assertEquals(4, $cnt);
		$this->assertEquals(null, $data->key());

		// no exception should be thrown
		(string)$data;
		json_encode($data);
		serialize($data);
	}
	public function testResultEmpty() {
		$data = self::$db->get('SELECT * FROM test WHERE id = ?', 11);
		$this->assertEquals(0, count($data));
		$this->assertEquals(false, isset($data[1]));
		$this->assertEquals(null, @$data[1]);
	}
	public function testResultFakeKey() {
		$data = self::$db->get('SELECT * FROM test WHERE id = ?', 1, 'id', true);
		$this->assertEquals(1, count($data));
		$this->assertEquals(true, isset($data[1]));
		$this->assertEquals(false, isset($data[10]));
		$this->assertEquals('user1', $data[1]);
		$data->get();
		$this->assertEquals(1, count($data));
		$this->assertEquals(true, isset($data[1]));
		$this->assertEquals(false, isset($data[10]));
		$this->assertEquals('user1', $data[1]);

		// no exception should be thrown
		(string)$data;
		json_encode($data);
		serialize($data);
	}
	public function testResultSet() {
		$this->setExpectedException('\vakata\database\DatabaseException');
		$data = self::$db->get('SELECT * FROM test WHERE id = ?', 1, 'id', true);
		$data[2] = 'asdf';
	}
	public function testResultUnset() {
		$this->setExpectedException('\vakata\database\DatabaseException');
		$data = self::$db->get('SELECT * FROM test WHERE id = ?', 1, 'id', true);
		unset($data[1]);
	}
	public function testSettings() {
		$temp = new \vakata\database\Settings('driver://user:pass@host:1000/path?charset=char&timezone=time');

		$this->assertEquals('driver', $temp->type);
		$this->assertEquals('user', $temp->username);
		$this->assertEquals('pass', $temp->password);
		$this->assertEquals('path', $temp->database);
		$this->assertEquals('host', $temp->servername);
		$this->assertEquals('1000', $temp->serverport);
		$this->assertEquals(false, $temp->persist);
		$this->assertEquals('time', $temp->timezone);
		$this->assertEquals('char', $temp->charset);
		$this->assertEquals('driver://user:pass@host:1000/path?charset=char&timezone=time', $temp->original);
	}
	public function testMangledSettings() {
		$temp = new \vakata\database\Settings('pdo://user:pass@mysql:dbname=system;host=127.0.0.1;charset=UTF8');

		$this->assertEquals('pdo', $temp->type);
		$this->assertEquals('user', $temp->username);
		$this->assertEquals('pass', $temp->password);
		$this->assertEquals('mysql:dbname=system;host=127.0.0.1;charset=UTF8', $temp->original);
	}
	public function testStream() {
		$handle = fopen('php://memory', 'w');
		fwrite($handle, 'asdf');
		rewind($handle);
		$id = self::$db->query('INSERT INTO test (name) VALUES(?)', [$handle])->insertId();
		fclose($handle);
		$this->assertEquals('asdf', self::$db->one('SELECT name FROM test WHERE id = ?', [$id]));
	}
}
