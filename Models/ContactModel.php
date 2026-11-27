<?php
/**
 * Modelo de Contacto (página pública).
 * Un visitante escribe un mensaje y la cooperativa lo recibe por correo.
 */
class ContactModel extends Query
{
    public function __construct()
    {
        parent::__construct();
    }

    /* =========================================================
     * REGLAS DE NEGOCIO
     * ========================================================= */

    /**
     * Valida el mensaje del visitante y lo envía al correo de la cooperativa.
     */
    public function enviarMensaje(array $d)
    {
        // 1. Campos obligatorios y correo válido (para poder responderle)
        if (empty($d['nombre']) || empty($d['asunto']) || empty($d['correo']) || empty($d['mensaje'])) {
            return array('msg' => 'Todos los campos son requeridos', 'icono' => 'error');
        }
        if (filter_var($d['correo'], FILTER_VALIDATE_EMAIL) === false) {
            return array('msg' => 'Ingresa un correo valido', 'icono' => 'error');
        }

        // 2. Armar el correo. El texto del visitante se escapa para que no pueda
        //    insertar HTML (enlaces falsos, imágenes, etc.) en el correo.
        $empresa = $this->getEmpresa();
        $cuerpo = '<p><b>Nombre:</b> ' . htmlspecialchars($d['nombre']) . '</p>'
            . '<p><b>Correo:</b> ' . htmlspecialchars($d['correo']) . '</p>'
            . '<p>' . nl2br(htmlspecialchars($d['mensaje'])) . '</p>';

        // 3. Enviar: llega a la cooperativa y, al responder, se contesta al visitante
        $enviado = Correo::enviar($empresa['correo'], $empresa['nombre'], 'Contacto web: ' . $d['asunto'], $cuerpo,
            $empresa['nombre'], $d['correo'], $d['nombre']);
        return $enviado
            ? array('msg' => 'Mensaje enviado', 'icono' => 'success')
            : array('msg' => 'No se pudo enviar el mensaje, intenta más tarde', 'icono' => 'error');
    }

    /* =========================================================
     * ACCESO A DATOS
     * ========================================================= */

    public function getEmpresa()
    {
        $sql = "SELECT * FROM configuracion";
        return $this->select($sql);
    }
}
