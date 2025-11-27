<?php 

namespace Controllers;

use core\controller;
use models\usuarios;

class UsuarioController extends Controller{
    public function index(){
        $usuarios = (new Usuarios())->getAllUsers();
        // Llama a la vista y le pasa los datos
        $this->render('usuarios/index', ['usuarios' => $usuarios]);
    }
}


?>