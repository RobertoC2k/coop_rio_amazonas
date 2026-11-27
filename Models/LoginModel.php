<?php
class LoginModel extends Query{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Regla de negocio del inicio de sesión.
     * Devuelve un arreglo con:
     *  - 'msg' y 'icono': la respuesta que verá el usuario.
     *  - 'sesion': los datos a guardar en $_SESSION (null si no pudo entrar).
     * El perfil (personal o cliente) lo detecta el sistema; el usuario no lo elige.
     */
    public function autenticar($usuario, $clave)
    {
        // 1. Validar campos obligatorios
        if (empty($usuario) || empty($clave)) {
            return array('msg' => 'Todos los campos son requeridos', 'icono' => 'error', 'sesion' => null);
        }

        // 2 y 3. Cifrar la clave y detectar el perfil: primero se busca en el personal (tipo 1),
        //    si no existe ahí, se busca en los clientes (tipo 2).
        //    Se prueba cada hash posible (ver hashesClave en Config/Helpers.php) para que
        //    las cuentas creadas antes del cambio de strClean sigan entrando.
        $data = null;
        foreach (hashesClave($clave) as $hash) {
            $tipo = 1;
            $data = $this->verificarUsuario($usuario, $hash);
            if (empty($data)) {
                $tipo = 2;
                $data = $this->verificarCliente($usuario, $hash);
            }
            if (!empty($data)) {
                break;
            }
        }

        if (empty($data)) {
            return array('msg' => 'Usuario o contraseña incorrecta', 'icono' => 'error', 'sesion' => null);
        }

        // 4. Armar los datos de la sesión (los clientes no tienen foto de perfil)
        $sesion = array(
            'id_usuario' => $data['id'],
            'nombre'     => $data['nombre'],
            'correo'     => $data['correo'],
            'tipo'       => $tipo,
            'perfil'     => ($tipo == 1) ? $data['perfil'] : 'default.png'
        );
        return array('msg' => 'Iniciando sesion', 'icono' => 'success', 'sesion' => $sesion);
    }

    // El personal puede entrar con su usuario o con su correo
    private function verificarUsuario($usuario, $hash)
    {
        $sql = "SELECT * FROM usuarios WHERE (usuario = ? OR correo = ?) AND clave = ? AND estado = 1";
        return $this->select($sql, array($usuario, $usuario, $hash));
    }

    // Los clientes entran con su correo
    private function verificarCliente($correo, $hash)
    {
        $sql = "SELECT * FROM clientes WHERE correo = ? AND clave = ? AND estado = 1";
        return $this->select($sql, array($correo, $hash));
    }
}

?>
