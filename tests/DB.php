<?php
namespace vakata\database\test;

use vakata\database\DB as DBI;

abstract class DB extends \PHPUnit\Framework\TestCase
{
    protected static $db = null;
    protected static $last = null;

    abstract protected function getConnectionString();

    protected function getDB()
    {
        if (!static::$db || $this->getConnectionString() !== static::$last) {
            $connection = $this->getConnectionString();
            static::$db = new DBI($connection);
            $this->importFile(static::$db, __DIR__ . '/data/' . basename(explode('://', $connection)[0]) . '.sql');
            static::$last = $connection;
        }
        return static::$db;
    }
    protected function importFile(DBI $dbc, string $path)
    {
        $sql = file_get_contents($path);
        $sql = str_replace("\r", '', $sql);
        $sql = preg_replace('(--.*\n)', '', $sql);
        $sql = preg_replace('(\n+)', "\n", $sql);
        $sql = explode(';', $sql);
        foreach (array_filter(array_map("trim", $sql)) as $q) {
            $dbc->query($q);
        }
    }

    public function testDriver()
    {
        $db = new DBI($this->getConnectionString());
        $this->assertEquals(true, $db->test());
        $db = new DBI('mysql://user:invalid@unknown/error');
        $this->assertEquals(false, $db->test());
    }

    public function testCreate()
    {
        $this->assertEquals(true, $this->getDB() instanceof DBI);
    }

