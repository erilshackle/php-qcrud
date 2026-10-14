# Getting started

[Documentation index](README.md)

## Install

Requires PHP 8.0+, PDO, and the PDO driver for your database.

```bash
composer require eril/qcrud
```

## Register a connection

Run this once during application startup:

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

$users = CRUD::query('users')->get();
```

For SQLite, use `new PDO('sqlite:' . __DIR__ . '/app.sqlite')`.

A callable array can create the connection lazily:

```php
CRUD::registerConnection([DatabaseFactory::class, 'connect']);
```

`DatabaseFactory::connect()` must return a PDO instance. Register a connection before calling `table()` or `query()`.

## Global shortcuts

Composer loads these functions and the global `CRUD` class alias automatically:

```php
crud_register_connection($pdo);
$user = crud('users')->read(1);
$users = query('users')->where('status', 'active')->get();
```

The helpers are global functions; no namespace import is required. Prefer `use Eril\QCrud\CRUD;` for explicit class imports.
