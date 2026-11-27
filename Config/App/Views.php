<?php
/**
 * Cargador de vistas.
 * getView("vehiculos/index", $data) incluye el archivo Views/vehiculos/index.php.
 * Dentro de la vista quedan disponibles:
 *  - $data: lo que el controlador obtuvo del modelo.
 *  - $notificaciones: reservas pendientes para la campana (solo personal, la usa Templates/header.php).
 * La propia vista incluye su cabecera y pie (header/footer) al inicio y al final.
 */
class Views{
    public $notificaciones = array();

    public function getView($vista, $data="")
    {
        $notificaciones = $this->notificaciones;
        require "Views/" . $vista . ".php";
    }
}
?>
