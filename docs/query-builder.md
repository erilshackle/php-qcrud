# Query builder

[Documentation index](README.md)

Import `Eril\QCrud\CRUD` and register a connection first. Start a new builder with `CRUD::query('table')`.

## Select and filter

```php
$users = CRUD::query('users')
    ->select('id, name, email')
    ->where('status', 'active')
    ->where('age', '>=', 18)
    ->orderByDesc('id')
    ->limit(20)
    ->get();
```

Common filters:

```php
CRUD::query('users')->where('deleted_at', null)->get();
CRUD::query('users')->where('id', [1, 2, 3])->get();
CRUD::query('users')->whereNot('status', 'blocked')->get();
CRUD::query('users')->whereBetween('age', 18, 65)->get();
CRUD::query('users')->whereNotBetween('age', 18, 65)->get();
CRUD::query('users')
    ->where('status', 'active')
    ->orWhere('status', 'pending')
    ->get();
```

Use raw conditions for explicit grouping and bind their values:

```php
$users = CRUD::query('users')
    ->whereRaw('(status = ? OR status = ?) AND age >= ?', ['active', 'pending', 18])
    ->get();
```

## Results and pagination

```php
$user = CRUD::query('users')->where('id', 1)->first();
$exists = CRUD::query('users')->where('email', 'ana@example.com')->exists();
$rows = CRUD::query('users')->orderBy('id')->paginate(20, 2);
```

| Method | Result |
| --- | --- |
| `get()` | List of associative rows; `[]` when empty |
| `first()` | One associative row; `false` when missing |
| `exists()` | Boolean; also returns `false` on execution failure |
| `paginate($perPage, $page)` | List of rows for the page; no metadata or total count |

`get()` and `first()` throw `RuntimeException` on query execution failure. Pages start at `1`. `limit($count, $offset)` supports an explicit offset.

## Joins

```php
$users = CRUD::query('users', 'u')
    ->select('u.id, u.name, p.bio')
    ->joinLeft(['profiles', 'p'], 'p.user_id = u.id')
    ->where('u.status', 'active')
    ->get();
```

Also available: `joinInner()`, `joinRight()`, and `join($table, $on, $type)`.

## Aggregates and grouping

```php
$count = CRUD::query('users')->where('status', 'active')->count();
$total = CRUD::query('orders')->sum('total');
$average = CRUD::query('orders')->avg('total');
$minimum = CRUD::query('orders')->min('total');
$maximum = CRUD::query('orders')->max('total');

$summary = CRUD::query('orders')
    ->select('user_id, COUNT(*) AS order_count')
    ->groupBy('user_id')
    ->having('COUNT(*)', '>=', 2)
    ->get();
```

## Subqueries

```php
$users = CRUD::query('users')
    ->whereSubquery('id', 'IN', function ($query) {
        $query->from('orders')->select('user_id')->where('status', 'paid');
    })
    ->get();

$usersWithOrders = CRUD::query('users', 'u')
    ->whereExists(function ($query) {
        $query->select('1')->whereRaw('o.user_id = u.id');
    }, ['orders', 'o'])
    ->get();
```

Use `whereNotExists()` for the opposite condition. `from()` changes the table only inside a subquery.

## Unions

```php
$emails = CRUD::query('users')->select('email')
    ->union(CRUD::query('subscribers')->select('email'))
    ->get();
```

`union()` removes duplicates. `unionAll()` keeps them. Both queries must select compatible columns.

## Inspect SQL

```php
$query = CRUD::query('users')->where('status', 'active');
$sql = $query->mountSQL();
$params = $query->getParams();
```

Builder methods mutate the current instance. Create a new builder for independent queries. Use trusted identifiers and SQL expressions; bind user-provided values.
