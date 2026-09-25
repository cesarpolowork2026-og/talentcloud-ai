<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\User;

/**
 * "Calidad de la IA": solo el admin SaaS revisa en toda la plataforma los
 * resultados marcados como incorrectos y puede rehacer los análisis básicos.
 * (Marcar un resultado lo autoriza la Policy de la postulación.)
 */
class MatchFeedbackPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tieneRol(Rol::Admin);
    }

    public function rehacerAnalisis(User $user): bool
    {
        return $user->tieneRol(Rol::Admin);
    }
}
