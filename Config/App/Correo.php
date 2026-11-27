<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'vendor/autoload.php';

/**
 * Envío de correos con PHPMailer.
 * Reúne en un solo lugar la configuración SMTP (definida en Config/Config.php)
 * para que los modelos solo digan a quién, con qué asunto y qué mensaje.
 */
class Correo
{
    /**
     * Envía un correo. Devuelve true si se envió, false si hubo un error.
     * - $responderA: correo al que se contestará (p. ej. el visitante que escribió
     *   en Contacto). El remitente siempre es la cuenta SMTP de la cooperativa,
     *   porque el servidor de correo rechaza remitentes que no son suyos.
     * - $adjuntos: archivos generados en memoria, cada uno como
     *   array('contenido' => ..., 'nombre' => 'reserva.ics', 'tipo' => 'text/calendar').
     */
    public static function enviar($para, $nombrePara, $asunto, $cuerpoHtml, $nombreRemitente, $responderA = null, $nombreResponder = '', $adjuntos = array())
    {
        $mail = new PHPMailer(true);
        try {
            $mail->SMTPDebug  = 0;
            $mail->isSMTP();
            $mail->Host       = HOST_SMTP;
            $mail->SMTPAuth   = true;
            $mail->Username   = USER_SMTP;
            $mail->Password   = CLAVE_SMTP;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = PUERTO_SMTP;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(USER_SMTP, $nombreRemitente);
            $mail->addAddress($para, $nombrePara);
            if (!empty($responderA)) {
                $mail->addReplyTo($responderA, $nombreResponder);
            }

            foreach ($adjuntos as $adjunto) {
                $mail->addStringAttachment($adjunto['contenido'], $adjunto['nombre'], PHPMailer::ENCODING_BASE64, $adjunto['tipo']);
            }

            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body    = $cuerpoHtml;
            $mail->send();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
