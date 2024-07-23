<?php
namespace vakata\database\test;

use vakata\collection\Collection;
use vakata\database\DB as DBI;
use vakata\database\DBException as DBE;

class Mapper2Test extends \PHPUnit\Framework\TestCase
{
    protected function getConnectionString()
    {
        return "postgre://postgres:postgres@".gethostname().".local/test?schema=public";
    }

    protected function reset(): DBI
    {
        $dbc = new DBI($this->getConnectionString());
        $this->importFile(
            $dbc,
            __DIR__ . '/data/mapper2.sql'
        );
        return $dbc;
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

    public function testFullRead()
    {
        $dbc = $this->reset();
        $cars = $dbc->tableMapped('cars')->sort('car')->collection();
        $dbc->query("UPDATE cars SET name = 'changed'");
        $temp = [];
        foreach ($cars as $car) {
            $temp[] = $car->car;
            $temp[] = $car->name;
        }
        $this->assertEquals($temp, [ 1, 'car1', 2, 'car2', 3, 'car3' ]);
    }
    public function testLazyColumns()
    {
        $dbc = $this->reset();
        $cars = $dbc->tableMapped('cars')->sort('car')->columns(['car'])->collection();
        $dbc->query("UPDATE cars SET name = 'changed' WHERE car = 2");
        $temp = [];
        foreach ($cars as $car) {
            $temp[] = $car->car;
            $temp[] = $car->name;
        }
        $this->assertEquals($temp, [ 1, 'car1', 2, 'changed', 3, 'car3' ]);
    }
    public function testLazyOneRelation()
    {
        $dbc = $this->reset();
        $drivers = $dbc->tableMapped('drivers')->sort('driver')->collection();
        $dbc->query("UPDATE avatars SET url = 'changed'");
        $temp = [];
        foreach ($drivers as $driver) {
            $temp[] = $driver->driver;
            $temp[] = $driver->avatars?->url;
        }
        $this->assertEquals($temp, [ 1, null, 2, 'changed', 3, 'changed' ]);
    }
    public function testPreloadedOneRelation()
    {
        $dbc = $this->reset();
        $drivers = $dbc->tableMapped('drivers')->with('avatars')->sort('driver')->collection();
        $dbc->query("UPDATE avatars SET url = 'changed'");
        $temp = [];
        foreach ($drivers as $driver) {
            $temp[] = $driver->driver;
            $temp[] = $driver->avatars?->url;
        }
        $this->assertEquals($temp, [ 1, null, 2, 'avatar', 3, 'avatar' ]);
    }
    public function testLazyManyRelation()
    {
        $dbc = $this->reset();
        $avatar = $dbc->tableMapped('avatars')[0];
        $dbc->query("UPDATE drivers SET name = 'changed' WHERE driver = 3");
        $temp = [];
        foreach ($avatar->drivers as $driver) {
            $temp[] = $driver->driver;
            $temp[] = $driver->name;
        }
        $this->assertEquals($temp, [ 2, 'driver2', 3, 'changed' ]);
    }
    public function testPreloadedManyRelation()
    {
        $dbc = $this->reset();
        $avatar = $dbc->tableMapped('avatars')->with('drivers')[0];
        $dbc->query("UPDATE drivers SET name = 'changed'");
        $temp = [];
        foreach ($avatar->drivers as $driver) {
            $temp[] = $driver->driver;
            $temp[] = $driver->name;
        }
        $this->assertEquals($temp, [ 2, 'driver2', 3, 'driver3' ]);
    }
    public function testLazyPivotRelation()
    {
        $dbc = $this->reset();
        $driver = $dbc->tableMapped('drivers')->filter('driver', 2)[0];
        $dbc->query("UPDATE cars SET name = 'changed'");
        $temp = [];
        foreach ($driver->cars as $car) {
            $temp[] = $car->car;
            $temp[] = $car->name;
        }
        $this->assertEquals($temp, [ 1, 'changed', 2, 'changed' ]);
    }
    public function testPreloadedPivotRelation()
    {
        $dbc = $this->reset();
        $driver = $dbc->tableMapped('drivers')->with('cars')->filter('driver', 2)[0];
        $dbc->query("UPDATE cars SET name = 'changed'");
        $temp = [];
        foreach ($driver->cars as $car) {
            $temp[] = $car->car;
            $temp[] = $car->name;
        }
        $this->assertEquals($temp, [ 1, 'car1', 2, 'car2' ]);
    }
    public function testRelationChain()
    {
        $dbc = $this->reset();
        $this->assertEquals(
            $dbc->tableMapped('cars')->filter('car', 3)[0]->drivers[0]->avatars->url,
            'avatar'
        );
    }
    public function testSingleInstance()
    {
        $dbc = $this->reset();
        $dbc->tableMapped('avatars')[0]->url = 'changed-in-memory';
        $this->assertEquals(
            $dbc->tableMapped('cars')->filter('car', 3)[0]->drivers[0]->avatars->url,
            'changed-in-memory'
        );
    }
    public function testSimpleUpdate()
    {
        $dbc = $this->reset();
        $avatar = $dbc->tableMapped('avatars')[0];
        $avatar->url = 'changed-in-memory';
        $dbc->getMapper('avatars')->save($avatar);
        $this->assertEquals(
            $dbc->one("SELECT url FROM avatars"),
            'changed-in-memory'
        );
    }
    public function testSimpleCreate()
    {
        $dbc = $this->reset();
        $avatar = $dbc->tableMapped('avatars')->create();
        $avatar->url = 'created';
        $dbc->getMapper('avatars')->save($avatar);
        $this->assertEquals(
            $dbc->one("SELECT url FROM avatars WHERE avatar = 2"),
            'created'
        );
    }
    public function testSimpleDelete()
    {
        $dbc = $this->reset();
        $dbc->query("UPDATE drivers SET avatar = NULL");
        $avatar = $dbc->tableMapped('avatars')[0];
        $dbc->getMapper('avatars')->delete($avatar);
        $this->assertEquals(
            $dbc->one("SELECT url FROM avatars"),
            null
        );
    }
    public function testOneRelationDelete()
    {
        $dbc = $this->reset();
        $avatar = $dbc->tableMapped('avatars')[0];
        $dbc->getMapper('avatars')->delete($avatar, true);
        $this->assertEquals(
            $dbc->one("SELECT url FROM avatars"),
            null
        );
        $this->assertEquals(
            $dbc->one("SELECT COUNT(*) FROM drivers WHERE avatar IS NOT NULL"),
            0
        );
    }
    public function testPivotRelationDelete()
    {
        $dbc = $this->reset();
        $dbc->query("TRUNCATE race_participants");

        $car = $dbc->tableMapped('cars')->filter('car', 1)[0];
        $this->assertEquals(
            $dbc->one("SELECT COUNT(*) FROM driver_cars WHERE car = 1"),
            2
        );
        $dbc->getMapper('cars')->delete($car, true);
        $this->assertEquals(
            $dbc->one("SELECT COUNT(*) FROM driver_cars WHERE car = 1"),
            0
        );
    }
    public function testSimplePKChange()
    {
        $dbc = $this->reset();
        $dbc->query("TRUNCATE race_participants");
        $dbc->query("TRUNCATE driver_cars");

        $car = $dbc->tableMapped('cars')->filter('car', 1)[0];
        $car->car = 4;
        $this->assertEquals(
            $dbc->one("SELECT COUNT(*) FROM cars WHERE car = 1"),
            1
        );
        $dbc->getMapper('cars')->save($car);
        $this->assertEquals(
            $dbc->one("SELECT COUNT(*) FROM cars WHERE car = 1"),
            0
        );
        $this->assertEquals(
            $dbc->one("SELECT COUNT(*) FROM cars WHERE car = 4"),
            1
        );
    }
    // public function testComplexPKChange()
    // {
    //     $dbc = $this->reset();
    //     $car = $dbc->tableMapped('cars')->filter('car', 1)[0];
    //     $car->car = 4;
    //     $this->assertEquals(
    //         $dbc->one("SELECT COUNT(*) FROM cars WHERE car = 1"),
    //         1
    //     );
    //     $dbc->getMapper('cars')->save($car, true);
    //     $this->assertEquals(
    //         $dbc->one("SELECT COUNT(*) FROM cars WHERE car = 1"),
    //         0
    //     );
    //     $this->assertEquals(
    //         $dbc->one("SELECT COUNT(*) FROM cars WHERE car = 4"),
    //         1
    //     );
    // }

    public function testDirtyRelation()
    {
        $dbc = $this->reset();

        $driver = $dbc->tableMapped('drivers')->filter('driver', 2)[0];
        $this->assertEquals($driver->avatars?->url, 'avatar');
        $avatar = $dbc->tableMapped('avatars')->filter('avatar', 1)[0];
        $avatar->url = 'changed';
        $this->assertEquals($driver->avatars?->url, 'changed');
        $dbc->getMapper('drivers')->save($driver, true);
        $this->assertEquals(
            $dbc->one("SELECT url FROM avatars"),
            'changed'
        );
    }
    public function testDereferenceNullRelation()
    {
        $dbc = $this->reset();

        $driver = $dbc->tableMapped('drivers')->filter('driver', 2)[0];
        $driver->avatars = null;
        $dbc->getMapper('drivers')->save($driver, true);
        $this->assertEquals(
            $dbc->one("SELECT avatar FROM drivers WHERE driver = 2"),
            null
        );
    }
    public function testDereferenceNewRelation()
    {
        $dbc = $this->reset();

        $avatar = $dbc->tableMapped('avatars')->create();
        $avatar->url = 'created';
        $driver = $dbc->tableMapped('drivers')->filter('driver', 2)[0];
        $driver->avatars = $avatar;
        $dbc->getMapper('drivers')->save($driver, true);
        $this->assertEquals(
            $dbc->one("SELECT avatar FROM drivers WHERE driver = 2"),
            2
        );
        $this->assertEquals(
            $dbc->one("SELECT url FROM avatars WHERE avatar = 2"),
            'created'
        );
    }
    public function testDereferenceChangeRelation()
    {
        $dbc = $this->reset();

        $avatar = $dbc->tableMapped('avatars')->create();
        $avatar->url = 'created';
        $dbc->getMapper('avatars')->save($avatar, true);
        $this->assertEquals($avatar->avatar, 2);
        $driver = $dbc->tableMapped('drivers')->filter('driver', 2)[0];
        $driver->avatars = $avatar;
        $dbc->getMapper('drivers')->save($driver, true);
        $this->assertEquals(
            $dbc->one("SELECT avatar FROM drivers WHERE driver = 2"),
            2
        );
    }
    public function testDereferenceNullManyRelation()
    {
        $dbc = $this->reset();

        $avatar = $dbc->tableMapped('avatars')->filter('avatar', 1)[0];
        $avatar->drivers = null;
        $dbc->getMapper('avatars')->save($avatar, true);
        $this->assertEquals(
            $dbc->one("SELECT avatar FROM drivers WHERE driver = 2"),
            null
        );
        $this->assertEquals(
            $dbc->one("SELECT avatar FROM drivers WHERE driver = 3"),
            null
        );
    }
    public function testDereferenceNewManyRelation()
    {
        $dbc = $this->reset();

        $avatar = $dbc->tableMapped('avatars')->filter('avatar', 1)[0];
        $avatar->drivers = Collection::from([]);
        $dbc->getMapper('avatars')->save($avatar, true);
        $this->assertEquals(
            $dbc->one("SELECT avatar FROM drivers WHERE driver = 2"),
            null
        );
        $this->assertEquals(
            $dbc->one("SELECT avatar FROM drivers WHERE driver = 3"),
            null
        );
    }
    public function testDereferenceNewManyPopulatedRelation()
    {
        $dbc = $this->reset();

        $driver = $dbc->tableMapped('drivers')->create();
        $driver->name = 'created';

        $avatar = $dbc->tableMapped('avatars')->filter('avatar', 1)[0];
        $avatar->drivers = Collection::from([$driver]);
        $dbc->getMapper('avatars')->save($avatar, true);
        $this->assertEquals(
            $dbc->one("SELECT avatar FROM drivers WHERE driver = 2"),
            null
        );
        $this->assertEquals(
            $dbc->one("SELECT avatar FROM drivers WHERE driver = 3"),
            null
        );
        $this->assertEquals(
            $dbc->one("SELECT avatar FROM drivers WHERE driver = 4"),
            1
        );
    }
    public function testDereferenceChangeManyRelation()
    {
        $dbc = $this->reset();

        $driver = $dbc->tableMapped('drivers')->filter('driver', 1)[0];

        $avatar = $dbc->tableMapped('avatars')->filter('avatar', 1)[0];
        $avatar->drivers = Collection::from([$driver]);
        $dbc->getMapper('avatars')->save($avatar, true);
        $this->assertEquals(
            $dbc->one("SELECT avatar FROM drivers WHERE driver = 2"),
            null
        );
        $this->assertEquals(
            $dbc->one("SELECT avatar FROM drivers WHERE driver = 3"),
            null
        );
        $this->assertEquals(
            $dbc->one("SELECT avatar FROM drivers WHERE driver = 1"),
            1
        );
    }
    public function testDereferenceChangeManyRelation2()
    {
        $dbc = $this->reset();

        $driver = $dbc->tableMapped('drivers')->filter('driver', 1)[0];

        $avatar = $dbc->tableMapped('avatars')->filter('avatar', 1)[0];
        $avatar->drivers->add($driver);
        $dbc->getMapper('avatars')->save($avatar, true);
        $this->assertEquals(
            $dbc->one("SELECT avatar FROM drivers WHERE driver = 2"),
            1
        );
        $this->assertEquals(
            $dbc->one("SELECT avatar FROM drivers WHERE driver = 3"),
            1
        );
        $this->assertEquals(
            $dbc->one("SELECT avatar FROM drivers WHERE driver = 1"),
            1
        );
    }
    public function testDereferenceChangeManyRelation3()
    {
        $dbc = $this->reset();

        $driver = $dbc->tableMapped('drivers')->filter('driver', 2)[0];

        $avatar = $dbc->tableMapped('avatars')->filter('avatar', 1)[0];
        $avatar->drivers->remove($driver);
        $dbc->getMapper('avatars')->save($avatar, true);
        $this->assertEquals(
            $dbc->one("SELECT avatar FROM drivers WHERE driver = 2"),
            null
        );
        $this->assertEquals(
            $dbc->one("SELECT avatar FROM drivers WHERE driver = 3"),
            1
        );
        $this->assertEquals(
            $dbc->one("SELECT avatar FROM drivers WHERE driver = 1"),
            null
        );
    }
    public function testDereferenceNullPivotRelation()
    {
        $dbc = $this->reset();

        $driver = $dbc->tableMapped('drivers')->filter('driver', 2)[0];
        $driver->cars = null;
        $dbc->getMapper('drivers')->save($driver, true);
        $this->assertEquals($dbc->one("SELECT COUNT(*) FROM drivers"), 3);
        $this->assertEquals($dbc->one("SELECT COUNT(*) FROM cars"), 3);
        $this->assertEquals(
            $dbc->all("SELECT * FROM driver_cars ORDER BY driver, car"),
            [['driver'=>1,'car'=>1], ['driver'=>3,'car'=>3]]
        );
    }
    public function testDereferenceNewPivotRelation()
    {
        $dbc = $this->reset();

        $driver = $dbc->tableMapped('drivers')->filter('driver', 2)[0];
        $driver->cars = Collection::from([]);
        $dbc->getMapper('drivers')->save($driver, true);
        $this->assertEquals($dbc->one("SELECT COUNT(*) FROM drivers"), 3);
        $this->assertEquals($dbc->one("SELECT COUNT(*) FROM cars"), 3);
        $this->assertEquals(
            $dbc->all("SELECT * FROM driver_cars ORDER BY driver, car"),
            [['driver'=>1,'car'=>1], ['driver'=>3,'car'=>3]]
        );
    }
    public function testDereferenceNewPivotPopulatedRelation()
    {
        $dbc = $this->reset();

        $car = $dbc->tableMapped('cars')->create();
        $car->name = 'created';

        $driver = $dbc->tableMapped('drivers')->filter('driver', 2)[0];
        $driver->cars = Collection::from([$car]);
        $dbc->getMapper('drivers')->save($driver, true);
        $this->assertEquals($dbc->one("SELECT COUNT(*) FROM drivers"), 3);
        $this->assertEquals($dbc->one("SELECT COUNT(*) FROM cars"), 4);
        $this->assertEquals(
            $dbc->all("SELECT * FROM driver_cars ORDER BY driver, car"),
            [['driver'=>1,'car'=>1], ['driver'=>2,'car'=>4], ['driver'=>3,'car'=>3]]
        );
    }
    public function testDereferenceChangePivotRelation()
    {
        $dbc = $this->reset();

        $car = $dbc->tableMapped('cars')->filter('car', 3)[0];
        $driver = $dbc->tableMapped('drivers')->filter('driver', 2)[0];
        $driver->cars = Collection::from([$car]);
        $dbc->getMapper('drivers')->save($driver, true);
        $this->assertEquals($dbc->one("SELECT COUNT(*) FROM drivers"), 3);
        $this->assertEquals($dbc->one("SELECT COUNT(*) FROM cars"), 3);
        $this->assertEquals(
            $dbc->all("SELECT * FROM driver_cars ORDER BY driver, car"),
            [['driver'=>1,'car'=>1], ['driver'=>2,'car'=>3], ['driver'=>3,'car'=>3]]
        );
    }
    public function testDereferenceChangePivotRelation2()
    {
        $dbc = $this->reset();

        $car = $dbc->tableMapped('cars')->filter('car', 3)[0];
        $driver = $dbc->tableMapped('drivers')->filter('driver', 2)[0];
        $driver->cars->add($car);
        $dbc->getMapper('drivers')->save($driver, true);
        $this->assertEquals($dbc->one("SELECT COUNT(*) FROM drivers"), 3);
        $this->assertEquals($dbc->one("SELECT COUNT(*) FROM cars"), 3);
        $this->assertEquals(
            $dbc->all("SELECT * FROM driver_cars ORDER BY driver, car"),
            [
                ['driver'=>1,'car'=>1],
                ['driver'=>2,'car'=>1],
                ['driver'=>2,'car'=>2],
                ['driver'=>2,'car'=>3],
                ['driver'=>3,'car'=>3]
            ]
        );
    }
    public function testDereferenceChangePivotRelation3()
    {
        $dbc = $this->reset();

        $car = $dbc->tableMapped('cars')->filter('car', 2)[0];
        $driver = $dbc->tableMapped('drivers')->filter('driver', 2)[0];
        $driver->cars->remove($car);
        $dbc->getMapper('drivers')->save($driver, true);
        $this->assertEquals($dbc->one("SELECT COUNT(*) FROM drivers"), 3);
        $this->assertEquals($dbc->one("SELECT COUNT(*) FROM cars"), 3);
        $this->assertEquals(
            $dbc->all("SELECT * FROM driver_cars ORDER BY driver, car"),
            [['driver'=>1,'car'=>1], ['driver'=>2,'car'=>1], ['driver'=>3,'car'=>3]]
        );
    }
    public function testDelete()
    {
        $dbc = $this->reset();
        $dbc->query("INSERT INTO cars (name) VALUES ('created')");
        $car = $dbc->tableMapped('cars')->filter('car', 4)[0];
        $this->assertEquals($car->name, 'created');
        $dbc->getMapper('cars')->delete($car, false);
        $this->assertEquals($dbc->one("SELECT 1 FROM cars WHERE car = 4"), null);
    }
    public function testDeleteRelations()
    {
        $dbc = $this->reset();
        $dbc->query("INSERT INTO cars (name) VALUES ('created')");
        $car = $dbc->tableMapped('cars')->filter('car', 2)[0];
        $dbc->getMapper('cars')->delete($car, true);
        $this->assertEquals($dbc->one("SELECT 1 FROM cars WHERE car = 2"), null);
        $this->assertEquals($dbc->one("SELECT COUNT(*) FROM cars"), 3);
        $this->assertEquals($dbc->one("SELECT COUNT(*) FROM car_pictures"), 2);
        $this->assertEquals($dbc->one("SELECT COUNT(*) FROM pictures"), 2);
        $this->assertEquals($dbc->one("SELECT COUNT(*) FROM driver_cars"), 3);
        $this->assertEquals($dbc->one("SELECT COUNT(*) FROM race_participants WHERE car IS NULL"), 3);
    }
    public function testDeleteRelations2()
    {
        $dbc = $this->reset();
        $carp = $dbc->tableMapped('car_pictures')->filter('car', 2)[0];
        $dbc->getMapper('car_pictures')->delete($carp, true);
        $this->assertEquals($dbc->one("SELECT COUNT(*) FROM cars"), 3);
        $this->assertEquals($dbc->one("SELECT COUNT(*) FROM car_pictures"), 2);
        $this->assertEquals($dbc->one("SELECT COUNT(*) FROM pictures"), 2);
    }
    public function testDeleteRelations3()
    {
        $dbc = $this->reset();
        $g = $dbc->tableMapped('pgrps')->filter('grp', 1)[0];
        $dbc->getMapper('pgrps')->delete($g, true);
        $this->assertEquals($dbc->one("SELECT COUNT(*) FROM pgrps"), 0);
        $this->assertEquals($dbc->one("SELECT COUNT(*) FROM polls"), 0);
        $this->assertEquals($dbc->one("SELECT COUNT(*) FROM questions"), 0);
        $this->assertEquals($dbc->one("SELECT COUNT(*) FROM answers"), 0);
    }
    public function testDBEntity()
    {
        $dbc = $this->reset();
        $car = $dbc->entity('cars');
        $car->name = 'created';
        foreach ($dbc->tableMapped('drivers') as $driver) {
            $driver->cars->add($car);
        }
        $driver = $dbc->tableMapped('drivers')->filter('driver', 1)[0];
        $dbc->delete($driver);
        $dbc->save();
        $this->assertEquals($dbc->one("SELECT COUNT(*) FROM cars"), 4);
        $this->assertEquals($dbc->one("SELECT COUNT(*) FROM drivers"), 2);
        $this->assertEquals($dbc->one("SELECT COUNT(*) FROM driver_cars"), 5);
    }
    public function testTypes()
    {
        $dbc = $this->reset();
        $dbc->setMapper(
            'drivers',
            new \vakata\database\schema\Mapper($dbc, 'drivers', Mapper2Driver::class),
            Mapper2Driver::class
        );
        $dbc->setMapper(
            'cars',
            new \vakata\database\schema\Mapper($dbc, 'cars', Mapper2Car::class),
            Mapper2Car::class
        );
        $driver = $dbc->entities(Mapper2Driver::class)->find(1);

        $this->assertEquals($driver::class, Mapper2Driver::class);
        $this->assertEquals($driver->cars[0]::class, Mapper2Car::class);
    }
    public function testHydratedRelationChangeFromColumn()
    {
        $dbc = $this->reset();
        $dbc->query("INSERT INTO avatars (url) VALUES ('avatar2')");
        $driver = $dbc->tableMapped('drivers')->with('avatars')->sort('driver')->collection()[1];
        $this->assertEquals('avatar', $driver->avatars->url);
        $this->assertEquals(1, $dbc->one("SELECT avatar FROM drivers WHERE driver = 2"));
        $driver->avatar = 2;
        $dbc->getMapper('drivers')->save($driver, true);
        $this->assertEquals(2, $dbc->one("SELECT avatar FROM drivers WHERE driver = 2"));
    }
    public function testRelationChangeFromColumn()
    {
        $dbc = $this->reset();
        $dbc->query("INSERT INTO avatars (url) VALUES ('avatar2')");
        $driver = $dbc->tableMapped('drivers')->sort('driver')->collection()[1];
        $driver->avatar = 2;
        $this->assertEquals('avatar2', $driver->avatars->url);
    }
}
