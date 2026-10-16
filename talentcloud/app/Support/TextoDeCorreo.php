<?php

namespace App\Support;

/**
 * Texto escrito por una persona (título de vacante, nombre de empresa o de
 * alguien) dentro de una línea de correo. Las líneas de un `MailMessage` se
 * interpretan como Markdown, así que `[clic](https://sitio-malo)` llegaría como
 * un enlace camuflado. `literal()` lo muestra tal cual.
 *
 * El HTML ya lo escapa la plantilla, por eso no se tocan `<`, `>` ni `&`.
 */
final class TextoDeCorreo
{
    public static function literal(string $texto): string
    {
        return (string) preg_replace('/([\\\\`*_{}\[\]()#+\-.!|])/', '\\\\$1', $texto);
    }
}
