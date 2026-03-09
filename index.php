<?php

require_once __DIR__ . '/src/core/autoload.php';
startSecureSession();
sendResponseSecurityHeaders();

//echo __DIR__. '/src/models/usuarios.php';
//echo BASE_PATH;
//require_once BASE_PATH.'/src/models/usuarios.php';
//


use controllers\usuariocontroller;
use controllers\logincontroller;
use controllers\dashboardcontroller;

// Por ejemplo, ?route=usuarios
$route = trim((string) ($_GET['route'] ?? 'home'));

$publicRoutes = [
    'login',
    'validar-login',
    'forgot-password',
    'forgot-password-send',
    'reset-password',
    'reset-password-save'
];
if (!in_array($route, $publicRoutes, true)) {
    if (!isset($_SESSION['usuario'])) {
        header("Location: ?route=login");
        exit();
    }

    getDashboardCsrfToken();

    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
        $csrfToken = extractRequestCsrfToken();
        if (!validateDashboardCsrfToken($csrfToken)) {
            respondCsrfFailureAndExit();
        }
    }

    $mustChangePassword = (int) ($_SESSION['usuario']['debe_cambiar_contrasena'] ?? 0) === 1;
    if ($mustChangePassword) {
        $allowedDuringPasswordChange = ['dashboard', 'perfil', 'perfil-actualizar', 'logout'];
        if (!in_array($route, $allowedDuringPasswordChange, true)) {
            $expectsJson = $_SERVER['REQUEST_METHOD'] !== 'GET'
                || str_ends_with($route, '-data')
                || in_array($route, ['pedido-detalle', 'venta-detalle', 'historial-detalle', 'home-data', 'developer-data', 'dashboard-ui-data'], true);

            if ($expectsJson) {
                header('Content-Type: application/json; charset=UTF-8');
                http_response_code(403);
                echo json_encode([
                    'ok' => false,
                    'message' => 'Debes cambiar tu contraseña antes de continuar.',
                    'force_password_change' => true,
                    'redirect' => '?route=perfil'
                ], JSON_UNESCAPED_UNICODE);
                exit();
            }

            header("Location: ?route=perfil");
            exit();
        }
    }
}

function sendResponseSecurityHeaders(): void {
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    if (isHttpsRequest()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function extractRequestCsrfToken(): string {
    $postToken = trim((string) ($_POST['csrf_token'] ?? ''));
    if ($postToken !== '') {
        return $postToken;
    }

    return trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
}

function respondCsrfFailureAndExit(): void {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(419);
    echo json_encode([
        'ok' => false,
        'message' => 'Tu sesión expiró. Recarga la página e inténtalo de nuevo.',
        'code' => 'csrf_invalid'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

//echo $route.'<br>';

switch($route) {
    case 'login':
        $controller = new LoginController();
        $controller->index();
        break;
    
    case 'validar-login':
        $controller = new LoginController();
        $controller->authenticate();
        break;

    case 'forgot-password':
        $controller = new LoginController();
        $controller->forgotPassword();
        break;

    case 'forgot-password-send':
        $controller = new LoginController();
        $controller->sendPasswordResetLink();
        break;

    case 'reset-password':
        $controller = new LoginController();
        $controller->resetPassword();
        break;

    case 'reset-password-save':
        $controller = new LoginController();
        $controller->savePasswordReset();
        break;
    
    case 'logout':
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            header("Location: ?route=dashboard");
            exit();
        }

        startSecureSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'ok' => true,
            'redirect' => '?route=login'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    
    case 'usuarios':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new UsuarioController();
        $controller->index();
        break;

    case 'dashboard':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->index();
        break;
    
    case 'ventas':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->ventas();
        break;

    case 'venta-crear':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->ventaCrear();
        break;

    case 'venta-actualizar':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->ventaActualizar();
        break;

    case 'venta-detalle':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->ventaDetalle();
        break;

    case 'venta-abono-crear':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->ventaAbonoCrear();
        break;

    case 'venta-eliminar':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->ventaEliminar();
        break;
    
    case 'perfil':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->perfil();
        break;

    case 'perfil-crear':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->perfilCrear();
        break;

    case 'perfil-actualizar':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->perfilActualizar();
        break;

    case 'perfil-eliminar':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->perfilEliminar();
        break;

    case 'clientes':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->clientes();
        break;

    case 'cliente-crear':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->clienteCrear();
        break;

    case 'cliente-eliminar':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->clienteEliminar();
        break;

    case 'cliente-actualizar':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->clienteActualizar();
        break;
    
    case 'pedidos':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->pedidos();
        break;

    case 'pedido-detalle':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->pedidoDetalle();
        break;

    case 'pedido-crear':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->pedidoCrear();
        break;

    case 'pedido-actualizar':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->pedidoActualizar();
        break;

    case 'pedido-eliminar':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->pedidoEliminar();
        break;
    
    case 'historial':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->historial();
        break;

    case 'historial-detalle':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->historialDetalle();
        break;

    case 'historial-anular':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->historialAnular();
        break;

    case 'producto-crear':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->productoCrear();
        break;

    case 'producto-actualizar':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->productoActualizar();
        break;

    case 'producto-eliminar':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->productoEliminar();
        break;

    case 'categoria-crear':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->categoriaCrear();
        break;

    case 'categoria-actualizar':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->categoriaActualizar();
        break;

    case 'categoria-eliminar':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->categoriaEliminar();
        break;
    
    case 'inventario':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->inventario();
        break;
    
    case 'configuracion':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->configuration();
        break;

    case 'developer':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->developer();
        break;

    case 'developer-data':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->developerData();
        break;

    case 'developer-action':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->developerAction();
        break;

    case 'dashboard-ui-data':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->dashboardUiData();
        break;

    case 'home':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->home();
        break;

    case 'home-data':
        startSecureSession();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->homeData();
        break;
    default:
        header("Location: ?route=login");
        exit();
}
