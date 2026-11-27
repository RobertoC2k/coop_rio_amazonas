<?php
/**
 * Modelo de Usuarios (personal administrativo de la cooperativa).
 * Estados: 1 = Activo, 0 = Dado de baja.
 */
class UsuariosModel extends Query
{
    public function __construct()
    {
        parent::__construct();
    }

    /* =========================================================
     * REGLAS DE NEGOCIO
     * ========================================================= */

    /**
     * Registra un usuario nuevo (si 'id' viene vacío) o modifica uno existente.
     * Devuelve la respuesta para la vista ('msg' e 'icono').
     */
    public function guardar(array $d)
    {
        // 1. Campos obligatorios y correo válido
        if (empty($d['usuario']) || empty($d['nombre']) || empty($d['correo']) || empty($d['telefono'])) {
            return array('msg' => 'Todo los campos son obligatorios', 'icono' => 'warning');
        }
        if (!$this->esCorreoValido($d['correo'])) {
            return array('msg' => 'Ingresa un correo valido', 'icono' => 'warning');
        }

        // 2. El usuario y el correo no pueden pertenecer a otra persona
        $id = ($d['id'] == '') ? 0 : intval($d['id']);
        if ($this->existeUsuario($d['usuario'], $d['correo'], $id)) {
            return array('msg' => 'El usuario o correo ya existe', 'icono' => 'warning');
        }

        // 3a. Modificar (la contraseña no se cambia desde aquí)
        if ($id > 0) {
            $ok = $this->modificarUsuario($d['usuario'], $d['nombre'], $d['correo'], $d['telefono'], $id);
            return ($ok == 1)
                ? array('msg' => 'Usuario modificado con éxito', 'icono' => 'success')
                : array('msg' => 'Error al modificar el usuario', 'icono' => 'error');
        }

        // 3b. Registrar: la contraseña es obligatoria y debe confirmarse
        if (empty($d['clave']) || empty($d['confirmar'])) {
            return array('msg' => 'La contraseña es requerida', 'icono' => 'warning');
        }
        if ($d['clave'] != $d['confirmar']) {
            return array('msg' => 'Las contraseñas no coinciden', 'icono' => 'warning');
        }
        $ok = $this->registrarUsuario($d['usuario'], $d['nombre'], $d['correo'], $d['telefono'], $this->encriptar($d['clave']));
        return ($ok == 1)
            ? array('msg' => 'Usuario registrado con éxito', 'icono' => 'success')
            : array('msg' => 'Error al registrar el usuario', 'icono' => 'error');
    }

    /**
     * Da de baja a un usuario (estado 0). Un usuario no puede darse de baja a sí mismo,
     * porque se quedaría sin acceso en plena sesión.
     */
    public function darDeBaja(int $id, int $idSesion)
    {
        if ($id == $idSesion) {
            return array('msg' => 'No puedes darte de baja a ti mismo', 'icono' => 'warning');
        }
        return ($this->accionUser(0, $id) == 1)
            ? array('msg' => 'Usuario dado de baja', 'icono' => 'success')
            : array('msg' => 'Error al eliminar el usuario', 'icono' => 'error');
    }

