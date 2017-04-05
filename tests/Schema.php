<?php
namespace vakata\database\test;

use vakata\database\DB as DBI;

abstract class Schema extends \PHPUnit\Framework\TestCase
{
    protected static $db = null;

    abstract protected function getConnectionString();

    protected function getDB()
    {
        if (!static::$db) {
            $connection = $this->getConnectionString();
            static::$db = new DBI($connection);
            $this->importFile(static::$db, __DIR__ . '/data/' . basename(explode('://', $connection)[0]) . '_schema.sql');
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

    public function testCollection() {
        $books = $this->getDB()->book();
        $this->assertEquals(count($books), 1);
        $this->assertEquals($books[0]['name'], 'Equal rites');
    }
    public function testRelations() {
        $books = $this->getDB()->book()->with('author')->with('tag');
        $this->assertEquals($books[0]['author']['name'], 'Terry Pratchett');
        $this->assertEquals(count($books[0]['tag']), 2);
    }

    public function testSerialize() {
        $this->getDB()->getSchema($this->getDB()->getSchema());
        $books = $this->getDB()->book()->with('author')->with('tag');
        $this->assertEquals($books[0]['author']['name'], 'Terry Pratchett');
        $this->assertEquals(count($books[0]['tag']), 2);
    }

    public function testFilter() {
        $this->assertEquals(count($this->getDB()->book()->filter('name', 'Equal rites')), 1);
        $this->assertEquals(count($this->getDB()->book()->filter('name', 'Not found')), 0);
        $this->assertEquals(count($this->getDB()->book()->filter('author.name', 'Terry Pratchett')), 1);
        $this->assertEquals(count($this->getDB()->book()->filter('author.name', 'Douglas Adams')), 0);
        $this->assertEquals(count($this->getDB()->book()->filter('tag.name', 'Escarina')), 1);
        $this->assertEquals(count($this->getDB()->book()->filter('tag.name', 'Discworld')), 1);
        $this->assertEquals(count($this->getDB()->book()->filter('tag.name', 'None')), 0);
    }

    public function testReadLoop() {
        $author = $this->getDB()->author();
        foreach($author as $k => $a) {
            $this->assertEquals($k + 1, $a['id']);
        }
        foreach($author as $k => $a) {
            $this->assertEquals($k + 1, $a['id']);
        }
    }
    public function testReadIndex() {
        $author = $this->getDB()->author();
        $this->assertEquals($author[0]['name'], 'Terry Pratchett');
        $this->assertEquals($author[2]['name'], 'Douglas Adams');
    }
    public function testReadRelations() {
        $author = $this->getDB()->author()->with('book');
        $this->assertEquals($author[0]['book'][0]['name'], 'Equal rites');
    }
    public function testReadChanges() {
        $this->getDB()->query('INSERT INTO author VALUES(NULL, ?)', ['Stephen King']);
        $author = $this->getDB()->author();
        $this->assertEquals($author[3]['name'], 'Stephen King');
    }
    public function testJoins() {
        $this->assertEquals(
            $this->getDB()->author()
                ->join('book', [ 'author_id' => 'id' ], 'books')
                ->groupById()
                ->having('cnt = ?', [1])
                ->order('cnt DESC')
                ->limit(2)
                ->select(['id', 'cnt' => 'COUNT(books.id)']),
            [ [ 'id' => 1, 'cnt' => 1 ] ]
        );
        $this->getDB()->query('INSERT INTO book VALUES(NULL, ?, ?)', ['Going postal', 1]);
        $this->getDB()->query('INSERT INTO book VALUES(NULL, ?, ?)', ['HGTG', 2]);
        $this->assertEquals(
            $this->getDB()->author()
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
        $author = $this->getDB()->author();
        $res = $author->insert(['name' => 'John Resig' ]);
        $this->assertEquals($res, ['id' => 5]);
        $this->assertEquals($author[4]['name'], 'John Resig');
    }
    public function testUpdate() {
        $author = $this->getDB()->author();
        $author->where('id = 1')->update(['name' => 'Terry Pratchett, Sir']);
        $this->assertEquals($this->getDB()->author()[0]['name'], 'Terry Pratchett, Sir');
        $this->assertEquals($this->getDB()->one('SELECT name FROM author WHERE id = 1'), 'Terry Pratchett, Sir');
    }
    public function testDelete() {
        $author = $this->getDB()->author();
        $author->filter('id', 5)->delete();
        $this->assertEquals(count($this->getDB()->author()), 4);
        $this->assertEquals($this->getDB()->one('SELECT COUNT(id) FROM author'), 4);
    }
}