<?php
function strClean($cadena)
{
    $string = preg_replace(['/\s+/','/^\s|\s$/'],[' ',''], $cadena);
    $string = trim($string);
    $string = stripslashes($string);
    $string = str_ireplace('<script>', '', $string);
    $string = str_ireplace('</script>', '', $string);
    $string = str_ireplace('<script type=>', '', $string);
    $string = str_ireplace('<script src>', '', $string);
    $string = str_ireplace('SELECT * FROM', '', $string);
    $string = str_ireplace('DELETE FROM', '', $string);
    $string = str_ireplace('INSERT INTO', '', $string);
    $string = str_ireplace('SELECT COUNT(*) FROM', '', $string);
    $string = str_ireplace('DROP TABLE', '', $string);
    $string = str_ireplace("OR '1'='1", '', $string);
    $string = str_ireplace('OR ´1´=´1', '', $string);
    $string = str_ireplace('IS NULL', '', $string);
    $string = str_ireplace('LIKE "', '', $string);
    $string = str_ireplace("LIKE '", '', $string);
    $string = str_ireplace('LIKE ´', '', $string);
    $string = str_ireplace('OR "a"="a', '', $string);
    $string = str_ireplace("OR 'a'='a", '', $string);
    $string = str_ireplace('OR ´a´=´a', '', $string);
    $string = str_ireplace('--', '', $string);
    $string = str_ireplace('^', '', $string);
    $string = str_ireplace('[', '', $string);
    $string = str_ireplace(']', '', $string);
    $string = str_ireplace('==', '', $string);
    return $string;
}

// Plantillas del panel interno. Cada vista llama a cargarHeader() en su primera línea
// y a cargarFooter() al final. Según el rol de la sesión se usa:
//   tipo 1 (personal) -> Templates/header.php y footer.php (menú de administración)
//   tipo 2 (cliente)  -> Templates/header-cliente.php y footer-cliente.php
// Las páginas públicas (Views/index.php, about.php, cars.php...) incluyen directamente
// Templates/header1.php, portada.php y footer1.php.
// $notificaciones se recibe como parámetro porque un include dentro de una función solo
// ve las variables de esa función: sin él, header.php no encontraba la variable.
function cargarHeader($tipo, $notificaciones = array()) {
    if ($tipo == 1) {
        include "Views/Templates/header.php";
    }else{
        include "Views/Templates/header-cliente.php";
    }
}

function cargarFooter($tipo) {
    if ($tipo == 1) {
        include "Views/Templates/footer.php";
    }else{
        include "Views/Templates/footer-cliente.php";
    }
}
?>