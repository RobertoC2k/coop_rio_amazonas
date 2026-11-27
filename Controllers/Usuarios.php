<?php
/**
 * Controlador de Usuarios (personal administrativo).
 * Solo recibe datos de la vista, llama al modelo (UsuariosModel) y responde.
 */
class Usuarios extends Controller{
    public function __construct() {
        session_start();
        // Solo el personal (tipo 1) puede entrar a este módulo.
        if (empty($_SESSION['id_usuario']) || $_SESSION['tipo'] != 1) {
            header("location: " . base_url . 'login');
            exit;
        }
        parent::__construct();
    }

    public function index()
    {
        $this->views->getView("usuarios/index");
    }

    public function listar()
    {
        $data = $this->model->getUsuarios(1);
        // Aquí solo se arma el HTML de la tabla (presentación)
        for ($i = 0; $i < count($data); $i++) {
            $data[$i]['estado'] = '<span class="badge bg-success">Activo</span>';
            $data[$i]['editar'] = '<button class="btn btn-outline-primary" type="button" onclick="btnEditarUser(' . $data[$i]['id'] . ');"><i class="fas fa-edit"></i></button>';
            $data[$i]['eliminar'] = '<button class="btn btn-outline-danger" type="button" onclick="btnEliminarUser(' . $data[$i]['id'] . ');"><i class="fas fa-trash-alt"></i></button>';
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function registrar()
    {
        // 1. Recibir datos de la vista
        $datos = array(
            'id'        => strClean($_POST['id']),
            'usuario'   => strClean($_POST['usuario']),
            'nombre'    => strClean($_POST['nombre']),
            'correo'    => strClean($_POST['correo']),
            'telefono'  => strClean($_POST['telefono']),
            'clave'     => strClean($_POST['clave']),
            'confirmar' => strClean($_POST['confirmar'])
        );
        // 2. El modelo valida, comprueba duplicados y registra o modifica
        $msg = $this->model->guardar($datos);
        // 3. Responder a la vista
        echo json_encode($msg, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function editar(int $id)
    {
        $data = $this->model->getUsuario($id);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function eliminar(int $id)
    {
        $msg = $this->model->darDeBaja($id, $_SESSION['id_usuario']);
        echo json_encode($msg, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function cambiarPass()
    {
        $msg = $this->model->cambiarClave(
            $_SESSION['id_usuario'],
            strClean($_POST['clave_actual']),
            strClean($_POST['clave_nueva']),
            strClean($_POST['confirmar_clave'])
        );
        echo json_encode($msg, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function perfil()
    {
        $data = $this->model->getUsuario($_SESSION['id_usuario']);
        $this->views->getView("usuarios/perfil", $data);
    }

    public function actualizarDato()
    {
        $datos = array(
            'usuario'   => strClean($_POST['usuario']),
            'nombre'    => strClean($_POST['nombre']),
            'apellido'  => strClean($_POST['apellido']),
            'correo'    => strClean($_POST['correo']),
            'telefono'  => strClean($_POST['telefono']),
            'direccion' => strClean($_POST['direccion'])
        );
        // El modelo valida los datos, la foto y la guarda
        $msg = $this->model->actualizarPerfil($_SESSION['id_usuario'], $datos, $_FILES['imagen']);
        echo json_encode($msg, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function salir()
    {
        session_destroy();
        header("location: " . base_url);
        exit;
    }
}
