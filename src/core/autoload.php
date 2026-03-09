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

            if ($name === '') {
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

if (!function_exists('isHttpsRequest')) {
    function isHttpsRequest(): bool {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off' && $https !== '0') {
            return true;
        }

        $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if (str_contains($forwardedProto, 'https')) {
            return true;
        }

        return (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }
}

if (!function_exists('configureSessionCookies')) {
    function configureSessionCookies(): void {
        if (headers_sent()) {
            return;
        }

        $secure = isHttpsRequest();
        $current = session_get_cookie_params();
        $basePath = defined('BASE_URL') ? trim((string) BASE_URL) : '';
        $cookiePath = $basePath !== '' ? rtrim($basePath, '/') . '/' : '/';

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookiePath,
            'domain' => (string) ($current['domain'] ?? ''),
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.cookie_secure', $secure ? '1' : '0');
    }
}

if (!function_exists('startSecureSession')) {
    function startSecureSession(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        configureSessionCookies();
        session_start();
    }
}

if (!function_exists('generateAppToken')) {
    function generateAppToken(): string {
        try {
            return bin2hex(random_bytes(32));
        } catch (\Throwable $e) {
            return hash('sha256', uniqid('app_token', true) . microtime(true));
        }
    }
}

if (!function_exists('getDashboardCsrfToken')) {
    function getDashboardCsrfToken(): string {
        startSecureSession();
        $token = (string) ($_SESSION['csrf_dashboard'] ?? '');
        if ($token === '') {
            $token = generateAppToken();
            $_SESSION['csrf_dashboard'] = $token;
        }
        return $token;
    }
}

if (!function_exists('regenerateDashboardCsrfToken')) {
    function regenerateDashboardCsrfToken(): string {
        startSecureSession();
        $token = generateAppToken();
        $_SESSION['csrf_dashboard'] = $token;
        return $token;
    }
}

if (!function_exists('validateDashboardCsrfToken')) {
    function validateDashboardCsrfToken(string $providedToken): bool {
        startSecureSession();
        $storedToken = (string) ($_SESSION['csrf_dashboard'] ?? '');
        $providedToken = trim($providedToken);

        if ($storedToken === '' || $providedToken === '') {
            return false;
        }

        return hash_equals($storedToken, $providedToken);
    }
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
