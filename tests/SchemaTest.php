<?php
namespace vakata\database\test;

class SchemaTest extends \PHPUnit_Framework_TestCase
{
    protected static $db       = null;

    public static function setUpBeforeClass() {
        $sql = file_get_contents(__DIR__ . '/dump.sql');
        $sql = explode(';', $sql);
        self::$db = new \vakata\database\DB('mysqli://root@127.0.0.1/test');
        self::$db->query("SET FOREIGN_KEY_CHECKS = 0");
        self::$db->query("DROP TABLE IF EXISTS author");
        self::$db->query("DROP TABLE IF EXISTS book");
        self::$db->query("DROP TABLE IF EXISTS tag");
        self::$db->query("DROP TABLE IF EXISTS book_tag");
        foreach ($sql as $query) {
            if (strlen(trim($query, " \t\r\n"))) {
                self::$db->query($query);
            }
        }
    }
    public static function tearDownAfterClass() {
        self::$db->query("SET FOREIGN_KEY_CHECKS = 0");
        self::$db->query("DROP TABLE IF EXISTS author");
        self::$db->query("DROP TABLE IF EXISTS book");
        self::$db->query("DROP TABLE IF EXISTS tag");
        self::$db->query("DROP TABLE IF EXISTS book_tag");
    }
    protected function setUp() {
        // self::$db->query("TRUNCATE TABLE test;");
    }
    protected function tearDown() {
        // self::$db->query("TRUNCATE TABLE test;");
    }

    public function testCollection() {
        $books = self::$db->book();
        $this->assertEquals(count($books), 1);
        $this->assertEquals($books[0]['name'], 'Equal rites');
    }
    public function testRelations() {
        $books = self::$db->book()->with('author')->with('tag');
        $this->assertEquals($books[0]['author']['name'], 'Terry Pratchett');
        $this->assertEquals(count($books[0]['tag']), 2);
    }

    public function testFilter() {
        $this->assertEquals(count(self::$db->book()->filter('name', 'Equal rites')), 1);
        $this->assertEquals(count(self::$db->book()->filter('name', 'Not found')), 0);
        $this->assertEquals(count(self::$db->book()->filter('author.name', 'Terry Pratchett')), 1);
        $this->assertEquals(count(self::$db->book()->filter('author.name', 'Douglas Adams')), 0);
        $this->assertEquals(count(self::$db->book()->filter('tag.name', 'Escarina')), 1);
        $this->assertEquals(count(self::$db->book()->filter('tag.name', 'Discworld')), 1);
        $this->assertEquals(count(self::$db->book()->filter('tag.name', 'None')), 0);
    }

    public function testReadLoop() {
        $author = self::$db->author();
        foreach($author as $k => $a) {
            $this->assertEquals($k + 1, $a['id']);
        }
        foreach($author as $k => $a) {
            $this->assertEquals($k + 1, $a['id']);
        }
    }
    public function testReadIndex() {
        $author = self::$db->author();
        $this->assertEquals($author[0]['name'], 'Terry Pratchett');
        $this->assertEquals($author[2]['name'], 'Douglas Adams');
    }
    public function testReadRelations() {
        $author = self::$db->author()->with('book');
        $this->assertEquals($author[0]['book'][0]['name'], 'Equal rites');
    }
    public function testReadChanges() {
        self::$db->query('INSERT INTO author VALUES(NULL, ?)', ['Stephen King']);
        $author = self::$db->author();
        $this->assertEquals($author[3]['name'], 'Stephen King');
    }
    public function testJoins() {
        $this->assertEquals(
            self::$db->author()
                ->join('book', [ 'author_id' => 'id' ], 'books')
                ->groupById()
                ->having('cnt = ?', [1])
                ->order('cnt DESC')
                ->limit(2)
                ->select(['id', 'cnt' => 'COUNT(books.id)']),
            [ [ 'id' => 1, 'cnt' => 1 ] ]
        );
        self::$db->query('INSERT INTO book VALUES(NULL, ?, ?)', ['Going postal', 1]);
        self::$db->query('INSERT INTO book VALUES(NULL, ?, ?)', ['HGTG', 2]);
        $this->assertEquals(
            self::$db->author()
                ->join('book', [ 'author_id' => 'id' ], 'books')
                ->groupById()
                ->having('cnt > ?', [0])
                ->order('cnt ASC')
                ->limit(2)
                ->select(['id', 'cnt' => 'COUNT(books.id)']),
            [ [ 'id' => 2, 'cnt' => 1 ], [ 'id' => 1, 'cnt' => 2 ] ]
        );
    }

    public function testCreate() {
        $author = self::$db->author();
        $res = $author->insert(['name' => 'John Resig' ]);
        $this->assertEquals($res, ['id' => 5]);
        $this->assertEquals($author[4]['name'], 'John Resig');
    }
    public function testUpdate() {
        $author = self::$db->author();
        $author->where('id = 1')->update(['name' => 'Terry Pratchett, Sir']);
        $this->assertEquals(self::$db->author()[0]['name'], 'Terry Pratchett, Sir');
        $this->assertEquals(self::$db->one('SELECT name FROM author WHERE id = 1'), 'Terry Pratchett, Sir');
    }
    public function testDelete() {
        $author = self::$db->author();
        $author->filter('id', 5)->delete();
        $this->assertEquals(count(self::$db->author()), 4);
        $this->assertEquals(self::$db->one('SELECT COUNT(id) FROM author'), 4);
    }
}
