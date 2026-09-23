<?php

namespace App\Enums;

/**
 * Roles de TalentCloud AI. El valor es el nombre del rol en spatie/laravel-permission.
 */
enum Rol: string
{
    case Admin = 'admin';
    case Empresa = 'empresa';
    case Reclutador = 'reclutador';
    case Postulante = 'postulante';

    /** Etiqueta corta del selector "Ingresar como" del login. */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Admin => 'Admin SaaS',
            self::Empresa => 'Empresa',
            self::Reclutador => 'Reclutador',
            self::Postulante => 'Postulante',
        };
    }

    /** Etiqueta del menú de perfil de la barra de navegación. */
    public function etiquetaPerfil(): string
    {
        return match ($this) {
            self::Admin => 'Administrador SaaS',
            default => $this->etiqueta(),
        };
    }

    /** Admin, empresa y reclutador gestionan procesos de selección; el postulante no. */
    public function esPersonalDeSeleccion(): bool
    {
        return $this !== self::Postulante;
    }

    /** @return array<string, string> valor => etiqueta, en el orden del login. */
    public static function opciones(): array
    {
        $opciones = [];
        foreach (self::cases() as $rol) {
            $opciones[$rol->value] = $rol->etiqueta();
        }

        return $opciones;
    }
}
