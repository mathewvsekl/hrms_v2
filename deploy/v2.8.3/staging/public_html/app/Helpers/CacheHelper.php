<?php

namespace App\Helpers;

/**
 * CacheHelper
 * 
 * Simple filesystem-based caching for HRMS V2.
 * Used to store non-real-time data like organization structure and dashboard summaries.
 */
class CacheHelper
{
    private static $cacheDir = __DIR__ . '/../../storage/cache';

    /**
     * Set a value in cache
     */
    public static function set($key, $data, $ttl = 300)
    {
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0777, true);
        }

        $filename = self::getFilename($key);
        $payload = [
            'expires' => time() + $ttl,
            'data' => $data
        ];

        file_put_contents($filename, json_encode($payload));
        return true;
    }

    /**
     * Get a value from cache
     */
    public static function get($key)
    {
        $filename = self::getFilename($key);
        if (!file_exists($filename)) {
            return null;
        }

        $payload = json_decode(file_get_contents($filename), true);
        if (!$payload || time() > $payload['expires']) {
            @unlink($filename);
            return null;
        }

        return $payload['data'];
    }

    /**
     * Delete a cache key
     */
    public static function forget($key)
    {
        $filename = self::getFilename($key);
        if (file_exists($filename)) {
            @unlink($filename);
        }
    }

    /**
     * Clear all cache
     */
    public static function clear()
    {
        if (is_dir(self::$cacheDir)) {
            $files = glob(self::$cacheDir . '/*.cache');
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }

    private static function getFilename($key)
    {
        return self::$cacheDir . '/' . md5($key) . '.cache';
    }
}
