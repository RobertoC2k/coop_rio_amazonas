<?php
class Login extends Controller
{
    public function __construct() {
        session_start();
        parent::__construct();
    }
    public function index()
    {
        // Si ya hay una sesión abierta, no se muestra el login: se envía al panel
        if (!empty($_SESSION['id_usuario'])) {
            header('Location: ' . base_url . 'dashboard');
            exit;
        }
        // Evita que el navegador guarde el login en caché (botón "atrás")
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        $data['title'] = 'Login';
        $this->views->getView("login", $data);
    }

    public function validar()
    {
        // 1. Recibir datos de la vista
        $usuario = strClean($_POST['usuario']);
        $clave = strClean($_POST['clave']);

        // 2. El modelo decide si las credenciales son válidas y qué perfil tiene
        $resultado = $this->model->autenticar($usuario, $clave);

        // 3. Si entró, iniciar una sesión nueva y limpia
        if ($resultado['sesion'] != null) {
            $_SESSION = array();            // borra datos de una sesión anterior
            session_regenerate_id(true);    // nuevo identificador de sesión (seguridad)
            foreach ($resultado['sesion'] as $clave_sesion => $valor) {
                $_SESSION[$clave_sesion] = $valor;
            }
        }

        // 4. Responder a la vista (login.js)
        $msg = array('msg' => $resultado['msg'], 'icono' => $resultado['icono']);
        echo json_encode($msg, JSON_UNESCAPED_UNICODE);
        die();
    }
}