    public function testInsertId()
    {
        $this->assertEquals(1, $this->getDB()->query('INSERT INTO log (lvl) VALUES(?)', ['error'])->insertID());
        $this->assertEquals(2, $this->getDB()->query('INSERT INTO log (lvl) VALUES(?)', ['warning'])->insertID());
    }
    public function testAffected()
    {
        $this->assertEquals(1, $this->getDB()->query('INSERT INTO log (lvl) VALUES(?)', ['debug'])->affected());
        $this->assertNotEquals(0, $this->getDB()->query('UPDATE log SET lvl = ?', ['debug'])->affected());
        $this->assertEquals(1, $this->getDB()->query('DELETE FROM log WHERE id = ?', [3])->affected());
    }
    public function testCount()
    {
        $this->assertEquals(2, count($this->getDB()->query('SELECT * FROM log')));
        $this->assertEquals(1, count($this->getDB()->query('SELECT * FROM log WHERE id = 2')));
        $this->assertEquals(0, count($this->getDB()->query('SELECT * FROM log WHERE id = 3')));
    }
    public function testExpand()
    {
        $this->assertEquals(2, count($this->getDB()->query('SELECT * FROM log WHERE id IN (??)', [[1,2,3]])));
        $this->assertEquals(1, count($this->getDB()->query('SELECT * FROM log WHERE id IN (??)', [1,3])));
        $this->assertEquals(
            2,
            count($this->getDB()->query('SELECT * FROM log WHERE id > ? AND id IN (??) AND id < ?', [0, [1,2,3], 4]))
        );
        $this->assertEquals(
            2,
            count(
                $this->getDB()->query(
                    'SELECT * FROM log WHERE id > ? AND (id IN (??) OR id IN (??)) AND id < ?',
                    [0, [1,2,3], [1,2,3], 4]
                )
            )
        );
    }
    public function testPrepare()
    {
        $q1 = $this->getDB()->prepare('SELECT id FROM log WHERE id = ?');
        $q2 = $this->getDB()->prepare('SELECT lvl FROM log WHERE id = ?');
        $q3 = $this->getDB()->prepare('INSERT INTO log (lvl) VALUES (?)');
        $q4 = $this->getDB()->prepare('UPDATE log SET lvl = ? WHERE id = ?');
        $q5 = $this->getDB()->prepare('DELETE FROM log WHERE id = ?');
        $this->assertEquals('debug', $q2->execute([1])->toArray()[0]['lvl']);
        $this->assertEquals(4, $q3->execute(['error'])->insertID());
        $this->assertEquals(1, $q4->execute(['error', 1])->affected());
        $this->assertEquals(5, $q3->execute(['error'])->insertID());
        $this->assertEquals(1, $q1->execute([1])->toArray()[0]['id']);
        $this->assertEquals(2, $q1->execute([2])->toArray()[0]['id']);
        $this->assertEquals('error', $q2->execute([4])->toArray()[0]['lvl']);
        $this->assertEquals(1, $q4->execute(['warning', 5])->affected());
        $this->assertEquals(0, $q5->execute([10])->affected());
        $this->assertEquals(1, $q5->execute([4])->affected());
    }
    public function testToArray()
    {
        $this->assertEquals(
            [],
            $this->getDB()->query('SELECT * FROM log WHERE id IN (??)', [[10,20]])->toArray()
        );
        $this->assertEquals(
            [['id' => 1, 'lvl' => 'error']],
            $this->getDB()->query('SELECT id, lvl FROM log WHERE id IN (??)', [[1]])->toArray()
        );
        $this->assertEquals(
            [['id' => 1, 'lvl' => 'error'], ['id' => 2, 'lvl' => 'debug']],
            $this->getDB()->query('SELECT id, lvl FROM log WHERE id IN (??) ORDER BY id', [[1, 2]])->toArray()
        );
        $this->assertEquals(
            [['id' => 1], ['id' => 2]],
            $this->getDB()->query('SELECT id FROM log WHERE id IN (??) ORDER BY id', [[1, 2]])->toArray()
        );
    }
    public function testAll()
    {
        $this->assertEquals(
            [],
            $this->getDB()->all('SELECT * FROM log WHERE id IN (??) ORDER BY id', [[10,20]])
        );
        $this->assertEquals(
            [['id' => 1, 'lvl' => 'error']],
            $this->getDB()->all('SELECT id, lvl FROM log WHERE id IN (??) ORDER BY id', [[1]])
        );
        $this->assertEquals(
            [['id' => 1, 'lvl' => 'error'], ['id' => 2, 'lvl' => 'debug']],
            $this->getDB()->all('SELECT id, lvl FROM log WHERE id IN (??) ORDER BY id', [[1, 2]])
        );
        $this->assertEquals(
            [ 1 => ['id' => 1, 'lvl' => 'error'], 2 => ['id' => 2, 'lvl' => 'debug']],
            $this->getDB()->all('SELECT id, lvl FROM log WHERE id IN (??) ORDER BY id', [[1, 2]], 'id')
        );
        $this->assertEquals(
            [1 => 'error', 2 => 'debug'],
            $this->getDB()->all('SELECT id, lvl FROM log WHERE id IN (??) ORDER BY id', [[1, 2]], 'id', true)
        );
        $this->assertEquals(
            [1, 2],
            $this->getDB()->all('SELECT id FROM log WHERE id IN (??) ORDER BY id', [[1, 2]])
        );
        $this->assertEquals(
            [['id' => 1 ], ['id' => 2]],
            $this->getDB()->all('SELECT id FROM log WHERE id IN (??) ORDER BY id', [[1, 2]], null, false, false)
        );
    }
    public function testOne()
    {
        $this->assertEquals(
            null,
            $this->getDB()->one('SELECT * FROM log WHERE id IN (??) ORDER BY id', [[10,20]])
        );
        $this->assertEquals(
            ['id' => 1, 'lvl' => 'error'],
            $this->getDB()->one('SELECT id, lvl FROM log WHERE id IN (??) ORDER BY id', [[1]])
        );
        $this->assertEquals(
            ['id' => 1, 'lvl' => 'error'],
            $this->getDB()->one('SELECT id, lvl FROM log WHERE id IN (??) ORDER BY id', [[1, 2]])
        );
        $this->assertEquals(
            1,
            $this->getDB()->one('SELECT id FROM log WHERE id IN (??) ORDER BY id', [[1, 2]])
        );
        $this->assertEquals(
            ['id' => 1 ],
            $this->getDB()->one('SELECT id FROM log WHERE id IN (??) ORDER BY id', [[1, 2]], false)
        );
    }
    public function testMode()
    {
        $connection = $this->getConnectionString();
        $connection .= (strpos($connection, '?') ? '&' : '?') . 'mode=strtoupper';
        $this->assertEquals(
            [ ['ID' => 1 ], ['ID' => 2] ],
            (new DBI($connection))->all('SELECT id FROM log WHERE id IN (??) ORDER BY id', [[1, 2]], null, false, false)
        );
    }
    public function testGet()
    {
        $data = $this->getDB()->get('SELECT id, lvl FROM log WHERE id IN (??) ORDER BY id', [1,2], 'id', true);
        $cnt = 0;
        foreach ($data as $k => $v) {
            $cnt ++;
        }
        $this->assertEquals(2, $cnt);
        $cnt = 0;
        foreach ($data as $k => $v) {
            $cnt ++;
        }
        $this->assertEquals(2, $cnt);
        $this->assertEquals(2, count($data));
        $this->assertEquals(true, isset($data[1]));
        $this->assertEquals(false, isset($data[10]));
        $this->assertEquals('error', $data[1]);
    }
    public function testGetSingle()
    {
        $temp = [];
        foreach (self::$db->get('SELECT id FROM log WHERE id IN (1,2) ORDER BY id') as $v) {
            $temp[] = $v;
        }
        $this->assertEquals([1,2], $temp);
    }
    public function testTransaction()
    {
        $this->getDB()->begin();
        $this->assertEquals(6, $this->getDB()->query('INSERT INTO log (lvl) VALUES(?)', ['debug'])->insertID());
        $this->getDB()->rollback();
        $this->assertEquals(true, $this->getDB()->one('SELECT MAX(id) FROM log') < 6);
        $this->getDB()->begin();
        $this->assertEquals(true, $this->getDB()->query('INSERT INTO log (lvl) VALUES(?)', ['debug'])->insertID() > 5);
        $this->getDB()->commit();
        $this->assertEquals(true, $this->getDB()->one('SELECT MAX(id) FROM log') > 5);
    }
    public function testSerialize()
    {
        // no exception should be thrown
        $data = $this->getDB()->get('SELECT * FROM grps');
        json_encode($data);
        $this->assertEquals(true, strlen(serialize($data)) > 0);
    }

