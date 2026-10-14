<?php

namespace App\Support;

/**
 * Texto escrito por una persona (título de una vacante, nombre de una empresa o de alguien) dentro de una línea
 * de un correo. Las líneas de un `MailMessage` se interpretan como Markdown, así que `[clic](https://sitio-malo)`
 * llegaría como un enlace camuflado en un correo que sale de TalentCloud. Con `literal()` se muestra tal cual.
 *
 * El HTML ya lo escapa la plantilla del correo, por eso no se tocan `<`, `>` ni `&`.
 */
final class TextoDeCorreo
{
    public static function literal(string $texto): string
    {
        return (string) preg_replace('/([\\\\`*_{}\[\]()#+\-.!|])/', '\\\\$1', $texto);
    }
}
