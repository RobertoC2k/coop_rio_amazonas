<?php
/**
 * Clase base de todos los controladores (Controllers/*.php la extienden).
 * Al crearse un controlador:
 *  1. Crea el objeto Views, que sabe cargar las vistas.
 *  2. Carga automáticamente su modelo: el controlador "Vehiculos" usa "VehiculosModel".
 *  3. Si quien navega es personal (tipo 1), obtiene las notificaciones de la campana
 *     del menú superior (Templates/header.php) y se las entrega a Views.
 */
class Controller{
    protected $views, $model;
    public function __construct()
    {
        $this->views = new Views();
        $this->cargarModel();
        $this->cargarNotificaciones();
    }
    public function cargarModel()
    {
        $model = get_class($this)."Model";
        $ruta = "Models/".$model.".php";
        if (file_exists($ruta)) {
            require_once $ruta;
            $this->model = new $model();
        }
    }
    // Reservas pendientes (estado 0) que se muestran en la campana del panel del personal.
    // MVC: la plantilla no consulta la base de datos; el controlador le entrega los datos.
    private function cargarNotificaciones()
    {
        if (isset($_SESSION['tipo']) && $_SESSION['tipo'] == 1) {
            require_once 'Models/ReservasModel.php';
            $reservas = new ReservasModel();
            $this->views->notificaciones = $reservas->getNuevasReservas();
        }
    }
}

?>
