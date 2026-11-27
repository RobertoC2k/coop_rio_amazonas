<?php
/**
 * Controlador de Contacto (página pública).
 * Solo recibe datos de la vista, llama al modelo (ContactModel) y responde.
 */
class Contact extends Controller
{
    public function __construct()
    {
        session_start();
        parent::__construct();
    }

    public function index()
    {
        $data['active'] = 'contact';
        $data['title'] = 'Contactos';
        $data['detail'] = 'Contactenos';
        $this->views->getView("contact", $data);
    }

    public function enviarCorreo()
    {
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $msg = array('msg' => 'Error desconocido', 'icono' => 'error');
        } else {
            // 1. Recibir datos de la vista
            $datos = array(
                'nombre'  => strClean($_POST['nombre']),
                'asunto'  => strClean($_POST['asunto']),
                'correo'  => strClean($_POST['correo']),
                'mensaje' => strClean($_POST['mensaje'])
            );
            // 2. El modelo valida y envía el correo
            $msg = $this->model->enviarMensaje($datos);
        }
        // 3. Responder a la vista
        echo json_encode($msg, JSON_UNESCAPED_UNICODE);
        die();
    }
}
