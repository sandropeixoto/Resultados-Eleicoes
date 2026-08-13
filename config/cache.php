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
            $content = file_get_contents($file);
            $data = json_decode($content, true);
            if (is_array($data)) return $data;
        }
        return null;
    }

    public static function set(string $key, array $data): void {
        $file = self::getCacheDir() . '/' . md5($key) . '.json';
        file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    public static function clear(): void {
        $dir = self::getCacheDir();
        if (is_dir($dir)) {
            foreach (glob($dir . '/*.json') as $file) {
                @unlink($file);
            }
        }
    }
}
