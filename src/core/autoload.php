<?php

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}

if (!function_exists('loadDotEnv')) {
    function loadDotEnv(string $envFilePath): void {
        if (!is_readable($envFilePath)) {
            return;
        }

        $lines = file($envFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $entry = trim((string) $line);
            if ($entry === '' || str_starts_with($entry, '#') || !str_contains($entry, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $entry, 2);
            $name = trim($name);
            $value = trim($value);

            if ($name === '' || getenv($name) !== false) {
                continue;
            }

            $quoted =
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"));
            if ($quoted && strlen($value) >= 2) {
                $value = substr($value, 1, -1);
            }

            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
        }
    }
}

loadDotEnv(BASE_PATH . '/.env');

$appDebugRaw = getenv('APP_DEBUG');
$appDebug = filter_var($appDebugRaw === false ? '0' : $appDebugRaw, FILTER_VALIDATE_BOOLEAN);

error_reporting(E_ALL);
ini_set('display_errors', $appDebug ? '1' : '0');

if (!defined('BASE_URL')) {
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $baseDir = str_replace('\\', '/', dirname($scriptName));
    $baseUrl = ($baseDir === '/' || $baseDir === '.' || $baseDir === '') ? '' : rtrim($baseDir, '/');
    define('BASE_URL', $baseUrl);
}

spl_autoload_register(function ($class) {
    $classPath = BASE_PATH . '/src/' . str_replace('\\', '/', $class) . '.php';

    if (file_exists($classPath)) {
        require_once $classPath;
    }
});

require_once BASE_PATH . '/config/db.php';

if (!defined('APP_NAME')) {
    define('APP_NAME', getenv('APP_NAME') ?: 'RECALDE');
}

if (!defined('APP_VERSION')) {
    define('APP_VERSION', getenv('APP_VERSION') ?: '1.0.0');
}
