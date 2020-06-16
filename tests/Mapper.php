<?php
namespace vakata\database\test;

use vakata\database\DB as DBI;
use vakata\database\DBException as DBE;

abstract class Mapper extends \PHPUnit\Framework\TestCase
{
    protected static $db = null;

    abstract protected function getConnectionString();

    public static function tearDownAfterClass(): void
    {
        static::$db = null;
    }

    protected function getDB()
    {
        if (!static::$db) {
            $connection = $this->getConnectionString();
            static::$db = new DBI($connection);
            $this->importFile(
                static::$db,
                __DIR__ . '/data/' . basename(explode('://', $connection)[0]) . '_schema.sql'
            );
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

    public function testMappedCollection()
    {
        $books = $this->getDB()->book(true);
        foreach ($books as $book) {
            $this->assertEquals($book->name, 'Equal rites');
        }
        $this->assertEquals(count($books), 1);
        $this->assertEquals($books[0]->name, 'Equal rites');
        iterator_to_array($books);
        foreach ($books as $book) {
            $this->assertEquals($book->name, 'Equal rites');
        }
    }
    public function testMappedRelations()
    {
        $books = $this->getDB()->book(true);
        $this->assertEquals('Terry Pratchett', $books[0]->author->name);
        $this->assertEquals(2, count($books[0]->tag));
        $this->assertEquals('Terry Pratchett', $books[0]->author->book[0]->tag[0]->book[0]->author->name);
    }

    public function testMappedRelationsWith()
    {
        $books = $this->getDB()->table('book', true)->with('author');
        $this->assertEquals($books[0]->author->name, 'Terry Pratchett');
        $this->assertEquals(count($books[0]->tag), 2);
    }

    public function testMappedFilter()
    {
        $this->assertEquals(count($this->getDB()->book(true)->filter('name', 'Equal rites')), 1);
        $this->assertEquals(count($this->getDB()->book(true)->filter('name', 'Not found')), 0);
        $this->assertEquals(count($this->getDB()->book(true)->filter('author.name', 'Terry Pratchett')), 1);
        $this->assertEquals(count($this->getDB()->book(true)->filter('author.name', 'Douglas Adams')), 0);
        $this->assertEquals(count($this->getDB()->book(true)->filter('tag.name', 'Escarina')), 1);
        $this->assertEquals(count($this->getDB()->book(true)->filter('tag.name', 'Discworld')), 1);
        $this->assertEquals(count($this->getDB()->book(true)->filter('tag.name', 'None')), 0);
        $this->assertEquals(
            count(
                $this->getDB()->book(true)->any([['author.name', 'Terry Pratchett'],['author.name', 'Douglas Adams']])
            ),
            1
        );
        $this->assertEquals(
            count(
                $this->getDB()->book(true)->all([['author.name', 'Terry Pratchett'],['author.name', 'Douglas Adams']])
            ),
            0
        );
        $this->assertEquals(
            count(
                $this->getDB()->book(true)->all([['tag.name', 'Discworld'],['author.name', 'Douglas Adams']])
            ),
            0
        );
        $this->assertEquals(
            count($this->getDB()->book(true)->any([['tag.name', 'Discworld'],['author.name', 'Douglas Adams']])),
            1
        );
    }

    public function testMappedReadLoop()
    {
        $author = $this->getDB()->author(true);
        foreach ($author as $k => $a) {
            $this->assertEquals($k + 1, $a->id);
        }
        foreach ($author as $k => $a) {
            $this->assertEquals($k + 1, $a->id);
        }
    }
    public function testMappedReadIndex()
    {
        $author = $this->getDB()->author(true);
        $this->assertEquals($author[0]->name, 'Terry Pratchett');
        $this->assertEquals($author[2]->name, 'Douglas Adams');
    }
    public function testMappedReadRelations()
    {
        $author = $this->getDB()->author(true);
        $this->assertEquals($author[0]->book[0]->name, 'Equal rites');
        $this->assertEquals($author[0]->book[0]->tag[1]->name, 'Escarina');
    }

    public function testReadChanges()
    {
        self::$db->query('INSERT INTO author (name) VALUES(?)', ['Stephen King']);
        $author = $this->getDB()->author(true);
        $this->assertEquals($author[3]->name, 'Stephen King');
    }

    public function testCreate()
    {
        $author = $this->getDB()->author(true);
        
        $resig = $author->create();
        $resig->name = 'John Resig';
        $resig->save();

        $this->assertEquals($author[4]->name, 'John Resig');
        $this->assertEquals(self::$db->one('SELECT name FROM author WHERE id = 5'), 'John Resig');
        $this->assertEquals($author[0]->book[0]->name, 'Equal rites');
        $this->assertEquals($author[0]->book[0]->tag[1]->name, 'Escarina');
    }
    public function testUpdate()
    {
        $author = $this->getDB()->author(true);
        $author[0]->name = 'Terry Pratchett, Sir';
        $author[0]->save();
        $this->assertEquals($author[0]->name, 'Terry Pratchett, Sir');
        $this->assertEquals(self::$db->one('SELECT name FROM author WHERE id = 1'), 'Terry Pratchett, Sir');
    }
    public function testDelete()
    {
        $author = $this->getDB()->author(true);
        $author[4]->delete();
        $author = $this->getDB()->author(true);
        $this->assertEquals(count($author), 4);
        $this->assertEquals(self::$db->one('SELECT COUNT(id) FROM author'), 4);
    }
    public function testChangePK()
    {
        $author = $this->getDB()->author(true);
        $this->assertEquals($author[2]->name, 'Douglas Adams');
        $this->assertEquals($author[2]->id, 3);
        $author[2]->id = 42;
        $author[2]->save();
        $this->assertEquals('Douglas Adams', $this->getDB()->author(true)[3]->name);
        $this->assertEquals(42, $this->getDB()->author(true)[3]->id);
        $this->assertEquals(self::$db->one('SELECT name FROM author WHERE id = 42'), 'Douglas Adams');
    }
    public function testCreateRelationFromDB()
    {
        $author = $this->getDB()->author(true);
        self::$db->query(
            'INSERT INTO book (name, author_id) VALUES(?, ?)',
            ['The Hitchhiker\'s Guide to the Galaxy', 42]
        );
        $this->assertEquals('The Hitchhiker\'s Guide to the Galaxy', $author[3]->book[0]->name);
    }
}
