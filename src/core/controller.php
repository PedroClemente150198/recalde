<?php 

namespace Core;
class Controller {
    protected function render(string $view, array $data = []) {
        extract($data);
        $viewFile = BASE_PATH . '/src/views/' . $view . '.php';
        
        if (file_exists($viewFile)) {
            require_once $viewFile;
        } else {
            echo "La vista no existe: " . $viewFile;
        }
    }

    // Redirecciona a otra ruta
    protected function redirect(string $url) {
        header("Location: {$url}");
        exit;
    }
}

?>