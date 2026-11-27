<?php
/**
 * Controlador de Clientes.
 * Solo recibe datos de la vista, llama al modelo (ClientesModel) y responde.
 * - Gestión de clientes (listar, registrar, editar, dar de baja, buscar): solo personal (tipo 1).
 * - Perfil propio (ver, actualizar datos, cambiar contraseña): solo el cliente (tipo 2).
 */
class Clientes extends Controller{
    public function __construct() {
        session_start();
        if (empty($_SESSION['id_usuario'])) {
            header("location: " . base_url . 'login');
            exit;
        }
        parent::__construct();
    }

    // Corta la petición si quien llama no es personal de la cooperativa
    private function soloPersonal()
    {
        if ($_SESSION['tipo'] != 1) {
            header("location: " . base_url . 'login');
            exit;
        }
    }

    // Corta la petición si quien llama no es un cliente (el personal tiene su perfil en Usuarios)
    private function soloCliente()
    {
        if ($_SESSION['tipo'] == 1) {
            header("location: " . base_url . 'usuarios/perfil');
            exit;
        }
    }

    public function index()
    {
        $this->soloPersonal();
        $this->views->getView('clientes/index');
    }

    public function listar()
    {
        $this->soloPersonal();
        $data = escaparFilas($this->model->getClientes(1));
        // Aquí solo se arma el HTML de la tabla (presentación)
        for ($i = 0; $i < count($data); $i++) {
            $data[$i]['estado'] = '<span class="badge bg-success">Activo</span>';
            $data[$i]['editar'] = '<button class="btn btn-outline-primary" type="button" onclick="btnEditarCli(' . $data[$i]['id'] . ');"><i class="fas fa-edit"></i></button>';
            $data[$i]['eliminar'] = '<button class="btn btn-outline-danger" type="button" onclick="btnEliminarCli(' . $data[$i]['id'] . ');"><i class="fas fa-trash-alt"></i></button>';
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function registrar()
    {
        $this->soloPersonal();
        // 1. Recibir datos de la vista
        $datos = array(
            'id'        => strClean($_POST['id']),
            'dni'       => strClean($_POST['dni']),
            'nombre'    => strClean($_POST['nombre']),
            'telefono'  => strClean($_POST['telefono']),
            'direccion' => strClean($_POST['direccion'])
        );
        // 2. El modelo valida, comprueba duplicados y registra o modifica
        $msg = $this->model->guardar($datos);
        // 3. Responder a la vista
        echo json_encode($msg, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function editar(int $id)
    {
        $this->soloPersonal();
        $data = $this->model->getCliente($id);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function eliminar(int $id)
    {
        $this->soloPersonal();
        $msg = $this->model->darDeBaja($id);
        echo json_encode($msg, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function buscarCliente()
    {
        // Solo el personal puede buscar clientes: devuelve nombres y direcciones.
        $this->soloPersonal();
        $texto = isset($_GET['cli']) ? strClean($_GET['cli']) : '';
        echo json_encode($this->model->sugerencias($texto), JSON_UNESCAPED_UNICODE);
        die();
    }

    public function cambiarPass()
    {
        $this->soloCliente();
        $msg = $this->model->cambiarClave(
            $_SESSION['id_usuario'],
            ($_POST['clave_actual'] ?? ''),
            ($_POST['clave_nueva'] ?? ''),
            ($_POST['confirmar_clave'] ?? '')
        );
        echo json_encode($msg, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function perfil()
    {
        $this->soloCliente();
        $data = $this->model->getCliente($_SESSION['id_usuario']);
        $this->views->getView("clientes/perfil", $data);
    }

    public function actualizarDato()
    {
        $this->soloCliente();
        $datos = array(
            'nombre'    => strClean($_POST['nombre']),
            'dni'       => strClean($_POST['dni']),
            'correo'    => strClean($_POST['correo']),
            'telefono'  => strClean($_POST['telefono']),
            'direccion' => strClean($_POST['direccion'])
        );
        $msg = $this->model->actualizarPerfil($_SESSION['id_usuario'], $datos);
        echo json_encode($msg, JSON_UNESCAPED_UNICODE);
        die();
    }
}
