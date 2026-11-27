<?php
/**
 * Modelo de Clientes de la cooperativa.
 * Un cliente puede ser registrado por el personal (con su DNI) o registrarse
 * él mismo desde la página pública (con correo y contraseña).
 * Estados: 1 = Activo, 0 = Dado de baja.
 */
class ClientesModel extends Query
{
    public function __construct()
    {
        parent::__construct();
    }

    /* =========================================================
     * REGLAS DE NEGOCIO
     * ========================================================= */

    /**
     * Registra un cliente nuevo (si 'id' viene vacío) o modifica uno existente.
     * Lo usa el personal desde el módulo Clientes.
     */
    public function guardar(array $d)
    {
        // 1. Campos obligatorios
        if (empty($d['dni']) || empty($d['nombre']) || empty($d['telefono']) || empty($d['direccion'])) {
            return array('msg' => 'Todo los campos son obligatorios', 'icono' => 'warning');
        }

        // 2. El DNI identifica al cliente: no puede repetirse
        $id = ($d['id'] == '') ? 0 : intval($d['id']);
        if ($this->existeCampo('dni', $d['dni'], $id)) {
            return array('msg' => 'Ya existe un cliente con ese DNI', 'icono' => 'warning');
        }

        // 3. Guardar
        if ($id > 0) {
            $ok = $this->modificarCliente($d['dni'], $d['nombre'], $d['telefono'], $d['direccion'], $id);
            return ($ok == 1)
                ? array('msg' => 'Cliente modificado', 'icono' => 'success')
                : array('msg' => 'Error al modificar el cliente', 'icono' => 'error');
        }
        $ok = $this->registrarCliente($d['dni'], $d['nombre'], $d['telefono'], $d['direccion']);
        return ($ok == 1)
            ? array('msg' => 'Cliente registrado con éxito', 'icono' => 'success')
            : array('msg' => 'Error al registrar el cliente', 'icono' => 'error');
    }

    // Da de baja a un cliente (estado 0)
    public function darDeBaja(int $id)
    {
        return ($this->accionCli(0, $id) == 1)
            ? array('msg' => 'Cliente dado de baja', 'icono' => 'success')
            : array('msg' => 'Error al eliminar el cliente', 'icono' => 'error');
    }

    /**
     * Cambia la contraseña del cliente: la actual debe ser correcta y la nueva debe confirmarse.
     */
    public function cambiarClave(int $id, $actual, $nueva, $confirmar)
    {
        if (empty($actual) || empty($nueva) || empty($confirmar)) {
            return array('msg' => 'Todo los campos son obligatorios', 'icono' => 'warning');
        }
        if ($nueva != $confirmar) {
            return array('msg' => 'Las contraseñas no coinciden', 'icono' => 'warning');
        }
        if ($this->getClave($id) != $this->encriptar($actual)) {
            return array('msg' => 'La contraseña actual es incorrecta', 'icono' => 'warning');
        }
        return ($this->modificarPass($this->encriptar($nueva), $id) == 1)
            ? array('msg' => 'Contraseña modificada con éxito', 'icono' => 'success')
            : array('msg' => 'Error al modificar la contraseña', 'icono' => 'error');
    }

    /**
     * El cliente actualiza sus propios datos desde su perfil.
     */
    public function actualizarPerfil(int $id, array $d)
    {
        if (empty($d['dni']) || empty($d['nombre']) || empty($d['correo']) || empty($d['telefono']) || empty($d['direccion'])) {
            return array('msg' => 'Todo los campos son obligatorios', 'icono' => 'warning');
        }
        if (filter_var($d['correo'], FILTER_VALIDATE_EMAIL) === false) {
            return array('msg' => 'Ingresa un correo valido', 'icono' => 'warning');
        }
        // El correo sirve para iniciar sesión y el DNI identifica al cliente: no pueden ser de otro
        if ($this->existeCampo('correo', $d['correo'], $id)) {
            return array('msg' => 'Ese correo ya está registrado', 'icono' => 'warning');
        }
        if ($this->existeCampo('dni', $d['dni'], $id)) {
            return array('msg' => 'Ya existe un cliente con ese DNI', 'icono' => 'warning');
        }
        return ($this->modificarDato($d['nombre'], $d['dni'], $d['correo'], $d['telefono'], $d['direccion'], $id) == 1)
            ? array('msg' => 'Perfil modificado con éxito', 'icono' => 'success')
            : array('msg' => 'Error al modificar', 'icono' => 'error');
    }

