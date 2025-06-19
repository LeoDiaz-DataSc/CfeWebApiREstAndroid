<?php
/**
 * Simple PSR-3–like logger utility that writes to the file configured in Config.
 */
require_once dirname(__DIR__) . '/config/config.php';

class Logger {
    private static $levelMap = [
        'debug'   => 0,
        'info'    => 1,
        'warning' => 2,
        'error'   => 3,
    ];

    public static function log(string $level, string $message, array $context = []): void {
        $level = strtolower($level);
        if (!isset(self::$levelMap[$level])) {
            $level = 'info';
        }
        if (!Config::get('log_enabled')) {
            return;
        }
        $configuredLevel = Config::get('log_level') ?? 'debug';
        if (self::$levelMap[$level] < self::$levelMap[$configuredLevel]) {
            return; // Skip lower-priority logs
        }

        $date   = date('Y-m-d H:i:s');
        $entry  = sprintf('[%s] %s: %s', $date, strtoupper($level), $message);
        if (!empty($context)) {
            // Reduce context size to avoid enormous files
            $entry .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $entry .= PHP_EOL;

        $logFile = Config::get('log_file');
        $dir     = dirname($logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    // Convenience wrappers
    public static function debug(string $msg, array $ctx = []): void { self::log('debug', $msg, $ctx); }
    public static function info(string $msg, array $ctx = []): void { self::log('info', $msg, $ctx); }
    public static function warning(string $msg, array $ctx = []): void { self::log('warning', $msg, $ctx); }
    public static function error(string $msg, array $ctx = []): void { self::log('error', $msg, $ctx); }
}

// Backwards compatibility helper used elsewhere in the codebase
function api_log(string $level, string $message, array $context = []): void {
    Logger::log($level, $message, $context);
}
?>
