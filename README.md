# database

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Build Status][ico-travis]][link-travis]
[![Coverage Status][ico-scrutinizer]][link-scrutinizer]
[![Quality Score][ico-code-quality]][link-code-quality]
[![Total Downloads][ico-downloads]][link-downloads]

A database abstraction with support for various drivers (mySQL, postgre, oracle, msSQL, sphinx, and even PDO).

## Install

Via Composer

``` bash
$ composer require vakata/database
```

## Usage

``` php
$db = new vakata\database\DB();
echo $db->one("SELECT * FROM table WHERE id = 1");
```

## Testing

``` bash
$ composer test
```


## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email database@vakata.com instead of using the issue tracker.

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

[link-packagist]: https://packagist.org/packages/vakata/database
[link-travis]: https://travis-ci.org/vakata/database
[link-scrutinizer]: https://scrutinizer-ci.com/g/vakata/database/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/vakata/database
[link-downloads]: https://packagist.org/packages/vakata/database
[link-author]: https://github.com/vakata
[link-contributors]: ../../contributors
