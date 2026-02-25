<?php 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__. '/src/core/autoload.php';

//echo __DIR__. '/src/models/usuarios.php';
//echo BASE_PATH;
//require_once BASE_PATH.'/src/models/usuarios.php';
//


use controllers\usuariocontroller;
use controllers\logincontroller;
use controllers\dashboardcontroller;

// Por ejemplo, ?route=usuarios
$route = $_GET['route'] ?? 'home';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$publicRoutes = ['login', 'validar-login', 'logout'];
if (!in_array($route, $publicRoutes, true)) {
    if (!isset($_SESSION['usuario'])) {
        header("Location: ?route=login");
        exit();
    }

    $mustChangePassword = (int) ($_SESSION['usuario']['debe_cambiar_contrasena'] ?? 0) === 1;
    if ($mustChangePassword) {
        $allowedDuringPasswordChange = ['dashboard', 'perfil', 'perfil-actualizar', 'logout'];
        if (!in_array($route, $allowedDuringPasswordChange, true)) {
            $expectsJson = $_SERVER['REQUEST_METHOD'] !== 'GET'
                || str_ends_with($route, '-data')
                || in_array($route, ['pedido-detalle', 'historial-detalle', 'home-data', 'developer-data'], true);

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
    
    case 'logout':
        if (session_status() === PHP_SESSION_NONE) session_start();
        session_destroy();
        header("Location: ?route=login");
        exit();
    
    case 'usuarios':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new UsuarioController();
        $controller->index();
        break;

    case 'dashboard':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->index();
        break;
    
    case 'ventas':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->ventas();
        break;

    case 'venta-crear':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->ventaCrear();
        break;

    case 'venta-actualizar':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->ventaActualizar();
        break;

    case 'venta-eliminar':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->ventaEliminar();
        break;
    
    case 'perfil':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->perfil();
        break;

    case 'perfil-crear':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->perfilCrear();
        break;

    case 'perfil-actualizar':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->perfilActualizar();
        break;

    case 'perfil-eliminar':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->perfilEliminar();
        break;

    case 'clientes':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->clientes();
        break;

    case 'cliente-crear':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->clienteCrear();
        break;

    case 'cliente-eliminar':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->clienteEliminar();
        break;

    case 'cliente-actualizar':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->clienteActualizar();
        break;
    
    case 'pedidos':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->pedidos();
        break;

    case 'pedido-detalle':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->pedidoDetalle();
        break;

    case 'pedido-crear':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->pedidoCrear();
        break;

    case 'pedido-actualizar':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->pedidoActualizar();
        break;
    
    case 'historial':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->historial();
        break;

    case 'historial-detalle':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->historialDetalle();
        break;

    case 'historial-anular':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->historialAnular();
        break;

    case 'producto-crear':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->productoCrear();
        break;

    case 'producto-actualizar':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->productoActualizar();
        break;

    case 'producto-eliminar':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->productoEliminar();
        break;

    case 'categoria-crear':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->categoriaCrear();
        break;

    case 'categoria-actualizar':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->categoriaActualizar();
        break;

    case 'categoria-eliminar':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->categoriaEliminar();
        break;
    
    case 'inventario':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->inventario();
        break;
    
    case 'configuracion':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->configuration();
        break;

    case 'developer':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->developer();
        break;

    case 'developer-data':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->developerData();
        break;

    case 'developer-action':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->developerAction();
        break;

    case 'home':
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['usuario'])) {
            header("Location: ?route=login");
            exit();
        }
        $controller = new DashboardController();
        $controller->home();
        break;

    case 'home-data':
        if (session_status() === PHP_SESSION_NONE) session_start();
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
/*
use models\usuarios;

$getUsers = new Usuarios();
$results = $getUsers->getAllUsers();

if(!$results){
    echo "No se encontraron usuarios.";
    return;
}
echo 'Lista de usuarios registrados:' . "<br>";
foreach ($results as $row) {
    echo "<p>Usuario ID: " . $row['id'] . "<br>Usuario: " . $row['usuario'] . "<br>Contraseña: ".$row["contrasena"]."<br>Email: " . $row['correo'] . "<br></p>";
}
*/


//require_once __DIR__. '/config/db.php';
//$usuario = new Usuarios();
//$results = $usuario->getAllUsers();
//if (!$results) {
//    echo "No se encontraron usuarios.";
//    return;
//}
//foreach ($results as $row) {
//    echo "<h1>Usuario ID: " . $row['id'] . " Nombre: " . $row['nombre'] . " Email: " . $row['email'] . "<br></h1>";
//}

//$dabase = new Database();
//$dabase->connect();
//$query = $dabase->connect()->query("SELECT * FROM roles");
//$results = $query->fetchAll(PDO::FETCH_ASSOC);
//if (!$results) {
//    echo "No se encontraron roles.";
//    return;
//}
//foreach ($results as $row) {
//    echo "<h1>Role ID: " . $row['id'] . " Rol: " . $row['rol'] . "<br></h1>";
//}
//$query->closeCursor();
//$dabase->disconnect();

?>
