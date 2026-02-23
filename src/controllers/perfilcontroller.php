<?php 

namespace Controllers;
use core\controller;
use models\usuarios;

class PerfilController extends Controller{
    public function index(){
        // Llama a la vista del perfil
        $this->render('dashboard/perfil/index');
    }

    
}

?>