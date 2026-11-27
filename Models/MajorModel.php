<?php
/**
 * Modelo de la página pública (Major).
 * Permite que un cliente se registre por su cuenta para luego solicitar reservas.
 */
class MajorModel extends Query
{
    public function __construct()
    {
        parent::__construct();
    }

    /* =========================================================
     * REGLAS DE NEGOCIO
     * ========================================================= */

    /**
     * Registra un cliente desde la página pública.
     * El correo es su usuario para iniciar sesión, por eso no puede repetirse.
     */
    public function registrarCliente(array $d)
    {
        // 1. Campos obligatorios
        if (empty($d['nombre']) || empty($d['telefono']) || empty($d['correo']) || empty($d['clave']) || empty($d['direccion'])) {
            return array('msg' => 'Todo los campos son requeridos', 'icono' => 'error');
        }
        // 2. Correo válido y contraseña de al menos 6 caracteres
        if (filter_var($d['correo'], FILTER_VALIDATE_EMAIL) === false) {
            return array('msg' => 'Ingresa un correo valido', 'icono' => 'error');
        }
        if (strlen($d['clave']) < 6) {
            return array('msg' => 'La contraseña debe tener al menos 6 caracteres', 'icono' => 'error');
        }
        // 3. El correo no debe estar registrado
        if (!empty($this->verify('correo', $d['correo']))) {
            return array('msg' => 'Ya tienes una cuenta', 'icono' => 'error');
        }
        // 4. Guardar con la contraseña encriptada (misma forma que usa el login)
        $id = $this->register($d['nombre'], $d['correo'], $d['codphone'], $d['telefono'], $d['direccion'], hash("SHA256", $d['clave']));
        return ($id > 0)
            ? array('msg' => 'Registrado con éxito', 'icono' => 'success')
            : array('msg' => 'Error al registrarse', 'icono' => 'error');
    }

    /* =========================================================
     * ACCESO A DATOS
     * ========================================================= */

    // Busca un cliente por un campo (p. ej. 'correo') para saber si ya está registrado.
    // El valor va en el "?"; el nombre del campo solo se acepta si está en la lista blanca.
    public function verify($item, $valor)
    {
        if (!in_array($item, array('correo', 'telefono'))) {
            return false;
        }
        return $this->select("SELECT id FROM clientes WHERE $item = ?", array($valor));
    }

    public function register($nombre, $correo, $codphone, $telefono, $direccion, $clave)
    {
        $sql = "INSERT INTO clientes (nombre, correo, codphone, telefono, direccion, clave) VALUES (?,?,?,?,?,?)";
        return $this->insertar($sql, [$nombre, $correo, $codphone, $telefono, $direccion, $clave]);
    }
}
