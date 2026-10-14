# Caching

[Documentation index](README.md)

Import `Eril\QCrud\CRUD` and register a connection first. Caching is disabled by default.

## Configure and enable

```php
CRUD::configureCache([
    'driver' => 'file',
    'directory' => __DIR__ . '/storage/cache',
    'ttl' => 300,
    'namespace' => 'my-app:production:main-db',
]);

$users = CRUD::query('users')
    ->where('status', 'active')
    ->useCache()
    ->get();

$user = CRUD::table('users')->useCache()->read(1);
```

Configure a store before calling `useCache()`. Enable caching per CRUD or builder instance. Call `useCache(false)` to disable it on that instance.

| Option | Value |
| --- | --- |
| `driver` | Required: `file` or `apcu` |
| `directory` | Required for `file`; directory must be writable |
| `ttl` | Positive integer in seconds; default `300` |
| `namespace` | Non-empty isolation key; default document root or working directory |

The file driver creates the directory if needed. For APCu, install and enable the extension:

```php
CRUD::configureCache([
    'driver' => 'apcu',
    'ttl' => 300,
    'namespace' => 'my-app:production:main-db',
]);
```

Use a distinct namespace for each application, environment, and database that shares a cache store.

## Invalidation

Successful CRUD writes that affect rows invalidate the current cache namespace. Successful transaction commits also invalidate it. Cached reads are bypassed inside library-managed transactions.

After writes made outside QCrud, clear the cache explicitly:

```php
$pdo->exec("UPDATE users SET status = 'inactive'");
CRUD::clearCache();
```

Invalidation applies to the whole namespace. Cache storage failures fall back to database reads.
