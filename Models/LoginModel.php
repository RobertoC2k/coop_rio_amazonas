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

        // 2. Cifrar la clave igual que cuando se registró
        $hash = hash("SHA256", $clave);

        // 3. Detectar el perfil: primero se busca en el personal (tipo 1),
        //    si no existe ahí, se busca en los clientes (tipo 2)
        $tipo = 1;
        $data = $this->verificarUsuario($usuario, $hash);
        if (empty($data)) {
            $tipo = 2;
            $data = $this->verificarCliente($usuario, $hash);
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
