<?php

namespace App\Support;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Barra de navegación por rol, a partir de la del prototipo
 * (TalentCloud App.dc.html): el postulante ve "Mis postulaciones" (y "Vacantes",
 * ampliación sobre el prototipo); el reclutador y la empresa ven
 * Dashboard…Evaluaciones, y la empresa añade "Mi empresa".
 *
 * El admin SaaS (cambio sobre el prototipo) ve primero lo suyo —Panel,
 * Empresas, Calidad de la IA— y las seis pantallas de selección agrupadas en
 * "Procesos de selección", porque para él son supervisión, no su trabajo diario.
 */
class Navegacion
{
    /**
     * Un enlace lleva su ruta; un grupo, además, sus enlaces en "hijos" y una
     * descripción para el encabezado del desplegable.
     *
     * @return list<array{etiqueta: string, ruta: string, patron: string, hijos?: list<array{etiqueta: string, ruta: string, patron: string}>, descripcion?: string}>
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

        if ($rol === Rol::Admin) {
            return [
                self::enlace('Panel', 'dashboard', 'dashboard'),
                self::enlace('Empresas', 'empresas.index', 'empresas.*'),
                self::enlace('Calidad de la IA', 'calidad-ia', 'calidad-ia'),
                self::grupo('Procesos de selección', 'Supervisión de las empresas', self::pantallasDeSeleccion()),
            ];
        }

        if ($rol->esPersonalDeSeleccion()) {
            $enlaces[] = self::enlace('Dashboard', 'dashboard', 'dashboard');
            array_push($enlaces, ...self::pantallasDeSeleccion());
        }

        return $enlaces;
    }

    /**
     * Administración de la cuenta, separada del proceso de selección: va a la
     * derecha de la barra, junto a la campana y el perfil (no es un paso más
     * del flujo de selección).
     *
     * @return list<array{etiqueta: string, ruta: string, patron: string}>
     */
    public static function administracion(?User $user): array
    {
        return $user?->rol() === Rol::Empresa
            ? [self::enlace('Mi empresa', 'mi-empresa', 'mi-empresa*')]
            : [];
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
     * Las pantallas del proceso de selección, en el orden del flujo: lo que
     * entra al proceso (vacantes y candidatos) y lo que se hace con cada
     * candidato (Kanban, matching, entrevistas y evaluaciones).
     *
     * @return list<array{etiqueta: string, ruta: string, patron: string}>
     */
    private static function pantallasDeSeleccion(): array
    {
        return [
            self::enlace('Vacantes', 'vacantes.index', 'vacantes.*'),
            self::enlace('Candidatos', 'candidatos.index', 'candidatos.*'),
            self::enlace('Kanban', 'kanban.index', 'kanban.*'),
            self::enlace('Matching', 'matching.index', 'matching.*'),
            self::enlace('Entrevistas', 'entrevistas.index', 'entrevistas.*'),
            self::enlace('Evaluaciones', 'evaluaciones.index', 'evaluaciones.*'),
        ];
    }

    /**
     * @return array{etiqueta: string, ruta: string, patron: string}
     */
    private static function enlace(string $etiqueta, string $ruta, string $patron): array
    {
        return ['etiqueta' => $etiqueta, 'ruta' => $ruta, 'patron' => $patron];
    }

    /**
     * Grupo de enlaces (desplegable en escritorio, bloque con título en móvil).
     * Está activo si lo está cualquiera de sus enlaces.
     *
     * @param  list<array{etiqueta: string, ruta: string, patron: string}>  $hijos
     * @return array{etiqueta: string, ruta: string, patron: string, hijos: list<array{etiqueta: string, ruta: string, patron: string}>, descripcion: string}
     */
    private static function grupo(string $etiqueta, string $descripcion, array $hijos): array
    {
        return [
            'etiqueta' => $etiqueta,
            'ruta' => '',
            'patron' => '',
            'hijos' => $hijos,
            'descripcion' => $descripcion,
        ];
    }
}
