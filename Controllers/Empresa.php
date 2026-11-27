<?php
/**
 * Controlador de Empresa (datos de la cooperativa).
 * Solo recibe datos de la vista, llama al modelo (EmpresaModel) y responde.
 */
class Empresa extends Controller
{
    public function __construct()
    {
        session_start();
        if (empty($_SESSION['id_usuario'])) {
            header("location: " . base_url);
            exit;
        }
        // Solo el personal (tipo 1) puede ver y modificar los datos de la cooperativa.
        if ($_SESSION['tipo'] != 1) {
            header("location: " . base_url . 'reservas');
            exit;
        }
        parent::__construct();
    }

    public function index()
    {
        $data['empresa'] = $this->model->getEmpresa();
        $this->views->getView("dashboard/index", $data);
    }

    public function modificar()
    {
        // 1. Recibir datos de la vista
        $datos = array(
            'id'        => intval(strClean($_POST['id'])),
            'ruc'       => strClean($_POST['ruc']),
            'nombre'    => strClean($_POST['nombre']),
            'telefono'  => strClean($_POST['telefono']),
            'correo'    => strClean($_POST['correo']),
            'direccion' => strClean($_POST['direccion']),
            'mensaje'   => strClean($_POST['mensaje'])
        );
        // 2. El modelo valida los datos y el logo, y guarda
        $msg = $this->model->actualizar($datos, $_FILES['imagen']);
        // 3. Responder a la vista
        echo json_encode($msg, JSON_UNESCAPED_UNICODE);
        die();
    }
}
