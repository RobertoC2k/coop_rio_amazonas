<?php
class MajorModel extends Query{
    public function __construct()
    {
        parent::__construct();
    }
    // Busca un cliente por un campo (p. ej. 'correo') para saber si ya está registrado.
    // El valor va en el "?"; el nombre del campo solo se acepta si está en la lista blanca.
    public function verify($item, $valor) {
        if (!in_array($item, array('correo', 'telefono'))) {
            return false;
        }
        return $this->select("SELECT * FROM clientes WHERE $item = ?", array($valor));
    }

    public function register($nombre, $correo, $codphone, $telefono, $direccion, $clave)
    {
        $sql = "INSERT INTO clientes (nombre, correo, codphone, telefono, direccion, clave) VALUES (?,?,?,?,?,?)";
        return $this->insertar($sql , [$nombre, $correo, $codphone, $telefono, $direccion, $clave]);
    }
}

?>