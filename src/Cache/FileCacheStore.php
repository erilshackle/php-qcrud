<?php

namespace Eril\QCrud\Cache;

use RuntimeException;

final class FileCacheStore implements CacheStoreInterface
{
    private string $directory;
    private string $lockFile;
    private int $operations = 0;

    public function __construct(string $directory)
    {
        $directory = rtrim($directory, DIRECTORY_SEPARATOR);
        if ($directory === '') {
            $directory = DIRECTORY_SEPARATOR;
        }

        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create CRUD cache directory: {$directory}");
        }
        if (!is_writable($directory)) {
            throw new RuntimeException("CRUD cache directory is not writable: {$directory}");
        }

        $realDirectory = realpath($directory);
        $this->directory = $realDirectory !== false ? $realDirectory : $directory;
        $this->lockFile = $this->directory . DIRECTORY_SEPARATOR . '.crud.lock';
    }

    public function get(string $key, ?bool &$hit = null): mixed
    {
        $hit = false;
        $path = $this->pathFor($key);

        return $this->withLock(LOCK_SH, function () use ($path, &$hit): mixed {
            if (!is_file($path)) {
                return null;
            }

            $contents = @file_get_contents($path);
            if ($contents === false) {
                return null;
            }

            $entry = @unserialize($contents, ['allowed_classes' => false]);
            if (!is_array($entry) || !array_key_exists('expires_at', $entry) || !array_key_exists('value', $entry)) {
                return null;
            }

            if ($entry['expires_at'] !== null && (!is_int($entry['expires_at']) || $entry['expires_at'] <= time())) {
                return null;
            }

            $hit = true;
            return $entry['value'];
        });
    }

    public function set(string $key, mixed $value, ?int $ttl): void
    {
        $path = $this->pathFor($key);
        $expiresAt = $ttl === null ? null : time() + $ttl;
        $contents = serialize(['expires_at' => $expiresAt, 'value' => $value]);

        $this->withLock(LOCK_EX, function () use ($path, $contents): void {
            $handle = @fopen($path, 'c+b');
            if ($handle === false) {
                throw new RuntimeException("Unable to open CRUD cache entry: {$path}");
            }

            try {
                if (!ftruncate($handle, 0) || rewind($handle) === false) {
                    throw new RuntimeException("Unable to prepare CRUD cache entry: {$path}");
                }

                $length = strlen($contents);
                $written = 0;
                while ($written < $length) {
                    $count = fwrite($handle, substr($contents, $written));
                    if ($count === false || $count === 0) {
                        throw new RuntimeException("Unable to write CRUD cache entry: {$path}");
                    }
                    $written += $count;
                }

                fflush($handle);
                @chmod($path, 0600);
            } finally {
                fclose($handle);
            }

            $this->operations++;
            if ($this->operations % 100 === 0) {
                $this->collectExpiredEntries();
            }
        });
    }

    private function pathFor(string $key): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.cache';
    }

    private function withLock(int $lockType, callable $callback): mixed
    {
        $lock = @fopen($this->lockFile, 'c');
        if ($lock === false) {
            throw new RuntimeException('Unable to open CRUD cache lock file.');
        }
        @chmod($this->lockFile, 0600);

        try {
            if (!flock($lock, $lockType)) {
                throw new RuntimeException('Unable to lock CRUD cache directory.');
            }

            try {
                return $callback();
            } finally {
                flock($lock, LOCK_UN);
            }
        } finally {
            fclose($lock);
        }
    }

    private function collectExpiredEntries(): void
    {
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*.cache') ?: [] as $path) {
            $contents = @file_get_contents($path);
            if ($contents === false) {
                continue;
            }

            $entry = @unserialize($contents, ['allowed_classes' => false]);
            if (!is_array($entry) || !array_key_exists('expires_at', $entry) || !array_key_exists('value', $entry)) {
                @unlink($path);
                continue;
            }

            if (is_int($entry['expires_at']) && $entry['expires_at'] <= time()) {
                @unlink($path);
            }
        }
    }
}
