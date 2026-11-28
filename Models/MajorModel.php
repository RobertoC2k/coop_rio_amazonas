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
        if (empty($d['cedula']) || empty($d['nombre']) || empty($d['telefono']) || empty($d['correo']) || empty($d['clave']) || empty($d['direccion'])) {
            return array('msg' => 'Todos los campos son requeridos', 'icono' => 'error');
        }
        // La cédula identifica al cliente (igual que en el módulo Clientes del personal)
        if (!ctype_digit($d['cedula']) || strlen($d['cedula']) != 10) {
            return array('msg' => 'La cédula debe tener 10 dígitos', 'icono' => 'error');
        }
        // 2. Correo válido y contraseña de al menos 6 caracteres
        if (filter_var($d['correo'], FILTER_VALIDATE_EMAIL) === false) {
            return array('msg' => 'Ingresa un correo valido', 'icono' => 'error');
        }
        if (mb_strlen($d['clave']) < 6) {
            return array('msg' => 'La contraseña debe tener al menos 6 caracteres', 'icono' => 'error');
        }
        // 3. El correo no debe estar registrado (es el usuario para iniciar sesión)
        if (!empty($this->verify('correo', $d['correo']))) {
            return array('msg' => 'Ya tienes una cuenta', 'icono' => 'error');
        }
        $clave = hash("SHA256", $d['clave']);   // misma forma que usa el login

        // 4. ¿La cédula ya existe? Evita el cliente duplicado cuando el personal ya lo había
        //    registrado en el módulo Clientes (sin correo ni clave, porque ese formulario no los pide).
        $existente = $this->getClientePorCedula($d['cedula']);
        if (!empty($existente)) {
            if (!empty($existente['correo'])) {
                return array('msg' => 'Ya tienes una cuenta con esa cédula. Inicia sesión', 'icono' => 'error');
            }
            // Para unir se exige que el teléfono también coincida: así nadie puede quedarse
            // con el registro de otra persona solo por conocer su cédula.
            if (!$this->mismoTelefono($existente['telefono'], $d['telefono'])) {
                return array('msg' => 'Esa cédula ya está registrada en la cooperativa. Comunícate con nosotros para activar tu cuenta', 'icono' => 'error');
            }
            $ok = $this->completarRegistro($existente['id'], $d['nombre'], $d['correo'], $d['codphone'], $d['telefono'], $d['direccion'], $clave);
            return ($ok == 1)
                ? array('msg' => 'Registrado con éxito: tu cuenta quedó unida a tu registro en la cooperativa', 'icono' => 'success')
                : array('msg' => 'Error al registrarse', 'icono' => 'error');
        }

        // 5. Cliente nuevo
        $id = $this->register($d['cedula'], $d['nombre'], $d['correo'], $d['codphone'], $d['telefono'], $d['direccion'], $clave);
        return ($id > 0)
            ? array('msg' => 'Registrado con éxito', 'icono' => 'success')
            : array('msg' => 'Error al registrarse', 'icono' => 'error');
    }

    /**
     * Compara dos teléfonos sin importar el formato: solo cuentan los últimos 9 dígitos.
     * Así "0984378505" (como lo escribe el personal) y "984378505" con +593 (como llega
     * del formulario web con selector de país) se reconocen como el mismo número.
     */
    public function mismoTelefono($a, $b)
    {
        $a = substr(preg_replace('/\D/', '', (string) $a), -9);
        $b = substr(preg_replace('/\D/', '', (string) $b), -9);
        return strlen($a) == 9 && $a === $b;
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

    public function register($cedula, $nombre, $correo, $codphone, $telefono, $direccion, $clave)
    {
        $sql = "INSERT INTO clientes (cedula, nombre, correo, codphone, telefono, direccion, clave) VALUES (?,?,?,?,?,?,?)";
        return $this->insertar($sql, [$cedula, $nombre, $correo, $codphone, $telefono, $direccion, $clave]);
    }

    public function getClientePorCedula($cedula)
    {
        return $this->select("SELECT id, correo, telefono FROM clientes WHERE cedula = ?", array($cedula));
    }

    // Completa el registro que creó el personal con los datos de acceso a la web
    public function completarRegistro($id, $nombre, $correo, $codphone, $telefono, $direccion, $clave)
    {
        $sql = "UPDATE clientes SET nombre = ?, correo = ?, codphone = ?, telefono = ?, direccion = ?, clave = ? WHERE id = ?";
        return $this->save($sql, array($nombre, $correo, $codphone, $telefono, $direccion, $clave, $id));
    }
}
