<?php
/**
 * Data Warehouse Eleitoral - Sistema de Cache de Performance
 */
class Cache {
    private static function getCacheDir(): string {
        $dir = __DIR__ . '/../cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    public static function get(string $key, int $ttl = 3600): ?array {
        $file = self::getCacheDir() . '/' . md5($key) . '.json';
        if (file_exists($file) && (time() - filemtime($file)) < $ttl) {
            $content = @file_get_contents($file);
            if ($content !== false && $content !== '') {
                $data = json_decode($content, true);
                if (is_array($data)) {
                    return $data;
                }
            }
        }
        return null;
    }

    public static function has(string $key, int $ttl = 3600): bool {
        $file = self::getCacheDir() . '/' . md5($key) . '.json';
        return file_exists($file) && (time() - filemtime($file)) < $ttl;
    }

    public static function set(string $key, array $data): bool {
        $dir = self::getCacheDir();
        $targetFile = $dir . '/' . md5($key) . '.json';
        $tmpFile = $dir . '/' . md5($key) . '_' . uniqid('', true) . '.tmp';

        $jsonFlags = JSON_UNESCAPED_UNICODE;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $jsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }

        $json = json_encode($data, $jsonFlags);
        if ($json === false) {
            return false;
        }

        if (@file_put_contents($tmpFile, $json, LOCK_EX) !== false) {
            return @rename($tmpFile, $targetFile);
        }

        return false;
    }

    public static function delete(string $key): bool {
        $file = self::getCacheDir() . '/' . md5($key) . '.json';
        if (file_exists($file)) {
            return @unlink($file);
        }
        return false;
    }

    public static function clear(): void {
        $dir = self::getCacheDir();
        if (is_dir($dir)) {
            $files = glob($dir . '/*.json');
            if (is_array($files)) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
            $tmpFiles = glob($dir . '/*.tmp');
            if (is_array($tmpFiles)) {
                foreach ($tmpFiles as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
        }
    }
}
