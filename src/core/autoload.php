<?php 

/* 
NOTA IMPORTANTE:
Las 2 primeras lineas son para mostrar los errores en el navegador 
*/
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Definición de constante BASE_PATH si no está definida
if(!defined('BASE_PATH')){
    define('BASE_PATH', '/var/www/html/recalde' ); // Asume core/ dentro de raíz
}

// Cargar automáticamente las clases
spl_autoload_register(function ($class) {
    // Convierte namespace en ruta de archivo
    $classPath = BASE_PATH . '/src/' . str_replace('\\', '/', $class) . '.php';

    if (file_exists($classPath)) {
        require_once $classPath;
    }
});

// Cargar configuración y DB
//define ('BASE_PATH', '/var/www/html/recalde' );
require_once BASE_PATH . '/config/db.php';
define ('APP_NAME', 'RECALDE' );
define ('APP_VERSION', '1.0.0' );


?>