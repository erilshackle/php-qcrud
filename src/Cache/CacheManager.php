<?php

namespace Eril\QCrud\Cache;

use InvalidArgumentException;
use LogicException;
use PDO;
use Throwable;

final class CacheManager
{
    private static ?CacheStoreInterface $store = null;
    private static int $defaultTtl = 300;
    private static string $namespace = 'default';

    private function __construct() {}

    /** @param array{driver:string, directory?:string, ttl?:int, namespace?:string} $config */
    public static function configure(array $config): void
    {
        $driver = $config['driver'] ?? null;
        if (!in_array($driver, ['file', 'apcu'], true)) {
            throw new InvalidArgumentException("Cache driver must be 'file' or 'apcu'.");
        }

        $ttl = $config['ttl'] ?? 300;
        if (!is_int($ttl) || $ttl < 1) {
            throw new InvalidArgumentException('Cache TTL must be a positive integer number of seconds.');
        }

        $namespace = $config['namespace'] ?? ($_SERVER['DOCUMENT_ROOT'] ?? (getcwd() ?: 'default'));
        if (!is_string($namespace) || trim($namespace) === '') {
            throw new InvalidArgumentException('Cache namespace must be a non-empty string.');
        }

        $store = match ($driver) {
            'file' => self::createFileStore($config['directory'] ?? null),
            'apcu' => new ApcuCacheStore(),
        };

        self::$store = $store;
        self::$defaultTtl = $ttl;
        self::$namespace = trim($namespace);
    }

    public static function assertConfigured(): void
    {
        if (self::$store === null) {
            throw new LogicException('Configure cache with CRUD::configureCache() before calling useCache().');
        }
    }

    /** Cache successful query results; store failures fall back to the database. */
    public static function remember(PDO $pdo, string $sql, array $params, string $resultMode, callable $callback): mixed
    {
        self::assertConfigured();
        $fingerprintParams = self::normalizeParams($params);
        if ($fingerprintParams === null) {
            return $callback();
        }

        try {
            $generationHit = false;
            $generation = self::$store->get(self::generationKey(), $generationHit);
            if (!$generationHit || !is_string($generation)) {
                $generation = bin2hex(random_bytes(16));
                self::$store->set(self::generationKey(), $generation, null);
            }

            $key = 'query:' . hash('sha256', serialize([
                self::$namespace,
                (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME),
                $generation,
                $resultMode,
                $sql,
                $fingerprintParams,
            ]));
            $hit = false;
            $value = self::$store->get($key, $hit);
            if ($hit) {
                return $value;
            }
        } catch (Throwable) {
            return $callback();
        }

        $value = $callback();
        if (!self::isCacheableValue($value)) {
            return $value;
        }
        try {
            self::$store->set($key, $value, self::$defaultTtl);
        } catch (Throwable) {
            // Cache storage is optional; keep the successful database result.
        }

        return $value;
    }

    public static function clear(): void
    {
        if (self::$store === null) {
            return;
        }

        try {
            self::$store->set(self::generationKey(), bin2hex(random_bytes(16)), null);
        } catch (Throwable) {
            // Cache invalidation must not make a successful database write fail.
        }
    }

    private static function generationKey(): string
    {
        return 'generation:' . self::$namespace;
    }

    private static function createFileStore(mixed $directory): FileCacheStore
    {
        if (!is_string($directory) || trim($directory) === '') {
            throw new InvalidArgumentException("The 'directory' option is required for the file cache driver.");
        }
        return new FileCacheStore($directory);
    }

    private static function normalizeParams(array $params): ?array
    {
        $normalized = [];
        foreach ($params as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $normalized[$key] = $value;
                continue;
            }
            if (is_array($value)) {
                $nested = self::normalizeParams($value);
                if ($nested === null) {
                    return null;
                }
                $normalized[$key] = $nested;
                continue;
            }
            // LOB streams and arbitrary objects are not stable cache-key values.
            return null;
        }
        return $normalized;
    }

    private static function isCacheableValue(mixed $value): bool
    {
        if (is_scalar($value) || $value === null) {
            return true;
        }
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $item) {
            if (!self::isCacheableValue($item)) {
                return false;
            }
        }
        return true;
    }
}
