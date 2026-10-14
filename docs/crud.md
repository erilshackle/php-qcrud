# CRUD operations

[Documentation index](README.md)

Import `Eril\QCrud\CRUD` and register a connection first.

## Create

```php
$id = CRUD::table('users')->create([
    'name' => 'Ana Silva',
    'email' => 'ana@example.com',
]);
```

Returns the generated ID as `int|string`, `0` if no generated ID is available or no rows were affected, or `null` on error.

## Read

```php
$user = CRUD::table('users')->read(1);
$users = CRUD::table('users')->read();
$users = CRUD::table('users')->read(null, 'id, name, email');

$activeUsers = CRUD::table('users')->select(
    'status = ? AND age >= ?',
    ['active', 18],
    'id, name'
);
```

`read($id)` returns one associative row or `[]` when missing. `read()` and `select()` return a list of rows or `[]` when empty. These methods return `null` on error.

## Update and delete

```php
$updated = CRUD::table('users')->update(1, ['name' => 'Ana Santos']);
$deleted = CRUD::table('users')->delete(1);
```

Both return the affected row count, including `0`, or `null` on error. Check errors with `=== null`.

## Custom primary key

```php
$product = CRUD::table('products', 'product_id')->read(42);
CRUD::table('products', 'product_id')->update(42, ['price' => 19.90]);
```

The default primary key is `id`. `read()`, `update()`, and `delete()` also accept an array containing that key, such as `['id' => 1]`.

Values are bound as parameters. Keep table names, column names, and SQL expressions under application control.