    /**
     * Cambia la contraseña: la actual debe ser correcta y la nueva debe confirmarse.
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
     * Actualiza los datos del perfil y, si se subió, la foto.
     * $archivo es el elemento de $_FILES (puede venir sin nombre si no se eligió foto).
     */
    public function actualizarPerfil(int $id, array $d, $archivo)
    {
        // 1. Campos obligatorios y correo válido
        if (
            empty($d['usuario']) || empty($d['nombre']) || empty($d['apellido'])
            || empty($d['correo']) || empty($d['telefono']) || empty($d['direccion'])
        ) {
            return array('msg' => 'Todo los campos son obligatorios', 'icono' => 'warning');
        }
        if (!$this->esCorreoValido($d['correo'])) {
            return array('msg' => 'Ingresa un correo valido', 'icono' => 'warning');
        }
        if ($this->existeUsuario($d['usuario'], $d['correo'], $id)) {
            return array('msg' => 'El usuario o correo ya existe', 'icono' => 'warning');
        }

        // 2. Foto: solo png, jpg o jpeg. Si no se sube, se conserva la actual.
        $actual = $this->getUsuario($id);
        $fotoNueva = !empty($archivo['name']);
        $foto = $actual['perfil'];
        if ($fotoNueva) {
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, array('png', 'jpeg', 'jpg'))) {
                return array('msg' => 'Archivo no permitido', 'icono' => 'warning');
            }
            $foto = date("YmdHis") . '.' . $extension;
        }

        // 3. Guardar en la base de datos
        $ok = $this->modificarDato($d['usuario'], $d['nombre'], $d['apellido'], $d['correo'], $d['telefono'], $d['direccion'], $foto, $id);
        if ($ok != 1) {
            return array('msg' => 'Error al modificar el usuario', 'icono' => 'error');
        }

        // 4. Solo si se guardó: subir la foto nueva y borrar la anterior (excepto el avatar por defecto)
        if ($fotoNueva) {
            move_uploaded_file($archivo['tmp_name'], 'Assets/img/users/' . $foto);
            $anterior = 'Assets/img/users/' . $actual['perfil'];
            if ($actual['perfil'] != 'avatar.svg' && file_exists($anterior)) {
                unlink($anterior);
            }
        }
        return array('msg' => 'Usuario modificado con éxito', 'icono' => 'success');
    }

    // Misma forma de encriptar que usa el login (LoginModel)
    private function encriptar($clave)
    {
        return hash("SHA256", $clave);
    }

    private function esCorreoValido($correo)
    {
        return filter_var($correo, FILTER_VALIDATE_EMAIL) !== false;
    }

    /* =========================================================
     * ACCESO A DATOS
     * ========================================================= */

    public function getEmpresa()
    {
        $sql = "SELECT * FROM configuracion";
        return $this->select($sql);
    }

    public function getUsuarios(int $estado)
    {
        $sql = "SELECT id, usuario, nombre, correo, estado FROM usuarios WHERE estado = ?";
        return $this->selectAll($sql, array($estado));
    }

    // Datos del usuario SIN la contraseña (para formularios y para el perfil)
    public function getUsuario(int $id)
    {
        $sql = "SELECT id, usuario, nombre, apellido, correo, telefono, direccion, perfil, fecha, estado FROM usuarios WHERE id = ?";
        return $this->select($sql, array($id));
    }

    // La contraseña encriptada solo se lee aquí, para compararla
    private function getClave(int $id)
    {
        $data = $this->select("SELECT clave FROM usuarios WHERE id = ?", array($id));
        return empty($data) ? '' : $data['clave'];
    }

    // ¿Hay OTRO usuario (distinto de $id) con ese usuario o correo?
    private function existeUsuario($usuario, $correo, int $id)
    {
        $sql = "SELECT id FROM usuarios WHERE (usuario = ? OR correo = ?) AND id != ?";
        return !empty($this->select($sql, array($usuario, $correo, $id)));
    }

    public function registrarUsuario(string $usuario, string $nombre, string $correo, string $telefono, string $clave)
    {
        $sql = "INSERT INTO usuarios(usuario, nombre, correo, telefono, clave) VALUES (?,?,?,?,?)";
        return $this->save($sql, array($usuario, $nombre, $correo, $telefono, $clave));
    }

    public function modificarUsuario(string $usuario, string $nombre, string $correo, string $tele, int $id)
    {
        $sql = "UPDATE usuarios SET usuario = ?, nombre = ?, correo = ?, telefono = ? WHERE id = ?";
        return $this->save($sql, array($usuario, $nombre, $correo, $tele, $id));
    }

    public function accionUser(int $estado, int $id)
    {
        $sql = "UPDATE usuarios SET estado = ? WHERE id = ?";
        return $this->save($sql, array($estado, $id));
    }

    public function modificarPass(string $clave, int $id)
    {
        $sql = "UPDATE usuarios SET clave = ? WHERE id = ?";
        return $this->save($sql, array($clave, $id));
    }

    public function modificarDato(string $usuario, string $nombre, string $apellido, string $correo, string $tel, string $dir, string $img, int $id)
    {
        $sql = "UPDATE usuarios SET usuario=?, nombre=?, apellido=?, correo=?, telefono=?, direccion=?, perfil=? WHERE id=?";
        return $this->save($sql, array($usuario, $nombre, $apellido, $correo, $tel, $dir, $img, $id));
    }
}
