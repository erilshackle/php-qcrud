<?php

namespace Eril\QCrud\Tests;

use Eril\QCrud\CRUD;
use PDO;
use PHPUnit\Framework\TestCase;

final class CacheIntegrationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, status TEXT)");
        $this->pdo->exec("INSERT INTO users VALUES (1, 'John Doe', 'active'), (2, 'Jane', 'active')");
        CRUD::registerConnection($this->pdo);
        CRUD::configureCache([
            'driver' => 'file',
            'directory' => sys_get_temp_dir() . '/eril-crud-cache-tests',
            'ttl' => 60,
            'namespace' => 'tests-' . bin2hex(random_bytes(8)),
        ]);
    }

    public function testCacheIsOptInAndWritesInvalidateCachedReads(): void
    {
        $crud = CRUD::table('users')->useCache();
        $first = $crud->read(1);
        self::assertSame('John Doe', $first['name']);

        $this->pdo->exec("UPDATE users SET name = 'Changed externally' WHERE id = 1");
        self::assertSame('John Doe', $crud->read(1)['name']);

        CRUD::clearCache();
        self::assertSame('Changed externally', $crud->read(1)['name']);

        $crud->update(1, ['name' => 'Changed through CRUD']);
        self::assertSame('Changed through CRUD', $crud->read(1)['name']);
    }

    public function testQueryBuilderCachesRowsAndAggregates(): void
    {
        $query = CRUD::query('users')->where('status', 'active')->useCache();
        self::assertCount(2, $query->get());
        self::assertSame(2, $query->count());
        self::assertTrue(CRUD::query('users')->where('id', 1)->useCache()->exists());

        CRUD::table('users')->delete(1);
        self::assertFalse(CRUD::query('users')->where('id', 1)->useCache()->exists());
    }
}
