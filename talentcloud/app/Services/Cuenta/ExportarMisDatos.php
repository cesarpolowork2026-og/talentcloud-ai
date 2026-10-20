<?php

namespace App\Services\Cuenta;

use App\Enums\EstadoDeMiPostulacion;
use App\Models\Cv;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Models\User;
use App\Repositories\Contracts\EntrevistaRepository;
use App\Tenancy\EmpresaScope;

/**
 * "Descargar mis datos" del postulante (derecho de acceso): su cuenta, su ficha
 * de candidato, sus CV (metadatos), sus postulaciones y entrevistas tal como él
 * las ve. Nunca incluye lo que es de cada empresa: notas, evaluaciones ni competencias
 * añadidas a mano por una empresa.
 */
class ExportarMisDatos
{
    public function __construct(private readonly EntrevistaRepository $entrevistas) {}

    /**
     * @return array<string, mixed>
     */
    public function de(User $user): array
    {
        $candidato = $user->candidato;

        $datos = [
            'generado_el' => now()->toIso8601String(),
            'cuenta' => [
                'nombre' => $user->name,
                'email' => $user->email,
                'email_verificado' => $user->email_verified_at !== null,
                'avisos_por_correo' => $user->notificaciones_email,
                'creada_el' => $user->created_at?->toIso8601String(),
            ],
        ];

        if ($candidato === null) {
            return $datos;
        }

        $postulaciones = $candidato->postulacionesEnTodasLasEmpresas()
            ->with(['vacante' => fn ($q) => $q->withoutGlobalScope(EmpresaScope::class)->with('empresa:id,nombre')])
            ->oldest()
            ->get();

        $entrevistas = $this->entrevistas->delCandidato($candidato)->groupBy('postulacion_id');

        return $datos + [
            'ficha_de_candidato' => [
                'nombre' => $candidato->nombre,
                'email' => $candidato->email,
                'telefono' => $candidato->telefono,
                'titular' => $candidato->titular,
                'consentimiento_el' => $candidato->consentimiento_at?->toIso8601String(),
            ],
            'cvs' => $candidato->cvs()->oldest()->get()->map(fn (Cv $cv) => [
                'archivo' => $cv->nombre_original,
                'tipo' => $cv->mime,
                'tamano_bytes' => $cv->tamano,
                'estado' => $cv->estado_procesamiento->etiqueta(),
                'subido_el' => $cv->created_at?->toIso8601String(),
            ])->all(),
            'postulaciones' => $postulaciones->map(fn (Postulacion $p) => [
                'vacante' => $p->vacante?->titulo,
                'empresa' => $p->vacante?->empresa?->nombre,
                'estado' => EstadoDeMiPostulacion::de($p)->etiqueta(),
                'postulada_el' => $p->created_at->toIso8601String(),
                'retirada_el' => $p->retirada_at?->toIso8601String(),
                'entrevistas' => $entrevistas->get($p->id, collect())->map(fn (Entrevista $e) => [
                    'fecha' => $e->inicia_at->toIso8601String(),
                    'duracion_min' => $e->duracion_min,
                    'modalidad' => $e->modalidad->etiqueta(),
                    'estado' => $e->estado->etiqueta(),
                ])->values()->all(),
            ])->all(),
        ];
    }
}
