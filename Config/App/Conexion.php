<?php
class Conexion{
    private $conect;
    public function __construct()
    {
        // Cadena de conexión (DSN). Antes decía ";.charset." DENTRO de las comillas: PDO recibía
        // ese texto literal, ignoraba la constante y usaba el charset por defecto del servidor.
        // Ahora se concatena la constante charset de Config.php (debe ser "charset=utf8mb4").
        $pdo = "mysql:host=".host.";dbname=".db.";".charset;
        try {
            $this->conect = new PDO($pdo, user, pass);
            $this->conect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            echo "Error en la conexion".$e->getMessage();
        }
    }
    public function conect()
    {
        return $this->conect;
    }
}

?>