<?php
// src/Logger.php

namespace Eril\CRUD\Logging;

use Throwable;

/**
 * Simple Logger for CRUD errors only
 * 
 * @package Eril\CRUD
 * @internal Used internally for error logging
 */
class Logger
{
    private static string $logFile = __DIR__ . '/../../logs';
    private static int $maxSize = 5242880; // 5MB
    private static int $maxDays = 7; // 7 days retention
    private static bool $enabled = true;
    private static bool $initialized = false;

    /**
     * Initialize logger with custom config
     * 
     * @param array $config Configuration array
     * @return void
     */
    public static function init(array $config = []): void
    {
        if (self::$initialized && empty($config)) return;

        $defaults = [
            'log_dir' => dirname(__DIR__, 2) . '/logs',
            'log_file' => 'crud-errors.log',
            'max_size' => self::$maxSize,
            'max_days' => self::$maxDays,
            'enabled' => true,
        ];

        $config = array_merge($defaults, $config);

        self::$logFile = rtrim($config['log_dir'], '/') . '/' . $config['log_file'];
        self::$maxSize = $config['max_size'];
        self::$maxDays = $config['max_days'];
        self::$enabled = $config['enabled'];

        // Create directory if doesn't exist
        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        self::$initialized = true;
    }

    public static function error(string $message, ?Throwable $exception = null): void
    {
        if (!self::$enabled) {
            return;
        }

        self::init();

        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] ERROR: {$message}\n";

        // Always find who called Logger::error()
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $callerFile = 'unknown';
        $callerLine = 0;

        // Skip the first frame (this method) and look for the first non-Logger caller
        foreach ($trace as $index => $frame) {
            // Skip the current method frame
            if ($index === 0) continue;

            // Skip frames from Logger class
            if (isset($frame['class']) && $frame['class'] === self::class) {
                continue;
            }

            if (isset($frame['file'])) {
                $callerFile = $frame['file'];
                $callerLine = $frame['line'];
                break;
            }
        }

        $callerFileName = basename($callerFile);
        // $logEntry .= "Called from: {$callerFileName} at line {$callerLine}\n";
        $logEntry .= "Called from: {$callerFile}:{$callerLine}\n";

        if ($exception) {
            $logEntry .= "Exception: {$exception->getMessage()}\n";
            $logEntry .= "Exception in: " . basename($exception->getFile()) . ":{$exception->getLine()}\n";
        }

        // $logEntry .= "Location: {$callerFile}:{$callerLine}\n";
        $logEntry .= str_repeat('-', 50) . "\n";

        // Rotate if file is too large
        if (file_exists(self::$logFile) && filesize(self::$logFile) > self::$maxSize) {
            self::rotate();
        }

        // Cleanup old logs
        self::cleanup();

        file_put_contents(self::$logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Rotate log file
     * 
     * @return void
     */
    private static function rotate(): void
    {
        self::init();
        $backup = self::$logFile . '.' . date('Y-m-d_His');
        if (file_exists(self::$logFile)) {
            rename(self::$logFile, $backup);
        }
    }

    /**
     * Cleanup old log files
     * 
     * @return void
     */
    private static function cleanup(): void
    {
        self::init();
        $pattern = dirname(self::$logFile) . '/*.log.*';
        $files = glob($pattern);
        $cutoff = time() - (self::$maxDays * 24 * 60 * 60);

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
            }
        }
    }

    /**
     * Get last N log entries
     * 
     * @param int $lines Number of lines to retrieve
     * @return array Array of log entries
     */
    public static function tail(int $lines = 50): array
    {
        self::init();
        if (!file_exists(self::$logFile)) {
            return [];
        }

        $content = file(self::$logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return array_slice(array_reverse($content), 0, $lines);
    }

    /**
     * Clear all logs
     * 
     * @return bool True if cleared successfully
     */
    public static function clear(): bool
    {
        self::init();
        if (file_exists(self::$logFile)) {
            return unlink(self::$logFile);
        }
        return true;
    }

    /**
     * Get log statistics
     * 
     * @return array Statistics array
     */
    public static function stats(): array
    {
        return [
            'log_file' => self::$logFile,
            'enabled' => self::$enabled,
            'max_size' => self::formatBytes(self::$maxSize),
            'max_days' => self::$maxDays,
            'exists' => file_exists(self::$logFile),
            'size' => file_exists(self::$logFile) ? filesize(self::$logFile) : 0,
            'lines' => file_exists(self::$logFile) ? count(file(self::$logFile)) : 0,
            'modified' => file_exists(self::$logFile) ? date('Y-m-d H:i:s', filemtime(self::$logFile)) : null,
        ];
    }

    /**
     * Enable/disable logging
     * 
     * @param bool $enabled Enable state
     * @return void
     */
    public static function setEnabled(bool $enabled): void
    {
        self::$enabled = $enabled;
    }

    /**
     * Format bytes to human readable format
     * 
     * @param int $bytes Bytes to format
     * @return string Formatted string
     */
    private static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = $bytes ? floor(log($bytes) / log(1024)) : 0;
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
