# Eril CRUD - Advanced CRUD Operations & Query Builder

[![Latest Stable Version](https://poser.pugx.org/eril/crud/v/stable)](https://packagist.org/packages/eril/crud)
[![Total Downloads](https://poser.pugx.org/eril/crud/downloads)](https://packagist.org/packages/eril/crud)
[![License](https://poser.pugx.org/eril/crud/license)](https://packagist.org/packages/eril/crud)
[![PHP Version](https://img.shields.io/badge/php-^8.0-blue.svg)](https://php.net)

A powerful, lightweight CRUD operations library with intelligent query builder, PDO support, and automatic error logging.

## Features

- 🚀 **Simple CRUD operations** (Create, Read, Update, Delete, Select)
- 🧠 **Intelligent Query Builder** with smart WHERE clauses and subquery support
- 🎯 **Automatic parameter binding** with type detection
- 🛡️ **PDO Prepared Statements** (SQL injection protection)
- 🔌 **Connection pooling** with static PDO instance
- 📊 **Transaction support** with rollback capability
- 📝 **Automatic error logging** with CLI tool and detailed stack traces
- 🔄 **JOIN operations** (INNER, LEFT, RIGHT)
- 📊 **Aggregate functions** (COUNT, SUM, AVG, MIN, MAX)
- 📄 **Pagination and UNION queries**
- 🔍 **EXISTS/NOT EXISTS conditions**
- 🎨 **Fluent interface** with method chaining
- 🧪 **Full test suite** with PHPUnit
- 📈 **HAVING clauses** with SQLite compatibility

## Requirements

- PHP 8.1 or higher
- PDO extension
- MySQL/PostgreSQL/SQLite (any PDO-compatible database)

## Installation

```bash
composer require eril/crud
```

## Quick Start

### 1. Setup Database Connection

```php
<?php
require_once 'vendor/autoload.php';

use Eril\CRUD\CRUD;

// Register your PDO connection 
$pdo = new PDO('mysql:host=localhost;dbname=myapp', 'username', 'password', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

// Register connection globally
CRUD::registerConnection($pdo);

// Optional: Configure logging (do this in your bootstrap)
CRUD::initLogging([
    'log_dir' => __DIR__ . '/logs',
    'log_file' => 'crud-errors.log',
    'max_size' => 10 * 1024 * 1024, // 10MB
    'max_days' => 30,
    'enabled' => true,
]);
```

### 2. Basic CRUD Operations

```php
<?php
// Create a new record
$userId = CRUD::table('users')->create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'created_at' => date('Y-m-d H:i:s')
]);

// Read a single record
$user = CRUD::table('users')->read($userId);
// Returns: ['id' => 1, 'name' => 'John Doe', ...]

// Read all records
$allUsers = CRUD::table('users')->read();
$usersWithColumns = CRUD::table('users')->read(null, 'id, name, email');

// Update a record
$updated = CRUD::table('users')->update($userId, [
    'name' => 'John Smith',
    'email' => 'john.smith@example.com'
]);

// Delete a record
$deleted = CRUD::table('users')->delete($userId);

// Custom select with WHERE clause
$activeUsers = CRUD::table('users')->select(
    'status = ? AND age > ?', 
    ['active', 18],
    'id, name, email'
);
```
> **hint**
> To help me keep tracking of tables I create constants with prefix **`tbl_`** in a dedicated filelke **`tables.php`**. 
```php
// tables.php
const tbl_users = 'users';
const tbl_orders = 'orders';

// ...
CRUD::table(tbl_users);
```


### 3. Advanced Query Builder

#### **Smart WHERE Conditions**
```php
<?php
// Basic query with conditions
$users = CRUD::query('users')
    ->where('status', '=', 'active')
    ->where('created_at', '>', '2024-01-01')
    ->orderBy('name', 'ASC')
    ->limit(10, offset: 0)
    ->get();

// Equals: WHERE active = 1
->where('active', 1)

// Operator: WHERE age > 18  
->where('age', '>', 18)

// NULL check: WHERE email IS NULL
->where('email', null)

// NOT condition: WHERE status <> 'inactive'
->where('!status', 'inactive')

// IN clause: WHERE id IN (1, 2, 3)
->where('id', [1, 2, 3])

// BETWEEN: WHERE price BETWEEN 10 AND 100  
->where('price', [10, '><', 100])

// Subquery without operator: WHERE user_id IN (SELECT id FROM admins)
->where('user_id', function($sq) {
    $sq->select('id')->from('admins');
})

// Subquery with operator: WHERE total > (SELECT AVG(total) FROM orders)
->where('total', '>', function($q) {
    $q->select('AVG(total)')->from('orders');
})

// EXISTS clause
->whereExists(function($q) {
    $q->select('1')
      ->from('invoices')
      ->where('invoices.user_id = users.id')
      ->where('invoices.status', 'pending');
})

// NOT EXISTS clause  
->whereNotExists(function($q) {
    $q->select('1')
      ->from('payments')
      ->where('payments.order_id = orders.id');
})

// Raw WHERE with parameters
->whereRaw('DATE(created_at) = ?', ['2024-01-01'])

// OR conditions
->where('status', 'active')
 ->orWhere('priority', '>', 5)
```

#### **Advanced SELECT with SQL Functions**
```php
<?php
// Basic select
->select('id, name, email')

// SQL functions
->select('COUNT(*) AS total, AVG(score) AS average')
->select('CONCAT(first_name, " ", last_name) AS full_name')
->select('DATE_FORMAT(created_at, "%Y-%m-%d") AS created_date')
->select('price * quantity AS subtotal, (price * quantity) * 0.1 AS tax')

// Subquery in SELECT
->select('(SELECT COUNT(*) FROM orders WHERE user_id = users.id) AS order_count')
```

#### **JOIN Operations**
```php
<?php
// Complex joins with table aliases
$results = CRUD::query('users', 'u')
    ->select('u.id, u.name, p.title as profile_title, c.name as country')
    ->joinInner('profiles p', 'u.profile_id = p.id')
    ->joinLeft('countries c', 'u.country_id = c.id')
    ->where('u.active', '=', 1)
    ->whereNotNull('u.email_verified_at')
    ->groupBy('u.id')
    ->having('COUNT(p.id)', '>', 0)
    ->get();

// INNER JOIN with array syntax
->joinInner(['profiles', 'p'], 'u.profile_id = p.id')

// LEFT JOIN
->joinLeft(['countries', 'c'], 'u.country_id = c.id')

// RIGHT JOIN
->joinRight(['departments', 'd'], 'e.department_id = d.id')
```

#### **Aggregate Functions**
```php
<?php
// Count records
$total = CRUD::query('users')->count();

// Sum of values
$revenue = CRUD::query('orders')->sum('amount');

// Average value
$avgScore = CRUD::query('students')->avg('score');

// Minimum and maximum
$minPrice = CRUD::query('products')->min('price');
$maxPrice = CRUD::query('products')->max('price');

// With conditions
$activeCount = CRUD::query('users')
    ->where('active', 1)
    ->count();
```

#### **HAVING Clauses (SQLite Compatible)**
```php
<?php
// Simple HAVING
->select('category, COUNT(*) as total')
 ->groupBy('category')
 ->having('total', '>', 5)

// HAVING with operator
->select('department_id, AVG(salary) as avg_salary')
 ->groupBy('department_id')
 ->having('avg_salary', '>', 50000)

// OR HAVING
->select('status, COUNT(*) as count')
 ->groupBy('status')
 ->having('count', 10)
 ->orHaving('count', '<', 2)
```

#### **Pagination and UNION**
```php
<?php
// Pagination (page 2, 10 per page)
$page2 = CRUD::query('products')
    ->where('active', 1)
    ->paginate(10, 2);

// UNION queries
$allUsers = CRUD::query('users')
    ->select('id, name, "active" as status')
    ->where('active', 1)
    ->union(function($q) {
        $q->select('id, name, "inactive" as status')
          ->from('users')
          ->where('active', 0);
    })
    ->get();

// UNION ALL
$withDuplicates = CRUD::query('table1')
    ->select('*')
    ->union(CRUD::query('table2')->select('*'), all: true)
    ->get();
```

#### **Ordering and Random Results**
```php
<?php
// Order by field
->orderBy('created_at', 'DESC')
->orderBy('name', 'ASC')

// Random ordering (database-agnostic)
->orderByRandom()

// Multiple order conditions
->orderBy('priority', 'DESC')
->orderBy('created_at', 'ASC')
```

### 4. Transactions

```php
<?php
// Prepare transaction
CRUD::prepareTransactions();

// Multiple operations
CRUD::table('users')->create([
    'name' => 'Jane Doe',
    'email' => 'jane@example.com'
]);

CRUD::table('profiles')->create([
    'user_id' => 2,
    'bio' => 'Software Developer'
]);

CRUD::table('user_roles')->create([
    'user_id' => 2,
    'role' => 'member'
]);

// Execute all operations as a single transaction
# $exception = null;
CRUD::executeTransactions(&$exception);

if ($exception) {
    echo "Transaction failed: " . $exception->getMessage();
    // All operations are automatically rolled back
}
```

## Helper Functions

The library includes convenient namespaced helper functions:

```php
<?php
use function Eril\CRUD\crud;
use function Eril\CRUD\query;

// Shorter syntax
$id = crud('users')->create(['name' => 'John']);
$users = crud('users')->read();

// Query builder helper
$results = crud_query('users')
    ->where('active', 1)
    ->orderBy('created_at', 'DESC')
    ->get();
```

## Error Logging & CLI Tool

Errors are automatically logged with detailed stack traces showing both the caller location and exception source.

### Log Format Example:
```
[2025-12-03 00:53:54] ERROR: Transaction failed
Called from: UserController.php:123
Exception: SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: users.email
Exception in: CRUD.php:472
--------------------------------------------------
```

### View Logs via CLI:

```bash
# Show last 50 errors
./vendor/bin/crud-logs tail

# Show last 20 errors
./vendor/bin/crud-logs tail 20

# Clear all logs
./vendor/bin/crud-logs clear

# Show log statistics
./vendor/bin/crud-logs stats
```

### Via Composer Scripts:

```bash
composer crud-logs:tail 20
composer crud-logs:clear
composer crud-logs:stats
```

### Configure Logging:

```php
// not required
CRUD::initLogging([
    'log_dir' => __DIR__ . '/logs',      // Directory for logs
    'log_file' => 'crud-errors.log',     // Log filename
    'max_size' => 5242880,               // 5MB max file size
    'max_days' => 7,                     // 7 days retention
    'enabled' => true,                   // Enable/disable logging
]);

// you can disable in bootstrap doing (it's a shortcut):
CRUDD::registerConnection($pdo, enableLogs: false);

```

## API Reference

### CRUD Class Methods

| Method | Description | Returns |
|--------|-------------|---------|
| `CRUD::table(string $table, string $primaryKey = 'id')` | Factory method for CRUD instance | `CRUD` |
| `CRUD::query(string $table, ?string $alias = null)` | Create QueryCrud instance | `QueryCrud` |
| `CRUD::registerConnection(PDO\|array $connection)` | Register PDO connection | `void` |
| `CRUD::initLogging(array $config)` | Configure error logging | `void` |
| `CRUD::prepareTransactions()` | Prepare for transaction | `void` |
| `CRUD::executeTransactions(?Exception &$exception)` | Execute transaction | `bool` |

### CRUD Instance Methods

| Method | Description |
|--------|-------------|
| `create(array $data)` | Insert new record |
| `read($id = null, ?string $select = null)` | Read single/all records |
| `update($id, array $data)` | Update existing record |
| `delete($id)` | Delete record |
| `select(?string $where, array $params, ?string $fields)` | Custom SELECT with WHERE |

### QueryCrud Builder Methods

| Category | Methods | Description |
|----------|---------|-------------|
| **SELECT** | `select()`, `addSelect()` | Set fields, supports SQL functions and subqueries |
| **WHERE** | `where()`, `orWhere()`, `whereExists()`, `whereNotExists()`, `whereRaw()`, `whereSubquery()`, `whereSelect()` | Intelligent conditions with subquery support |
| **JOIN** | `join()`, `joinInner()`, `joinLeft()`, `joinRight()` | Table joins with aliases |
| **ORDER** | `orderBy()`, `orderByDesc()`, `orderByRandom()` | Result ordering |
| **LIMIT** | `limit()`, `paginate()` | Result limiting and pagination |
| **GROUP/HAVING** | `groupBy()`, `having()`, `orHaving()`, `havingRaw()`, `havingInt()` | Grouping with SQLite-compatible HAVING |
| **UNION** | `union()`, `unionAll()` | Combine query results |
| **AGGREGATE** | `count()`, `sum()`, `avg()`, `min()`, `max()` | Aggregate functions with conditions |
| **EXECUTION** | `get()`, `first()`, `exists()`, `reset()` | Execute query |

## Advanced Examples

### Complex Query with All Features
```php
$report = CRUD::query('orders', 'o')
    ->select('
        o.id,
        o.order_number,
        o.total_amount,
        DATE(o.created_at) as order_date,
        c.name as customer_name,
        c.email as customer_email,
        (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count,
        (SELECT SUM(quantity * price) FROM order_items oi WHERE oi.order_id = o.id) as subtotal
    ')
    ->joinInner(['customers', 'c'], 'o.customer_id = c.id')
    ->joinLeft(['payments', 'p'], 'o.id = p.order_id AND p.status = "completed"')
    ->where('o.status', '!=', 'cancelled')
    ->where('o.created_at', '>=', '2024-01-01')
    ->whereExists(function($q) {
        $q->select('1')
          ->from('order_items oi')
          ->where('oi.order_id = o.id')
          ->where('oi.quantity', '>', 0);
    })
    ->groupBy('o.id')
    ->having('subtotal', '>', 1000)
    ->orderBy('o.created_at', 'DESC')
    ->paginate(20, 1);
```

### Subquery in WHERE Clause
```php
// Users who have placed orders
$usersWithOrders = CRUD::query('users')
    ->where('id', function($q) {
        $q->select('DISTINCT user_id')
          ->from('orders')
          ->where('status', 'completed');
    })
    ->get();

// Products with above-average price
$premiumProducts = CRUD::query('products')
    ->where('price', '>', function($q) {
        $q->select('AVG(price)')
          ->from('products')
          ->where('active', 1);
    })
    ->get();
```

### Dynamic Query Building
```php
// Build query based on conditions
$query = CRUD::query('products')
    ->select('id, name, price, stock');

if ($categoryId) {
    $query->where('category_id', $categoryId);
}

if ($minPrice) {
    $query->where('price', '>=', $minPrice);
}

if ($maxPrice) {
    $query->where('price', '<=', $maxPrice);
}

if ($inStockOnly) {
    $query->where('stock', '>', 0);
}

$results = $query->orderBy('name')->get();
```

### Batch Statistical Analysis
```php
// Get multiple statistics in one go
$stats = [];

// Count by status
$stats['by_status'] = CRUD::query('orders')
    ->select('status, COUNT(*) as count')
    ->groupBy('status')
    ->get();

// Monthly revenue
$stats['monthly_revenue'] = CRUD::query('orders')
    ->select('
        YEAR(created_at) as year,
        MONTH(created_at) as month,
        SUM(total_amount) as revenue,
        COUNT(*) as orders
    ')
    ->where('status', 'completed')
    ->groupBy('YEAR(created_at), MONTH(created_at)')
    ->orderBy('year', 'DESC')
    ->orderBy('month', 'DESC')
    ->get();

// Top customers
$stats['top_customers'] = CRUD::query('orders', 'o')
    ->select('
        c.id,
        c.name,
        c.email,
        COUNT(o.id) as order_count,
        SUM(o.total_amount) as total_spent
    ')
    ->joinInner(['customers', 'c'], 'o.customer_id = c.id')
    ->where('o.status', 'completed')
    ->groupBy('c.id')
    ->orderBy('total_spent', 'DESC')
    ->limit(10)
    ->get();
```

### Batch Operations with Transactions
```php
// Multiple inserts in transaction
CRUD::prepareTransactions();

$users = [
    ['name' => 'Alice', 'email' => 'alice@example.com'],
    ['name' => 'Bob', 'email' => 'bob@example.com'],
    ['name' => 'Charlie', 'email' => 'charlie@example.com'],
];

foreach ($users as $user) {
    CRUD::table('users')->create($user);
}

$success = CRUD::executeTransactions($exception);

if (!$success) {
    echo "Failed to insert users: " . $exception->getMessage();
}
```

## Performance Tips

1. **Create constants** for tables starting with prefix `tbl_`
2. **Use transactions** for bulk operations
3. **Limit selected fields** instead of using `SELECT *`
4. **Use indexed columns** in WHERE clauses
5. **Batch similar operations** together
6. **Enable query caching** at database level when appropriate

## Security Best Practices

1. **Always use prepared statements** (automatic with this library)
2. **Validate input data** before passing to CRUD operations
3. **Use least privilege principle** for database users
4. **Sanitize field names** if they come from user input
5. **Regularly review error logs** for suspicious activity

## Testing

Run the test suite:

```bash
# All tests
composer test

# Unit tests only
composer test-unit

# Integration tests
composer test-integration

```

## Error Handling

All errors are caught and logged automatically with detailed context:

```php
try {
    $id = CRUD::table('users')->create(['email' => 'existing@example.com']);
} catch (\Exception $e) {
    // Error is automatically logged with:
    // - Caller location (where you called the method)
    // - Exception location (where it occurred in the library)
    // - Full exception message
    
    // Check logs: ./vendor/bin/crud-logs tail
}
```

### Common Error Patterns:
- **UNIQUE constraint violation**: Duplicate entry for unique fields
- **FOREIGN KEY constraint**: Reference to non-existent record
- **NOT NULL constraint**: Missing required field
- **Data type mismatch**: Wrong type for column
- **SQL syntax errors**: Malformed queries

## Migration from Other Query Builders

If you're coming from Laravel Eloquent or similar:

1. **Similar fluent interface** - Chain methods like `where()`, `orderBy()`, etc.
2. **Prepared statements** - Automatic like Eloquent
3. **Subquery support** - Similar syntax with closures
4. **Aggregate functions** - Same method names
5. **Transactions** - Similar prepare/execute pattern

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Development Setup:
```bash
git clone https://github.com/eril/crud.git
cd crud
composer install
composer test  # Run tests
```

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

- **Issues**: [GitHub Issues](https://github.com/erilshackle/crud-php/issues)
- **Email**: erilandocarvalho@gmail.com
- **Documentation**: [GitHub Wiki](https://github.com/erilshackle/crud-php/wiki)

## Acknowledgments

- Built with modern PHP 8.1+ features
- Inspired by Laravel's Eloquent and Doctrine's QueryBuilder
- Focus on simplicity, performance, and developer experience
- Special thanks to all contributors and users

---

**Star this repository** if you find it useful! ⭐

**Found a bug or need a feature?** Open an issue or submit a pull request!

**Want to contribute?** Check out the [Contributing Guide](CONTRIBUTING.md).