<?php

namespace Eril\QCrud\Cache;

use RuntimeException;

final class ApcuCacheStore implements CacheStoreInterface
{
    public function __construct()
    {
        $cli = PHP_SAPI === 'cli';

        if (!function_exists('apcu_fetch') || !function_exists('apcu_store') || !function_exists('apcu_enabled') || !apcu_enabled($cli)) {
            throw new RuntimeException('The APCu cache driver was selected, but APCu is not available and enabled.');
        }
    }

    public function get(string $key, ?bool &$hit = null): mixed
    {
        $success = false;
        $value = apcu_fetch($this->keyFor($key), $success);
        $hit = $success;

        return $value;
    }

    /**
     * Summary of set
     * @param string $key
     * @param mixed $value
     * @param mixed $ttl
     * @throws RuntimeException
     * @return void
     */
    public function set(string $key, mixed $value, ?int $ttl): void
    {
        if (!apcu_store($this->keyFor($key), $value, $ttl ?? 0)) {
            throw new RuntimeException('Unable to write to APCu.');
        }
    }

    private function keyFor(string $key): string
    {
        return 'crud_' . hash('sha256', $key);
    }
}