    public function testRows()
    {
        $this->assertEquals(
            [],
            $this->getDB()->rows('SELECT * FROM log WHERE id IN (??) ORDER BY id', [[10,20]])
        );
        $this->assertEquals(
            [['id' => 1, 'lvl' => 'error']],
            $this->getDB()->rows('SELECT id, lvl FROM log WHERE id IN (??) ORDER BY id', [[1]])
        );
        $this->assertEquals(
            [['id' => 1, 'lvl' => 'error'], ['id' => 2, 'lvl' => 'debug']],
            $this->getDB()->rows('SELECT id, lvl FROM log WHERE id IN (??) ORDER BY id', [[1, 2]])
        );
        $this->assertEquals(
            [ 1 => ['id' => 1, 'lvl' => 'error'], 2 => ['id' => 2, 'lvl' => 'debug']],
            $this->getDB()->rows('SELECT id, lvl FROM log WHERE id IN (??) ORDER BY id', [[1, 2]], 'id')
        );
        $this->assertEquals(
            [['id'=>1], ['id'=>2]],
            $this->getDB()->rows('SELECT id FROM log WHERE id IN (??) ORDER BY id', [[1, 2]])
        );
    }
    public function testRow()
    {
        $this->assertEquals(
            null,
            $this->getDB()->row('SELECT * FROM log WHERE id IN (??) ORDER BY id', [[10,20]])
        );
        $this->assertEquals(
            ['id' => 1, 'lvl' => 'error'],
            $this->getDB()->row('SELECT id, lvl FROM log WHERE id IN (??) ORDER BY id', [[1]])
        );
        $this->assertEquals(
            ['id' => 1, 'lvl' => 'error'],
            $this->getDB()->row('SELECT id, lvl FROM log WHERE id IN (??) ORDER BY id', [[1, 2]])
        );
        $this->assertEquals(
            ['id'=>1],
            $this->getDB()->row('SELECT id FROM log WHERE id IN (??) ORDER BY id', [[1, 2]])
        );
    }
    public function testCol()
    {
        $this->assertEquals(
            [],
            $this->getDB()->col('SELECT * FROM log WHERE id IN (??) ORDER BY id', [[10,20]])
        );
        $this->assertEquals(
            [1],
            $this->getDB()->col('SELECT id FROM log WHERE id IN (??) ORDER BY id', [[1]])
        );
        $this->assertEquals(
            [1],
            $this->getDB()->col('SELECT id, lvl FROM log WHERE id IN (??) ORDER BY id', [[1]])
        );
        $this->assertEquals(
            [1 => 'error', 2 => 'debug'],
            $this->getDB()->col('SELECT id, lvl FROM log WHERE id IN (??) ORDER BY id', [[1, 2]], 'id')
        );
    }
    public function testVal()
    {
        $this->assertEquals(
            null,
            $this->getDB()->val('SELECT * FROM log WHERE id IN (??) ORDER BY id', [[10,20]])
        );
        $this->assertEquals(
            1,
            $this->getDB()->val('SELECT id FROM log WHERE id IN (??) ORDER BY id', [[1]])
        );
        $this->assertEquals(
            1,
            $this->getDB()->val('SELECT id, lvl FROM log WHERE id IN (??) ORDER BY id', [[1]])
        );
        $this->assertEquals(
            'error',
            $this->getDB()->val('SELECT lvl FROM log WHERE id IN (??) ORDER BY id', [[1, 2]])
        );
        $this->assertEquals(
            '1',
            $this->getDB()->valString('SELECT id, lvl FROM log WHERE id IN (??) ORDER BY id', [[1]])
        );
        $this->assertEquals(
            0,
            $this->getDB()->valInt('SELECT lvl FROM log WHERE id IN (??) ORDER BY id', [[1, 2]])
        );
    }
}
