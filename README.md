# QCrud

QCrud is a PDO-based PHP library for common database operations and fluent SQL queries. It includes optional query caching and transaction support.

[![Latest Stable Version](https://poser.pugx.org/eril/qcrud/v/stable)](https://packagist.org/packages/eril/qcrud)
[![Total Downloads](https://poser.pugx.org/eril/qcrud/downloads)](https://packagist.org/packages/eril/qcrud)
[![License](https://poser.pugx.org/eril/qcrud/license)](https://packagist.org/packages/eril/qcrud)
[![PHP Version](https://img.shields.io/badge/php-%5E8.0-blue.svg)](https://php.net)

## Documentation

See the [usage documentation](docs/README.md) for concise guides:

- [Getting started](docs/getting-started.md)
- [CRUD operations](docs/crud.md)
- [Query builder](docs/query-builder.md)
- [Transactions](docs/transactions.md)
- [Caching](docs/caching.md)

## Features

- CRUD operations through a small, fluent API
- Query builder with conditions, joins, subqueries, aggregates, pagination, and unions
- Prepared statements for bound values
- Optional file or APCu query cache
- Automatic cache invalidation after writes
- Callback-based and queued transactions

## Requirements

- PHP 8.0 or later
- PDO and a PDO driver for your database
- APCu only if you select the APCu cache driver

## Installation

```bash
composer require eril/qcrud
```

## Quick start

Register a PDO connection once during application setup:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Eril\QCrud\CRUD;

$pdo = new PDO(
    'mysql:host=localhost;dbname=myapp;charset=utf8mb4',
    'username',
    'password',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

CRUD::registerConnection($pdo);
```

### CRUD operations

```php
$id = CRUD::table('users')->create([
    'name' => 'Ana Silva',
    'email' => 'ana@example.com',
]);

$user = CRUD::table('users')->read($id);
$users = CRUD::table('users')->read(null, 'id, name, email');

$changed = CRUD::table('users')->update($id, ['name' => 'Ana Santos']);
$deleted = CRUD::table('users')->delete($id);

$activeUsers = CRUD::table('users')->select(
    'status = ? AND age >= ?',
    ['active', 18],
    'id, name, email'
);
```

### Query builder

```php
$users = CRUD::query('users', 'u')
    ->select('u.id, u.name, p.bio')
    ->joinLeft(['profiles', 'p'], 'p.user_id = u.id')
    ->where('u.status', 'active')
    ->where('u.age', '>=', 18)
    ->orderByDesc('u.name')
    ->limit(20)
    ->get();
```

`where()` supports equality, comparison operators, `NULL`, arrays for `IN`, and subqueries. The builder also provides `whereBetween()`, `whereExists()`, `whereRaw()`, `groupBy()`, `having()`, `union()`, `paginate()`, `first()`, `exists()`, and aggregate methods such as `count()` and `sum()`.

Use bound parameters for values. SQL expressions and identifiers passed to methods such as `select()`, `orderBy()`, `groupBy()`, joins, and raw conditions should come from trusted code; do not pass untrusted user input as SQL.

## Transactions

Use the callback API for a standard transaction. Exceptions roll back the transaction and are rethrown.

```php
CRUD::transaction(function (PDO $pdo): void {
    CRUD::table('users')->create(['name' => 'Ana']);
    CRUD::table('profiles')->create(['user_id' => 1, 'bio' => 'Developer']);
});
```

The queued API is also available when you need to collect operations before executing them:

```php
CRUD::prepareTransactions();
CRUD::table('users')->create(['name' => 'Ana']);
CRUD::table('profiles')->create(['user_id' => 1, 'bio' => 'Developer']);

$exception = null;
$success = CRUD::executeTransactions($exception);
```

## Query cache

Caching is disabled by default. Configure a store once, then enable caching only on reads that should be cached:

```php
CRUD::configureCache([
    'driver' => 'file',
    'directory' => __DIR__ . '/storage/cache',
    'ttl' => 300,
    'namespace' => 'my-app:production',
]);

$users = CRUD::query('users')
    ->where('status', 'active')
    ->useCache()
    ->get();
```

The file cache creates the configured directory if needed; the PHP process must be able to write to it. To use APCu, set `'driver' => 'apcu'` and install/enable the APCu extension. Use a distinct namespace for each application or environment that shares a cache store.

Successful writes that affect rows and successful transaction commits invalidate the current cache namespace. For writes performed outside the library, use `clearCache()`:

```php
CRUD::clearCache();
```

Cache storage failures fall back to executing the query against the database.

### Cache options

| Option | Required | Description |
| --- | --- | --- |
| `driver` | Yes | `file` or `apcu` |
| `directory` | File only | Writable directory for cache files |
| `ttl` | No | Entry lifetime in seconds; defaults to `300` |
| `namespace` | No | Cache isolation key; defaults to the document root or current working directory |

## Helper functions

The Composer autoloader also provides `crud()` and `query()` shortcuts:

```php
$user = crud('users')->read(1);
$activeUsers = query('users')->where('status', 'active')->get();
```

## API overview

| Entry point | Main methods |
| --- | --- |
| `CRUD` | `registerConnection()`, `table()`, `query()`, `configureCache()`, `clearCache()`, `transaction()` |
| CRUD instance | `create()`, `read()`, `update()`, `delete()`, `select()`, `useCache()` |
| Query builder | `select()`, `where()`, `join()`, `groupBy()`, `having()`, `orderBy()`, `limit()`, `paginate()`, `union()`, `get()`, `first()`, `exists()`, aggregates |

## Tests

Install the development dependencies and run PHPUnit:

```bash
composer install
composer test
```

The test suite uses an in-memory SQLite database. The PHP installation used for tests must include the PDO SQLite driver.

## License

QCrud is released under the [MIT License](LICENSE).

## Support

- [Report an issue](https://github.com/erilshackle/php-crud-class/issues)
- [Packagist package](https://packagist.org/packages/eril/qcrud)
