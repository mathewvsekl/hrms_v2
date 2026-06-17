<?php

namespace App\Helpers;

/**
 * CacheHelper
 * 
 * Multi-driver caching system (Redis/Filesystem) for HRMS V2.
 */
class CacheHelper
{
    private static $cacheDir = null;

    private static function getCacheDir()
    {
        if (self::$cacheDir === null) {
            self::$cacheDir = (defined('STORAGE_PATH') ? STORAGE_PATH : (defined('ROOT_PATH') ? ROOT_PATH : BASE_PATH) . '/storage') . '/cache';
        }
        return self::$cacheDir;
    }
    private static $redis = null;

    private static function getRedis()
    {
        if (self::$redis !== null) return self::$redis;

        $host = getenv('REDIS_HOST');
        if (!$host) return false;

        try {
            if (class_exists('\Redis')) {
                self::$redis = new \Redis();
                self::$redis->connect($host, (int)getenv('REDIS_PORT') ?: 6379);
                if ($pass = getenv('REDIS_PASS')) {
                    self::$redis->auth($pass);
                }
                return self::$redis;
            }
        } catch (\Exception $e) {
            error_log("Redis Connection Failed: " . $e->getMessage());
        }
        return false;
    }

    /**
     * Set a value in cache
     */
    public static function set($key, $data, $ttl = 300)
    {
        $redis = self::getRedis();
        if ($redis) {
            return $redis->setex($key, $ttl, json_encode($data));
        }

        if (!is_dir(self::getCacheDir())) {
            mkdir(self::getCacheDir(), 0777, true);
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
        $redis = self::getRedis();
        if ($redis) {
            $val = $redis->get($key);
            return $val ? json_decode($val, true) : null;
        }

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
        $redis = self::getRedis();
        if ($redis) {
            return $redis->del($key);
        }

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
        $redis = self::getRedis();
        if ($redis) {
            return $redis->flushDB();
        }

        if (is_dir(self::getCacheDir())) {
            $files = glob(self::getCacheDir() . '/*.cache');
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }

    private static function getFilename($key)
    {
        return self::getCacheDir() . '/' . md5($key) . '.cache';
    }
}
