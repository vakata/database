# database

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Build Status][ico-travis]][link-travis]
[![Code Climate][ico-cc]][link-cc]
[![Tests Coverage][ico-cc-coverage]][link-cc]

A database abstraction with support for various drivers (mySQL, postgre, oracle, msSQL, sphinx, and even PDO).

## Install

Via Composer

``` bash
$ composer require vakata/database
```

## Usage

``` php
$db = new \vakata\database\DB('mysqli://user:pass@127.0.0.1/database_name?charset=utf8');

// get an array result:
$db->all('SELECT id, name FROM table');
// [ [ 'id' => 1, 'name' => 'name 1' ], [ 'id' => 2, 'name' => 'name 2' ] ]

// passing parameters
$db->all('SELECT id, name FROM table WHERE id = ? OR id = ?', [ 1, 2]);
// [ [ 'id' => 1, 'name' => 'name 1' ], [ 'id' => 2, 'name' => 'name 2' ] ]

// if selecting a single column there is no wrapping array:
$db->all('SELECT name FROM table');
// [ 'name 1', 'name 2' ]

// setting a key for the resulting array
$db->all('SELECT id, name FROM table', null, 'id');
// [ 1 => [ 'id' => 1, 'name' => 'name 1' ], 2 => [ 'id' => 2, 'name' => 'name 2' ] ]

// skipping the key (which leaves a single column so it is not wrapped anymore)
$db->all('SELECT id FROM table', null, 'id', true);
// [ 1 => 'name 1', 2 => 'name 2' ]

// selecting a single row:
$db->one('SELECT id, name FROM table WHERE id = ?', [1]);
// [ 'id' => 1, 'name' => 'name 1' ]

// selecting a single value from a single row (no wrapping array):
$db->one('SELECT name FROM table WHERE id = ?', [1]);
// "name 1"

// insert / update / delete queries (affected rows count and last insert ID)
$db->query("UPDATE table SET name = ? WHERE id = ?", ['asdf', 1])->affected();
// 1
$db->query("INSERT INTO table (name) VALUES(?)", ['asdf'])->insertId();
// 3
$db->query("DELETE FROM table WHERE id = ?", [3])->affected();
// 1

// queries using the "all" method can also use the "get" method
// "get" does not create an array in memory, instead it fetches data from the mysql client
// the resulting object is not an array but can be iterated and supports indexes
// basically it can be used as an array as it implements all neccessary interfaces
foreach($db->get('SELECT id, name FROM table') as $v) {
    echo $v['id'] . ' ';
}
// 1 2
$db->get('SELECT id, name FROM table', null, 'id', true)[2];
// "name 2"
```

## Testing

``` bash
$ composer test
```


## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email github@vakata.com instead of using the issue tracker.

## Credits

- [vakata][link-author]
- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/vakata/database.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/vakata/database/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/vakata/database.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/vakata/database.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/vakata/database.svg?style=flat-square
[ico-cc]: https://img.shields.io/codeclimate/github/vakata/database.svg?style=flat-square
[ico-cc-coverage]: https://img.shields.io/codeclimate/coverage/github/vakata/database.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/vakata/database
[link-travis]: https://travis-ci.org/vakata/database
[link-scrutinizer]: https://scrutinizer-ci.com/g/vakata/database/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/vakata/database
[link-downloads]: https://packagist.org/packages/vakata/database
[link-author]: https://github.com/vakata
[link-contributors]: ../../contributors
[link-cc]: https://codeclimate.com/github/vakata/database

