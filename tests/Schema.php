<?php
namespace vakata\database\test;

use vakata\database\DB as DBI;
use vakata\database\DBException as DBE;

abstract class Schema extends \PHPUnit\Framework\TestCase
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

    public function testIDs()
    {
        $books = $this->getDB()->book()
            ->with('author')->filter('author.name', 'Terry Pratchett')->ids();
        $this->assertEquals($books, ['1']);
    }

    public function testCollection()
    {
        $books = $this->getDB()->book();
        $this->assertEquals(count($books), 1);
        $this->assertEquals($books[0]['name'], 'Equal rites');
    }
    public function testRelations()
    {
        $books = $this->getDB()->book()->with('author')->with('tag');
        $this->assertEquals($books[0]['author']['name'], 'Terry Pratchett');
        $this->assertEquals(count($books[0]['tag']), 2);
    }
    public function testRemoteRelations()
    {
        $author = $this->getDB()->author()->with('book')->with('book.tag');
        $this->assertEquals(count($author[0]['book'][0]['tag']), 2);
        $this->assertEquals(count($author[2]['book']), 0);
    }

    public function testSerialize()
    {
        $this->getDB()->parseSchema();
        $this->getDB()->setSchema($this->getDB()->getSchema());
        $books = $this->getDB()->book()->with('author')->with('tag');
        $this->assertEquals($books[0]['author']['name'], 'Terry Pratchett');
        $this->assertEquals(count($books[0]['tag']), 2);
    }

    public function testFilter()
    {
        $this->assertEquals(count($this->getDB()->book()->filter('name', 'Equal rites')), 1);
        $this->assertEquals(count($this->getDB()->book()->filter('name', 'Not found')), 0);
        $this->assertEquals(count($this->getDB()->book()->filter('author.name', 'Terry Pratchett')), 1);
        $this->assertEquals(count($this->getDB()->book()->filter('author.name', 'Douglas Adams')), 0);
        $this->assertEquals(count($this->getDB()->book()->filter('tag.name', 'Escarina')), 1);
        $this->assertEquals(count($this->getDB()->book()->filter('tag.name', 'Discworld')), 1);
        $this->assertEquals(count($this->getDB()->book()->filter('tag.name', 'None')), 0);
        $this->assertEquals(count($this->getDB()->book()->filter('author_id', ['gt' => 0])), 1);
        $this->assertEquals(count($this->getDB()->book()->filter('author_id', ['gt' => 1])), 0);
        $this->assertEquals(count($this->getDB()->book()->filter('author_id', ['gte' => 1])), 1);
        $this->assertEquals(count($this->getDB()->book()->filter('author_id', ['lt' => 1])), 0);
        $this->assertEquals(count($this->getDB()->book()->filter('author_id', ['lt' => 2])), 1);
        $this->assertEquals(count($this->getDB()->book()->filter('author_id', ['lte' => 1])), 1);
        $this->assertEquals(count($this->getDB()->book()->filter('author_id', ['gt' => 0], true)), 0);
        $this->assertEquals(count($this->getDB()->book()->filter('author_id', ['gt' => 1], true)), 1);
        $this->assertEquals(count($this->getDB()->book()->filter('author_id', ['gte' => 1], true)), 0);
        $this->assertEquals(count($this->getDB()->book()->filter('author_id', ['lt' => 1], true)), 1);
        $this->assertEquals(count($this->getDB()->book()->filter('author_id', ['lt' => 2], true)), 0);
        $this->assertEquals(count($this->getDB()->book()->filter('author_id', ['lte' => 1], true)), 0);
    }

    public function testReadLoop()
    {
        $author = $this->getDB()->author();
        foreach ($author as $k => $a) {
            $this->assertEquals($k + 1, $a['id']);
        }
        foreach ($author as $k => $a) {
            $this->assertEquals($k + 1, $a['id']);
        }
    }
    public function testReadIndex()
    {
        $author = $this->getDB()->author();
        $this->assertEquals($author[0]['name'], 'Terry Pratchett');
        $this->assertEquals($author[2]['name'], 'Douglas Adams');
    }
    public function testReadRelations()
    {
        $author = $this->getDB()->author()->with('book');
        $this->assertEquals($author[0]['book'][0]['name'], 'Equal rites');
    }
    public function testReadChanges()
    {
        $this->getDB()->query('INSERT INTO author (name) VALUES (?)', ['Stephen King']);
        $author = $this->getDB()->author();
        $this->assertEquals($author[3]['name'], 'Stephen King');
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
        $books = $this->getDB()->tableMapped('book')->with('author');
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
            count(
                $this->getDB()->book(true)->any([['tag.name', 'Discworld'],['author.name', 'Douglas Adams']])
            ),
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

    public function testJoins()
    {
        $this->assertEquals(
            $this->getDB()->author()
                ->join('book', [ 'author_id' => 'id' ], 'books')
                ->groupById()
                ->having('COUNT(books.id) = ?', [1]) // ->having('cnt = ?', [1]) - not standard SQL!
                ->order('cnt DESC')
                ->limit(2)
                ->select(['id', 'cnt' => 'COUNT(books.id)']),
            [ [ 'id' => 1, 'cnt' => 1 ] ]
        );
        $this->getDB()->query('INSERT INTO book(name, author_id) VALUES(?, ?)', ['Going postal', 1]);
        $this->getDB()->query('INSERT INTO book(name, author_id) VALUES(?, ?)', ['HGTG', 2]);
        $this->assertEquals(
            $this->getDB()->author()
                ->join('book', [ 'author_id' => 'id' ], 'books')
                ->groupById()
                ->having('COUNT(books.id) > ?', [0]) // ->having('cnt > ?', [0]) - not standard SQL!
                ->order('cnt ASC')
                ->limit(2)
                ->select(['id', 'cnt' => 'COUNT(books.id)']),
            [ [ 'id' => 2, 'cnt' => 1 ], [ 'id' => 1, 'cnt' => 2 ] ]
        );
    }


    public function testCreate()
    {
        $author = $this->getDB()->author();
        $res = $author->insert(['name' => 'John Resig' ]);
        $this->assertEquals($res, ['id' => 5]);
        $this->assertEquals($author[4]['name'], 'John Resig');
    }
    public function testUpdate()
    {
        $author = $this->getDB()->author();
        $author->where('id = 1')->update(['name' => 'Terry Pratchett, Sir']);
        $this->assertEquals($this->getDB()->author()[0]['name'], 'Terry Pratchett, Sir');
        $this->assertEquals($this->getDB()->one('SELECT name FROM author WHERE id = 1'), 'Terry Pratchett, Sir');
    }
    public function testDelete()
    {
        $author = $this->getDB()->author();
        $author->filter('id', 5)->delete();
        $this->assertEquals(count($this->getDB()->author()), 4);
        $this->assertEquals($this->getDB()->one('SELECT COUNT(id) FROM author'), 4);
    }
    public function testAny()
    {
        $author = $this->getDB()->author();
        $this->assertEquals(count($author->reset()->any([['id', 1], ['id', 2], ['id', [3,4]]])), 4);
        $this->assertEquals(count($author->reset()->any([['id', 1], ['id', 2]])), 2);
    }
    public function testAll()
    {
        $author = $this->getDB()->author();
        $this->assertEquals(count($author->reset()->all([['id', 1], ['id', 2], ['id', [3,4]]])), 0);
        $this->assertEquals(count($author->reset()->all([['id', 1], ['id', 2]])), 0);
        $this->assertEquals(count($author->reset()->all([['id', 1], ['id', ['lt'=>2]]])), 1);
        $this->assertEquals(count($author->reset()->all([['id', 1], ['id', ['not'=>2]]])), 1);
        $this->assertEquals(count($author->reset()->all([['id', 2], ['id', ['not'=>2]]])), 0);
    }
    public function testLike()
    {
        $author = $this->getDB()->author();
        $this->assertEquals(count($author->reset()->filter('name', ['like'=>'Brad'])), 0);
        $this->assertEquals(count($author->reset()->filter('name', ['contains'=>'Brad'])), 1);
        $this->assertEquals(count($author->reset()->filter('name', ['icontains'=>'BRAD'])), 1);
        $this->assertEquals(count($author->reset()->filter('name', ['like'=>'%Ivan%'])), 0);
        $this->assertEquals(
            count($author->reset()->any([['name', ['like'=>'King']], ['name', ['contains'=>'Brad']]])),
            1
        );
    }
    public function testStrictDisabled()
    {
        $db = $this->getDB();
        $id = $db->tag()->insert([
            'name' => str_repeat('a', 256)
        ]);
        $this->assertEquals(str_repeat('a', 255), $db->tag()->filter('id', $id['id'])[0]['name']);
    }
    public function testStrictEnabled()
    {
        $this->expectException(DBE::class);
        $db = new DBI($this->getConnectionString() . '&strict=1');
        $db->tag()->insert([
            'name' => str_repeat('a', 256)
        ]);
    }

    public function testCount()
    {
        $this->assertEquals(
            4,
            $this->getDB()->author()->count()
        );
        $this->assertEquals(
            4,
            $this->getDB()->author()->with('book')->count()
        );
        $this->assertEquals(
            4,
            $this->getDB()->author()->join('book', ['author_id' => 'id'], 'tmp')->count()
        );
        $this->assertEquals(
            3,
            $this->getDB()->author()->join('book', ['author_id' => 'id'], 'tmp', false)->count()
        );
        $this->assertEquals(
            1,
            $this->getDB()->author()->join('book', ['author_id' => 'id'], 'tmp')
                ->where("tmp.name LIKE '%rites%'")
                ->count()
        );
        $this->assertEquals(
            0,
            $this->getDB()->author()->join('book', ['author_id' => 'id'], 'tmp')
                ->where("tmp.name LIKE '%nonexisting%'")
                ->count()
        );
        $this->assertEquals(
            0,
            $this->getDB()->author()->join('book', ['author_id' => 'id'], 'tmp', false)
                ->where("tmp.name LIKE '%nonexisting%'")
                ->count()
        );
    }
    public function testCount1()
    {
        $this->assertEquals(
            4,
            $this->getDB()->author()->count()
        );
        $this->assertEquals(
            4,
            $this->getDB()->author()->join('book1', ['author_id' => 'id'], null)->count()
        );
        $this->assertEquals(
            0,
            $this->getDB()->author()->join('book1', ['author_id' => 'id'], null, false)->count()
        );
        $this->getDB()->query("INSERT INTO book1 (author_id, name) VALUES (1, 'rites')");
        $this->getDB()->query("INSERT INTO book1 (author_id, name) VALUES (1, 'test')");
        $this->assertEquals(
            4,
            $this->getDB()->author()->join('book1', ['author_id' => 'id'], null)->count()
        );
        $this->assertEquals(
            2,
            $this->getDB()->author()->join('book1', ['author_id' => 'id'], null, false)->count()
        );
        $this->assertEquals(
            1,
            $this->getDB()->author()->join('book1', ['author_id' => 'id'], null)
                ->where("book1.name LIKE '%rites%'")
                ->count()
        );
        $this->assertEquals(
            0,
            $this->getDB()->author()->join('book1', ['author_id' => 'id'], null)
                ->where("book1.name LIKE '%nonexisting%'")
                ->count()
        );
        $this->assertEquals(
            0,
            $this->getDB()->author()->join('book1', ['author_id' => 'id'], null, false)
                ->where("book1.name LIKE '%nonexisting%'")
                ->count()
        );
    }
}
