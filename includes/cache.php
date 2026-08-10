<?php
/**
 * Simple File-based Caching System
 * Improves performance by caching frequently accessed data
 */

class SimpleCache {
    private $cache_dir;
    private $default_ttl = 300; // 5 minutes default
    
    public function __construct($cache_dir = '../cache/') {
        $this->cache_dir = $cache_dir;
        
        // Create cache directory if it doesn't exist
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
        
        // Clean old cache files on initialization
        $this->cleanExpiredCache();
    }
    
    /**
     * Get cached data
     */
    public function get($key) {
        $file = $this->cache_dir . md5($key) . '.cache';
        
        if (!file_exists($file)) {
            return false;
        }
        
        $data = unserialize(file_get_contents($file));
        
        // Check if cache has expired
        if ($data['expires'] < time()) {
            unlink($file);
            return false;
        }
        
        return $data['data'];
    }
    
    /**
     * Set cached data
     */
    public function set($key, $data, $ttl = null) {
        if ($ttl === null) {
            $ttl = $this->default_ttl;
        }
        
        $file = $this->cache_dir . md5($key) . '.cache';
        $cache_data = [
            'data' => $data,
            'expires' => time() + $ttl,
            'created' => time()
        ];
        
        return file_put_contents($file, serialize($cache_data)) !== false;
    }
    
    /**
     * Delete cached data
     */
    public function delete($key) {
        $file = $this->cache_dir . md5($key) . '.cache';
        if (file_exists($file)) {
            return unlink($file);
        }
        return true;
    }
    
    /**
     * Clear all cache
     */
    public function clear() {
        $files = glob($this->cache_dir . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
        return true;
    }
    
    /**
     * Get cache info
     */
    public function info() {
        $files = glob($this->cache_dir . '*.cache');
        $total_size = 0;
        $count = 0;
        
        foreach ($files as $file) {
            $total_size += filesize($file);
            $count++;
        }
        
        return [
            'count' => $count,
            'size' => $total_size,
            'size_formatted' => $this->formatBytes($total_size)
        ];
    }
    
    private function formatBytes($size, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        return round($size, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Clean expired cache files
     */
    public function cleanExpiredCache() {
        $files = glob($this->cache_dir . '*.cache');
        $cleaned = 0;
        
        foreach ($files as $file) {
            if (file_exists($file)) {
                $data = unserialize(file_get_contents($file));
                if (isset($data['expires']) && $data['expires'] < time()) {
                    unlink($file);
                    $cleaned++;
                }
            }
        }
        
        return $cleaned;
    }
    
    /**
     * Get cache statistics
     */
    public function getStats() {
        $files = glob($this->cache_dir . '*.cache');
        $stats = [
            'total_files' => count($files),
            'total_size' => 0,
            'expired_files' => 0,
            'valid_files' => 0
        ];
        
        foreach ($files as $file) {
            $size = filesize($file);
            $stats['total_size'] += $size;
            
            $data = unserialize(file_get_contents($file));
            if (isset($data['expires']) && $data['expires'] < time()) {
                $stats['expired_files']++;
            } else {
                $stats['valid_files']++;
            }
        }
        
        $stats['total_size_formatted'] = $this->formatBytes($stats['total_size']);
        return $stats;
    }
}

// Global cache instance
$cache = new SimpleCache();
?>



