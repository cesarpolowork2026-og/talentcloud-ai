<?php

namespace App\Support;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Barra de navegación por rol, tal como la define el prototipo
 * (TalentCloud App.dc.html): el postulante ve "Mis postulaciones" (y "Vacantes",
 * ampliación sobre el prototipo); el personal
 * de selección ve Dashboard…Evaluaciones; el admin añade "Empresas" y la
 * empresa "Mi empresa".
 */
class Navegacion
{
    /**
     * @return list<array{etiqueta: string, ruta: string, patron: string}>
     */
    public static function enlaces(?User $user): array
    {
        $rol = $user?->rol();

        if ($rol === null) {
            return [];
        }

        $enlaces = [];

        if ($rol === Rol::Postulante) {
            $enlaces[] = self::enlace('Mis postulaciones', 'postulante.postulaciones', 'postulante.postulaciones');
            $enlaces[] = self::enlace('Vacantes', 'postulante.vacantes', 'postulante.vacantes*');
        }

        if ($rol->esPersonalDeSeleccion()) {
            $enlaces[] = self::enlace('Dashboard', 'dashboard', 'dashboard');
            $enlaces[] = self::enlace('Vacantes', 'vacantes.index', 'vacantes.*');
            $enlaces[] = self::enlace('Kanban', 'kanban.index', 'kanban.*');
            $enlaces[] = self::enlace('Candidatos', 'candidatos.index', 'candidatos.*');
            $enlaces[] = self::enlace('Matching', 'matching.index', 'matching.*');
            $enlaces[] = self::enlace('Entrevistas', 'entrevistas.index', 'entrevistas.*');
            $enlaces[] = self::enlace('Evaluaciones', 'evaluaciones.index', 'evaluaciones.*');
        }

        if ($rol === Rol::Admin) {
            $enlaces[] = self::enlace('Empresas', 'empresas.index', 'empresas.*');
        }

        if ($rol === Rol::Empresa) {
            $enlaces[] = self::enlace('Mi empresa', 'mi-empresa', 'mi-empresa*');
        }

        return $enlaces;
    }

    /** Pantalla de inicio de cada rol ("Volver a mi panel"). */
    public static function rutaInicio(?User $user): string
    {
        if ($user?->rol() === Rol::Postulante && Route::has('postulante.postulaciones')) {
            return route('postulante.postulaciones');
        }

        return $user ? route('dashboard') : route('login');
    }

    /** Texto del menú de perfil: "Reclutador · Nimbus Corp", "Postulante · Marco Díaz"… */
    public static function etiquetaPerfil(User $user): string
    {
        $rol = $user->rol();

        if ($rol === null) {
            return $user->name;
        }

        return match ($rol) {
            Rol::Admin => $rol->etiquetaPerfil(),
            Rol::Postulante => $rol->etiquetaPerfil().' · '.$user->name,
            Rol::Empresa, Rol::Reclutador => $user->empresa
                ? $rol->etiquetaPerfil().' · '.$user->empresa->nombre
                : $rol->etiquetaPerfil(),
        };
    }

    /** Inicial del avatar: A, E, R o la inicial del postulante. */
    public static function inicial(User $user): string
    {
        return match ($user->rol()) {
            Rol::Admin => 'A',
            Rol::Empresa => 'E',
            Rol::Reclutador => 'R',
            default => mb_strtoupper(mb_substr($user->name, 0, 1)),
        };
    }

    /**
     * @return array{etiqueta: string, ruta: string, patron: string}
     */
    private static function enlace(string $etiqueta, string $ruta, string $patron): array
    {
        return ['etiqueta' => $etiqueta, 'ruta' => $ruta, 'patron' => $patron];
    }
}
