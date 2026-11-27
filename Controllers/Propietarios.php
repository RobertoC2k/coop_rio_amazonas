<?php
/**
 * Controlador de Propietarios (dueños de los vehículos).
 * Solo recibe datos de la vista, llama al modelo (PropietariosModel) y responde.
 */
class Propietarios extends Controller
{
    public function __construct()
    {
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
        $this->views->getView("propietarios/index");
    }

    public function listar()
    {
        $data = escaparFilas($this->model->getPropietarios(1));
        // Aquí solo se arma el HTML de la tabla (presentación)
        for ($i = 0; $i < count($data); $i++) {
            $data[$i]['estado'] = '<span class="badge bg-success">Activo</span>';
            $data[$i]['editar'] = '<button class="btn btn-outline-primary" type="button" onclick="btnEditarProp(' . $data[$i]['id'] . ');"><i class="fas fa-edit"></i></button>';
            $data[$i]['eliminar'] = '<button class="btn btn-outline-danger" type="button" onclick="btnEliminarProp(' . $data[$i]['id'] . ');"><i class="fas fa-trash-alt"></i></button>';
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function registrar()
    {
        // 1. Recibir datos de la vista
        $datos = array(
            'id'      => strClean($_POST['id']),
            'cedula'  => strClean($_POST['cedula']),
            'nombre'  => strClean($_POST['nombre']),
            'correo'  => strClean($_POST['correo']),
            'celular' => strClean($_POST['celular'])
        );
        // 2. El modelo valida, comprueba duplicados y registra o modifica
        $msg = $this->model->guardar($datos);
        // 3. Responder a la vista
        echo json_encode($msg, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function editar(int $id)
    {
        $data = $this->model->getPropietario($id);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function eliminar(int $id)
    {
        $msg = $this->model->darDeBaja($id);
        echo json_encode($msg, JSON_UNESCAPED_UNICODE);
        die();
    }
}
