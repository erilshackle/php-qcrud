# Transactions

[Documentation index](README.md)

Import `Eril\QCrud\CRUD` and register a connection first.

## Callback transaction

```php
$userId = CRUD::transaction(function (PDO $pdo) {
    $id = CRUD::table('users')->create([
        'name' => 'Ana',
        'email' => 'ana@example.com',
    ]);
    if ($id === null || $id === 0) {
        throw new RuntimeException('Could not create user.');
    }

    $profileId = CRUD::table('profiles')->create([
        'user_id' => $id,
        'bio' => 'Developer',
    ]);
    if ($profileId === null) {
        throw new RuntimeException('Could not create profile.');
    }

    return $id;
});
```

The callback receives the registered PDO connection. QCrud commits on success and returns the callback result. A thrown exception triggers rollback and is rethrown.

CRUD write methods return `null` on error. Check their results and throw to request rollback. Use a database and tables that support transactions. Avoid nesting transactions.

## Queued writes

```php
CRUD::prepareTransactions();
CRUD::table('users')->update(1, ['status' => 'active']);
CRUD::table('users')->update(2, ['status' => 'active']);

$error = null;
if (!CRUD::executeTransactions($error)) {
    throw $error ?? new RuntimeException('Transaction failed.');
}
```

Writes are queued until `executeTransactions()`. It returns `true` after commit or `false` after failure and exposes the exception through `$error`. Reads are not queued. Queued write calls return `0`; inserted IDs are unavailable until execution. Use the callback API when later writes depend on a new ID.

Global shortcuts: `crud_prepare_transactions()` and `crud_execute_transactions($error)`.
