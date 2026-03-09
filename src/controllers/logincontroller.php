<?php
namespace Controllers;

use core\controller;
use core\mailer as Mailer;
use models\usuarios as Usuarios;

class LoginController extends Controller {
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOGIN_WINDOW_SECONDS = 900;
    private const LOGIN_LOCK_SECONDS = 600;
    private const PASSWORD_RESET_TTL_SECONDS = 3600;
    private const MIN_PASSWORD_LENGTH = 8;
    private const INVALID_CREDENTIALS_MESSAGE = 'Usuario o contraseña incorrectos.';
    private const REMEMBER_COOKIE_NAME = 'recalde_remember_user';
    private const REMEMBER_COOKIE_DAYS = 30;

    public function index(): void {
        $this->startSessionIfNeeded();

        $flash = $this->pullFlash('login_flash');
        $usuario = trim((string) ($_SESSION['login_last_user'] ?? ''));
        $rememberedUser = $this->getRememberedUser();

        if ($usuario === '' && $rememberedUser !== '') {
            $usuario = $rememberedUser;
        }

        $rememberUser = ((int) ($_SESSION['login_last_remember'] ?? 0) === 1)
            || ($rememberedUser !== '' && $usuario === $rememberedUser);

        $waitSeconds = $usuario !== ''
            ? $this->secondsUntilUnlock($this->getAttemptState($usuario))
            : 0;

        $this->render('login/index', [
            'csrfToken' => $this->getCsrfToken('login'),
            'usuario' => $usuario,
            'rememberUser' => $rememberUser,
            'waitSeconds' => $waitSeconds,
            'success' => (($flash['type'] ?? '') === 'success') ? (string) ($flash['message'] ?? '') : '',
            'error' => (($flash['type'] ?? '') === 'error') ? (string) ($flash['message'] ?? '') : '',
        ]);
    }

    public function authenticate(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('?route=login');
            return;
        }

        $this->startSessionIfNeeded();

        $usuario = trim((string) ($_POST['usuario'] ?? ''));
        $password = trim((string) ($_POST['password'] ?? ''));
        $rememberUser = (int) ($_POST['remember_user'] ?? 0) === 1;

        $_SESSION['login_last_user'] = $usuario;
        $_SESSION['login_last_remember'] = $rememberUser ? 1 : 0;

        $csrfToken = (string) ($_POST['csrf_token'] ?? '');
        if (!$this->isValidCsrfToken('login', $csrfToken)) {
            $this->renderLoginError(
                'Tu sesión expiró. Recarga la página e intenta de nuevo.',
                $usuario,
                0,
                $rememberUser
            );
            return;
        }

        if ($usuario === '' || $password === '') {
            $this->renderLoginError('Completa usuario y contraseña.', $usuario, 0, $rememberUser);
            return;
        }

        $state = $this->getAttemptState($usuario);
        $waitSeconds = $this->secondsUntilUnlock($state);
        if ($waitSeconds > 0) {
            $this->renderLoginError(
                "Demasiados intentos fallidos. Intenta de nuevo en {$waitSeconds} segundos.",
                $usuario,
                $waitSeconds,
                $rememberUser
            );
            return;
        }

        $usuariosModel = new Usuarios();
        $user = $usuariosModel->getUserByUsername($usuario);
        $storedPassword = (string) ($user['contrasena'] ?? '');

        if (!$user || !$usuariosModel->verifyPassword($password, $storedPassword)) {
            $waitSeconds = $this->registerFailedAttempt($usuario);
            usleep(random_int(150000, 350000));
            $this->renderLoginError(self::INVALID_CREDENTIALS_MESSAGE, $usuario, $waitSeconds, $rememberUser);
            return;
        }

        if (strtolower((string) ($user['estado'] ?? '')) !== 'activo') {
            $this->renderLoginError('Tu usuario está inactivo. Contacta al administrador.', $usuario, 0, $rememberUser);
            return;
        }

        $this->clearFailedAttempts($usuario);

        $userId = (int) ($user['user_id'] ?? $user['id'] ?? 0);
        if ($userId > 0 && (
            $usuariosModel->needsPasswordMigration($storedPassword) ||
            $usuariosModel->passwordNeedsRehash($storedPassword)
        )) {
            $usuariosModel->upgradePasswordHash($userId, $password);
        }

        unset($user['contrasena']);

        session_regenerate_id(true);
        $_SESSION['usuario'] = $user;
        $_SESSION['auth_meta'] = [
            'logged_in_at' => date('c'),
            'ip' => $this->getClientIp(),
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ];

