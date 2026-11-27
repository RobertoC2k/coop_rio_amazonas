<?php
/**
 * PUNTO DE ENTRADA ÚNICO (front controller).
 * Toda petición llega aquí: el archivo .htaccess convierte una URL como
 *     /coop_rio_amazonas/vehiculos/editar/5
 * en
 *     index.php?url=vehiculos/editar/5
 *
 * Recorrido completo de una petición:
 *  1. index.php          -> lee la URL y decide controlador, método y parámetros.
 *  2. Controllers/X.php  -> el constructor valida la sesión/rol y carga su modelo (Config/App/Controller.php).
 *  3. Models/XModel.php  -> aplica las reglas de negocio y consulta la base de datos (Config/App/Query.php).
 *  4. Views/...php       -> el controlador llama a $this->views->getView() (Config/App/Views.php)
 *                           o responde JSON si es una petición AJAX.
 *  5. Templates          -> la vista incluye header y footer (Config/Helpers.php: cargarHeader/cargarFooter).
 */

// 1. Configuración global: base_url, datos de conexión a la BD y SMTP
require_once 'Config/Config.php';

// 2. Separar la URL en partes. Si no hay URL se muestra la página pública de inicio.
//    "vehiculos/editar/5" -> ["vehiculos", "editar", "5"]
$ruta = !empty($_GET['url']) ? $_GET['url'] : "major/index";
$partes = explode("/", trim($ruta, "/"));

$controller = ucfirst($partes[0]);                                   // "Vehiculos"
$metodo     = !empty($partes[1]) ? $partes[1] : "index";             // "editar" (por defecto "index")
$parametro  = implode(",", array_filter(array_slice($partes, 2), 'strlen')); // "5" (varios se unen con coma)

// 3. Cargar las clases base (Controller, Views, Query, Conexion) y las funciones de ayuda
require_once 'Config/App/Autoload.php';
require_once 'Config/Helpers.php';

// 4. Buscar el controlador y ejecutar el método pedido
$dirControllers = "Controllers/" . $controller . ".php";

// Solo letras en el nombre del controlador (evita rutas a otras carpetas)
$nombreValido = preg_match('/^[A-Za-z]+$/', $controller);

if ($nombreValido && file_exists($dirControllers)) {
    require_once $dirControllers;
    $objeto = new $controller();     // el constructor valida sesión/rol y carga el modelo
    // Solo se pueden llamar desde la URL los métodos públicos y que no sean internos de PHP (__construct, etc.)
    if (is_callable(array($objeto, $metodo)) && strpos($metodo, '__') !== 0) {
        $objeto->$metodo($parametro);
    } else {
        header('Location: ' . base_url . 'errors');
        exit;
    }
} else {
    header('Location: ' . base_url . 'errors');
    exit;
}