    /**
     * Sugerencias para el buscador de clientes (autocompletar del módulo Servicios).
     */
    public function sugerencias(string $texto)
    {
        $lista = array();
        foreach ($this->buscarCliente($texto) as $row) {
            $lista[] = array(
                'id'    => $row['id'],
                'label' => $row['nombre'] . ' - ' . $row['direccion'],
                'value' => $row['nombre']
            );
        }
        return $lista;
    }

    // Misma forma de encriptar que usa el login (LoginModel)
    private function encriptar($clave)
    {
        return hash("SHA256", $clave);
    }

    /* =========================================================
     * ACCESO A DATOS
     * ========================================================= */

    public function getClientes(int $estado)
    {
        $sql = "SELECT id, dni, nombre, correo, telefono, direccion, fecha, estado FROM clientes WHERE estado = ?";
        return $this->selectAll($sql, array($estado));
    }

    public function buscarCliente(string $valor)
    {
        // Consulta preparada: el texto escrito va en el "?" y nunca se mezcla con el SQL.
        // Los % (cualquier texto antes/después) se añaden al valor, no a la consulta.
        $sql = "SELECT id, nombre, direccion FROM clientes WHERE nombre LIKE ? AND estado = 1";
        return $this->selectAll($sql, array('%' . $valor . '%'));
    }

    // Datos del cliente SIN la contraseña (para formularios y para el perfil)
    public function getCliente(int $id)
    {
        $sql = "SELECT id, dni, nombre, correo, codphone, telefono, direccion, fecha, estado FROM clientes WHERE id = ?";
        return $this->select($sql, array($id));
    }

    // La contraseña encriptada solo se lee aquí, para compararla
    private function getClave(int $id)
    {
        $data = $this->select("SELECT clave FROM clientes WHERE id = ?", array($id));
        return empty($data) ? '' : $data['clave'];
    }

    // ¿Hay OTRO cliente (distinto de $id) con ese valor? $campo solo puede ser 'dni' o 'correo'.
    private function existeCampo($campo, $valor, int $id)
    {
        if (!in_array($campo, array('dni', 'correo'))) {
            return false;
        }
        $sql = "SELECT id FROM clientes WHERE $campo = ? AND id != ?";
        return !empty($this->select($sql, array($valor, $id)));
    }

    public function registrarCliente(string $dni, string $nombre, string $telefono, string $direccion)
    {
        $sql = "INSERT INTO clientes(dni, nombre, telefono, direccion) VALUES (?,?,?,?)";
        return $this->save($sql, array($dni, $nombre, $telefono, $direccion));
    }

    public function modificarCliente(string $dni, string $nombre, string $telefono, string $direccion, int $id)
    {
        $sql = "UPDATE clientes SET dni = ?, nombre = ?, telefono = ?, direccion = ? WHERE id = ?";
        return $this->save($sql, array($dni, $nombre, $telefono, $direccion, $id));
    }

    public function accionCli(int $estado, int $id)
    {
        $sql = "UPDATE clientes SET estado = ? WHERE id = ?";
        return $this->save($sql, array($estado, $id));
    }

    public function modificarPass(string $clave, int $id)
    {
        $sql = "UPDATE clientes SET clave = ? WHERE id = ?";
        return $this->save($sql, array($clave, $id));
    }

    public function modificarDato(string $nombre, string $dni, string $correo, string $tel, string $dir, int $id)
    {
        $sql = "UPDATE clientes SET nombre=?, dni=?, correo=?, telefono=?, direccion=? WHERE id=?";
        return $this->save($sql, array($nombre, $dni, $correo, $tel, $dir, $id));
    }
}