        if ($rememberUser) {
            $this->setRememberUserCookie($usuario);
        } else {
            $this->clearRememberUserCookie();
        }

        unset($_SESSION['login_last_user'], $_SESSION['login_last_remember']);
        $_SESSION[$this->csrfSessionKey('login')] = $this->generateCsrfToken();
        if (function_exists('regenerateDashboardCsrfToken')) {
            regenerateDashboardCsrfToken();
        }

        $mustChangePassword = (int) ($user['debe_cambiar_contrasena'] ?? 0) === 1;
        $this->redirect($mustChangePassword ? '?route=perfil' : '?route=dashboard');
    }

    public function forgotPassword(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->redirect('?route=forgot-password');
            return;
        }

        $this->startSessionIfNeeded();
        $flash = $this->pullFlash('forgot_flash');
        $identifier = trim((string) ($_SESSION['forgot_last_identifier'] ?? ''));

        $this->renderForgotView(
            (($flash['type'] ?? '') === 'error') ? (string) ($flash['message'] ?? '') : '',
            (($flash['type'] ?? '') === 'success') ? (string) ($flash['message'] ?? '') : '',
            $identifier
        );
    }

    public function sendPasswordResetLink(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('?route=forgot-password');
            return;
        }

        $this->startSessionIfNeeded();

        $identifier = trim((string) ($_POST['identificador'] ?? ''));
        $_SESSION['forgot_last_identifier'] = $identifier;

        $csrfToken = (string) ($_POST['csrf_token'] ?? '');
        if (!$this->isValidCsrfToken('forgot_password', $csrfToken)) {
            $this->renderForgotView('Tu sesión expiró. Intenta nuevamente.', '', $identifier);
            return;
        }

        if ($identifier === '') {
            $this->renderForgotView('Ingresa tu usuario o correo para continuar.', '', $identifier);
            return;
        }

        $usuariosModel = new Usuarios();
        $user = $this->findUserForRecovery($usuariosModel, $identifier);

        if ($user && strtolower((string) ($user['estado'] ?? '')) === 'activo') {
            $idUsuario = (int) ($user['user_id'] ?? $user['id'] ?? 0);
            $tokenData = $idUsuario > 0
                ? $usuariosModel->createPasswordResetToken($idUsuario, self::PASSWORD_RESET_TTL_SECONDS)
                : null;

            if ($tokenData) {
                $resetUrl = $this->buildPasswordResetUrl((string) ($tokenData['token'] ?? ''));
                $emailSent = $this->sendResetPasswordEmail(
                    (string) ($user['correo'] ?? ''),
                    (string) ($user['usuario'] ?? 'usuario'),
                    $resetUrl,
                    (int) ($tokenData['expires_in'] ?? self::PASSWORD_RESET_TTL_SECONDS)
                );

                if (!$emailSent) {
                    error_log("LoginController::sendPasswordResetLink => no se pudo enviar correo a {$user['correo']}");
                }
            } else {
                $reason = (string) ($usuariosModel->getLastError() ?? 'sin detalle');
                error_log("LoginController::sendPasswordResetLink => token no generado: {$reason}");
            }
        }

        usleep(random_int(120000, 280000));

        unset($_SESSION['forgot_last_identifier']);
        $this->renderForgotView(
            '',
            'Si existe una cuenta con esos datos, enviamos un enlace de recuperación a su correo.',
            ''
        );
    }

    public function resetPassword(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->redirect('?route=login');
            return;
        }

        $this->startSessionIfNeeded();
        $token = trim((string) ($_GET['token'] ?? ''));
        $context = $this->getResetTokenContext($token);

        if (!$context['valid']) {
            $this->renderResetView(
                'El enlace de recuperación es inválido o ya expiró.',
                '',
                '',
                '',
                false
            );
            return;
        }

        $this->renderResetView(
            '',
            '',
            $token,
            (string) ($context['usuario'] ?? ''),
            true
        );
    }

    public function savePasswordReset(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('?route=login');
            return;
        }

        $this->startSessionIfNeeded();

        $token = trim((string) ($_POST['token'] ?? ''));
        $newPassword = trim((string) ($_POST['new_password'] ?? ''));
        $confirmPassword = trim((string) ($_POST['confirm_password'] ?? ''));
        $csrfToken = (string) ($_POST['csrf_token'] ?? '');

        if (!$this->isValidCsrfToken('reset_password', $csrfToken)) {
            $this->renderResetView(
                'Tu sesión expiró. Vuelve a abrir el enlace de recuperación.',
                '',
                $token,
                '',
                false
            );
            return;
        }

        $context = $this->getResetTokenContext($token);
        if (!$context['valid']) {
            $this->renderResetView(
                'El enlace de recuperación es inválido o ya expiró.',
                '',
                '',
                '',
                false
            );
            return;
        }

        $usuario = (string) ($context['usuario'] ?? '');

        if ($newPassword === '' || $confirmPassword === '') {
            $this->renderResetView('Completa ambos campos de contraseña.', '', $token, $usuario, true);
            return;
        }

        if ($newPassword !== $confirmPassword) {
            $this->renderResetView('Las contraseñas no coinciden.', '', $token, $usuario, true);
            return;
        }

        if (strlen($newPassword) < self::MIN_PASSWORD_LENGTH) {
            $this->renderResetView(
                'La nueva contraseña debe tener al menos ' . self::MIN_PASSWORD_LENGTH . ' caracteres.',
                '',
                $token,
                $usuario,
                true
            );
            return;
        }

        $usuariosModel = new Usuarios();
        $updated = $usuariosModel->resetPasswordWithToken($token, $newPassword);
        if (!$updated) {
            $error = (string) ($usuariosModel->getLastError() ?? 'No se pudo cambiar la contraseña.');
            $contextAfterUpdate = $this->getResetTokenContext($token);
            $this->renderResetView(
                $error,
                '',
                $contextAfterUpdate['valid'] ? $token : '',
                $usuario,
                $contextAfterUpdate['valid']
            );
            return;
        }

        $this->pushFlash(
            'login_flash',
            'success',
            'Contraseña actualizada correctamente. Ya puedes iniciar sesión.'
        );

        $this->redirect('?route=login');
    }

    private function renderLoginError(
        string $error,
        string $usuario = '',
        int $waitSeconds = 0,
        bool $rememberUser = false
    ): void {
        $this->render('login/index', [
            'error' => $error,
            'success' => '',
            'usuario' => $usuario,
            'rememberUser' => $rememberUser,
            'waitSeconds' => max(0, $waitSeconds),
            'csrfToken' => $this->getCsrfToken('login')
        ]);
    }

    private function renderForgotView(string $error = '', string $success = '', string $identifier = ''): void {
        $this->render('login/forgot', [
            'error' => $error,
            'success' => $success,
            'identificador' => $identifier,
            'csrfToken' => $this->getCsrfToken('forgot_password')
        ]);
    }

    private function renderResetView(
        string $error = '',
        string $success = '',
        string $token = '',
        string $usuario = '',
        bool $tokenValid = false
    ): void {
        $this->render('login/reset', [
            'error' => $error,
            'success' => $success,
            'token' => $token,
            'usuario' => $usuario,
            'tokenValid' => $tokenValid,
            'csrfToken' => $this->getCsrfToken('reset_password')
        ]);
    }

    private function getResetTokenContext(string $token): array {
        $token = trim($token);
        if ($token === '') {
            return ['valid' => false, 'usuario' => ''];
        }

        $usuariosModel = new Usuarios();
        $user = $usuariosModel->getUserByPasswordResetToken($token);
        if (!$user) {
            return ['valid' => false, 'usuario' => ''];
        }

        $estado = strtolower(trim((string) ($user['estado'] ?? '')));
        if ($estado !== 'activo') {
            return ['valid' => false, 'usuario' => (string) ($user['usuario'] ?? '')];
        }

        return [
            'valid' => true,
            'usuario' => (string) ($user['usuario'] ?? ''),
            'correo' => (string) ($user['correo'] ?? ''),
            'id' => (int) ($user['id'] ?? 0),
        ];
    }

    private function findUserForRecovery(Usuarios $usuariosModel, string $identifier): ?array {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return $usuariosModel->getUserByEmail($identifier);
        }

        return $usuariosModel->getUserByUsername($identifier);
    }

    private function sendResetPasswordEmail(
        string $targetEmail,
        string $username,
        string $resetUrl,
        int $ttlSeconds
    ): bool {
        if (!filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $appName = (string) (defined('APP_NAME') ? APP_NAME : (getenv('APP_NAME') ?: 'RECALDE'));
        $minutes = max(1, (int) ceil($ttlSeconds / 60));
        $safeUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
        $safeAppName = htmlspecialchars($appName, ENT_QUOTES, 'UTF-8');

        $subject = "{$appName} | Recuperación de contraseña";
        $html = "
            <div style=\"font-family:Arial,Helvetica,sans-serif;background:#f8fafc;padding:24px;\">
                <div style=\"max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;padding:22px;border:1px solid #e2e8f0;\">
                    <h2 style=\"margin-top:0;color:#0f172a;\">Recuperación de contraseña</h2>
                    <p style=\"color:#334155;\">Hola <strong>{$safeUsername}</strong>, recibimos una solicitud para restablecer tu contraseña en {$safeAppName}.</p>
                    <p style=\"color:#334155;\">Haz clic en el siguiente botón para crear una nueva contraseña:</p>
                    <p style=\"margin:20px 0;\">
                        <a href=\"{$safeUrl}\" style=\"display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:8px;font-weight:700;\">
                            Restablecer contraseña
                        </a>
                    </p>
                    <p style=\"color:#475569;\">Este enlace caduca en {$minutes} minutos y solo puede usarse una vez.</p>
                    <p style=\"color:#64748b;font-size:13px;\">Si tú no solicitaste este cambio, puedes ignorar este correo.</p>
                    <hr style=\"border:none;border-top:1px solid #e2e8f0;margin:18px 0;\">
                    <p style=\"font-size:12px;color:#94a3b8;word-break:break-all;\">Si el botón no funciona, copia este enlace en tu navegador:<br>{$safeUrl}</p>
                </div>
            </div>
        ";

        $text = "Hola {$username},\n\n"
            . "Recibimos una solicitud para restablecer tu contraseña en {$appName}.\n"
            . "Usa este enlace para cambiarla (válido por {$minutes} minutos):\n{$resetUrl}\n\n"
            . "Si no solicitaste este cambio, ignora este correo.\n";

        $mailer = new Mailer();
        return $mailer->send($targetEmail, $subject, $html, $text);
    }

    private function buildPasswordResetUrl(string $token): string {
        $token = trim($token);
        if ($token === '') {
            return '';
        }

        $route = '?route=reset-password&token=' . urlencode($token);
        $appUrl = rtrim((string) (getenv('APP_URL') ?: ''), '/');
        if ($appUrl !== '') {
            return "{$appUrl}/{$route}";
        }

        $scheme = $this->isHttpsRequest() ? 'https' : 'http';
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $baseUrl = defined('BASE_URL') ? rtrim((string) BASE_URL, '/') : '';

        return "{$scheme}://{$host}{$baseUrl}/{$route}";
    }

    private function startSessionIfNeeded(): void {
        if (function_exists('startSecureSession')) {
            startSecureSession();
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    private function getCsrfToken(string $formKey): string {
        $key = $this->csrfSessionKey($formKey);
        $token = (string) ($_SESSION[$key] ?? '');
        if ($token !== '') {
            return $token;
        }

        $token = $this->generateCsrfToken();
        $_SESSION[$key] = $token;
        return $token;
    }

    private function isValidCsrfToken(string $formKey, string $providedToken): bool {
        $key = $this->csrfSessionKey($formKey);
        $storedToken = (string) ($_SESSION[$key] ?? '');
        if ($storedToken === '' || $providedToken === '') {
            return false;
        }

        $isValid = hash_equals($storedToken, $providedToken);
        if ($isValid) {
            $_SESSION[$key] = $this->generateCsrfToken();
        }

        return $isValid;
    }

    private function csrfSessionKey(string $formKey): string {
        $safe = preg_replace('/[^a-z0-9_\-]/i', '', strtolower(trim($formKey))) ?: 'default';
        return "csrf_{$safe}";
    }

    private function generateCsrfToken(): string {
        try {
            return bin2hex(random_bytes(32));
        } catch (\Throwable $e) {
            return hash('sha256', uniqid('csrf', true) . microtime(true));
        }
    }

    private function getAttemptState(string $usuario): array {
        $this->ensureThrottleStore();
        $bucketKey = $this->getAttemptBucketKey($usuario);
        $state = $_SESSION['login_throttle'][$bucketKey] ?? [];
        $now = time();

        $failed = (int) ($state['failed'] ?? 0);
        $firstFailedAt = (int) ($state['first_failed_at'] ?? 0);
        $lockedUntil = (int) ($state['locked_until'] ?? 0);

        if ($firstFailedAt > 0 && ($now - $firstFailedAt) > self::LOGIN_WINDOW_SECONDS) {
            $failed = 0;
            $firstFailedAt = 0;
        }

        if ($lockedUntil > 0 && $lockedUntil <= $now) {
            $failed = 0;
            $firstFailedAt = 0;
            $lockedUntil = 0;
        }

        $normalizedState = [
            'failed' => $failed,
            'first_failed_at' => $firstFailedAt,
            'locked_until' => $lockedUntil,
        ];

        $_SESSION['login_throttle'][$bucketKey] = $normalizedState;
        $this->purgeThrottleState($now);

        return $normalizedState;
    }

    private function registerFailedAttempt(string $usuario): int {
        $this->ensureThrottleStore();
        $bucketKey = $this->getAttemptBucketKey($usuario);
        $state = $this->getAttemptState($usuario);
        $now = time();

        if ((int) ($state['first_failed_at'] ?? 0) === 0) {
            $state['first_failed_at'] = $now;
        }

        $state['failed'] = (int) ($state['failed'] ?? 0) + 1;
        if ((int) $state['failed'] >= self::MAX_LOGIN_ATTEMPTS) {
            $state['locked_until'] = $now + self::LOGIN_LOCK_SECONDS;
        }

        $_SESSION['login_throttle'][$bucketKey] = $state;
        $this->purgeThrottleState($now);

        return $this->secondsUntilUnlock($state);
    }

    private function clearFailedAttempts(string $usuario): void {
        $this->ensureThrottleStore();
        $bucketKey = $this->getAttemptBucketKey($usuario);
        if (isset($_SESSION['login_throttle'][$bucketKey])) {
            unset($_SESSION['login_throttle'][$bucketKey]);
        }
    }

    private function secondsUntilUnlock(array $state): int {
        $lockedUntil = (int) ($state['locked_until'] ?? 0);
        if ($lockedUntil <= 0) {
            return 0;
        }

        return max(0, $lockedUntil - time());
    }

    private function purgeThrottleState(?int $now = null): void {
        $this->ensureThrottleStore();

        $now ??= time();
        foreach ($_SESSION['login_throttle'] as $key => $state) {
            if (!is_array($state)) {
                unset($_SESSION['login_throttle'][$key]);
                continue;
            }

            $firstFailedAt = (int) ($state['first_failed_at'] ?? 0);
            $lockedUntil = (int) ($state['locked_until'] ?? 0);
            if ($lockedUntil > 0 && $lockedUntil > $now) {
                continue;
            }

            if ($firstFailedAt > 0 && ($now - $firstFailedAt) <= self::LOGIN_WINDOW_SECONDS) {
                continue;
            }

            unset($_SESSION['login_throttle'][$key]);
        }
    }

    private function getAttemptBucketKey(string $usuario): string {
        $normalizedUser = strtolower(trim($usuario));
        return hash('sha256', $this->getClientIp() . '|' . $normalizedUser);
    }

    private function getClientIp(): string {
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
        return $ip !== '' ? $ip : '0.0.0.0';
    }

    private function ensureThrottleStore(): void {
        if (!isset($_SESSION['login_throttle']) || !is_array($_SESSION['login_throttle'])) {
            $_SESSION['login_throttle'] = [];
        }
    }

    private function getRememberedUser(): string {
        return trim((string) ($_COOKIE[self::REMEMBER_COOKIE_NAME] ?? ''));
    }

    private function setRememberUserCookie(string $usuario): void {
        $usuario = trim($usuario);
        if ($usuario === '') {
            return;
        }

        $cookiePath = $this->getCookiePath();
        setcookie(self::REMEMBER_COOKIE_NAME, $usuario, [
            'expires' => time() + (self::REMEMBER_COOKIE_DAYS * 86400),
            'path' => $cookiePath,
            'secure' => $this->isHttpsRequest(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function clearRememberUserCookie(): void {
        $cookiePath = $this->getCookiePath();
        setcookie(self::REMEMBER_COOKIE_NAME, '', [
            'expires' => time() - 3600,
            'path' => $cookiePath,
            'secure' => $this->isHttpsRequest(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function getCookiePath(): string {
        $baseUrl = defined('BASE_URL') ? trim((string) BASE_URL) : '';
        return $baseUrl !== '' ? rtrim($baseUrl, '/') . '/' : '/';
    }

    private function isHttpsRequest(): bool {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        if ($https === 'on' || $https === '1') {
            return true;
        }

        $serverPort = (int) ($_SERVER['SERVER_PORT'] ?? 0);
        if ($serverPort === 443) {
            return true;
        }

        $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        return $forwardedProto === 'https';
    }

    private function pushFlash(string $key, string $type, string $message): void {
        $_SESSION[$key] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    private function pullFlash(string $key): ?array {
        $flash = $_SESSION[$key] ?? null;
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }

        return is_array($flash) ? $flash : null;
    }
}
