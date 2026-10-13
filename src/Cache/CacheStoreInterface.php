<?php

namespace Eril\QCrud\Cache;

interface CacheStoreInterface
{
    /**
     * Read a value and report separately whether the key was present.
     *
     * @param bool|null $hit Set to true when the key exists, including when its value is false or null.
     */
    public function get(string $key, ?bool &$hit = null): mixed;

    /**
     * Store a value. A null TTL means the value does not expire.
     */
    public function set(string $key, mixed $value, ?int $ttl): void;
}
