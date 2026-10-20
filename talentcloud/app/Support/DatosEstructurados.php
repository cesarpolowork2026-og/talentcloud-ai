<?php

namespace App\Support;

use App\Enums\Modalidad;
use App\Enums\TipoRequisito;
use App\Models\Vacante;

/**
 * Datos estructurados (JSON-LD de schema.org) de las páginas públicas: la oferta de empleo de cada vacante abierta
 * (la que Google for Jobs lee) y la organización y el sitio de la portada. Solo lo que la aplicación sabe de verdad:
 * si la vacante no tiene sueldo ni fecha de cierre, esos campos no se inventan.
 */
final class DatosEstructurados
{
    /** @return array<string, mixed> */
    public static function ofertaDeEmpleo(Vacante $vacante): array
    {
        $empresa = $vacante->empresa;
        $lugar = $vacante->modalidad->normalizarUbicacion($vacante->ubicacion);

        $oferta = [
            '@context' => 'https://schema.org',
            '@type' => 'JobPosting',
            'title' => $vacante->titulo,
            'description' => self::descripcion($vacante),
            'datePosted' => ($vacante->publicada_at ?? $vacante->created_at)->toIso8601String(),
            'identifier' => ['@type' => 'PropertyValue', 'name' => (string) $empresa?->nombre, 'value' => (string) $vacante->id],
            'hiringOrganization' => array_filter([
                '@type' => 'Organization',
                'name' => $empresa?->nombre,
                'sameAs' => EnlaceExterno::seguro($empresa?->sitio_web),
            ]),
            'url' => route('empleos.show', $vacante->id),
        ];

        if ($vacante->jornada !== null) {
            $oferta['employmentType'] = $vacante->jornada->tipoDeEmpleo();
        }

        if ($vacante->sueldo_minimo !== null || $vacante->sueldo_maximo !== null) {
            // Solo lo que la empresa indicó: con un solo extremo, un valor; con los dos, un rango (siempre por mes).
            $valor = ($vacante->sueldo_minimo !== null && $vacante->sueldo_maximo !== null && $vacante->sueldo_minimo !== $vacante->sueldo_maximo)
                ? ['@type' => 'QuantitativeValue', 'minValue' => $vacante->sueldo_minimo, 'maxValue' => $vacante->sueldo_maximo, 'unitText' => 'MONTH']
                : ['@type' => 'QuantitativeValue', 'value' => $vacante->sueldo_minimo ?? $vacante->sueldo_maximo, 'unitText' => 'MONTH'];
            $oferta['baseSalary'] = ['@type' => 'MonetaryAmount', 'currency' => 'PEN', 'value' => $valor];
        }

        if ($vacante->modalidad === Modalidad::Remoto) {
            // Remoto: Google pide los países desde donde se puede postular; sin cobertura escrita, Perú.
            $oferta['jobLocationType'] = 'TELECOMMUTE';
            $oferta['applicantLocationRequirements'] = ['@type' => 'Country', 'name' => $lugar ?? 'Perú'];
        } else {
            $oferta['jobLocation'] = [
                '@type' => 'Place',
                'address' => array_filter(['@type' => 'PostalAddress', 'addressLocality' => $lugar, 'addressCountry' => 'PE']),
            ];
        }

        return $oferta;
    }

    /**
     * Organización y sitio web, para la portada.
     *
     * @return array<string, mixed>
     */
    public static function organizacionYSitio(): array
    {
        $inicio = route('inicio');

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => $inicio.'#organizacion',
                    'name' => config('app.name', 'TalentCloud AI'),
                    'url' => $inicio,
                    'logo' => asset('marca/talentcloud-ai.svg'),
                    'contactPoint' => [
                        '@type' => 'ContactPoint',
                        'contactType' => 'customer support',
                        'email' => config('talentcloud.soporte.correo'),
                        'availableLanguage' => 'es',
                    ],
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => $inicio.'#sitio',
                    'url' => $inicio,
                    'name' => config('app.name', 'TalentCloud AI'),
                    'inLanguage' => 'es',
                    'publisher' => ['@id' => $inicio.'#organizacion'],
                ],
            ],
        ];
    }

    /**
     * El JSON listo para ir dentro de `<script type="application/ld+json">`: `<`, `>` y `&` escapados para que un título
     * o una descripción escritos por una empresa nunca cierren la etiqueta.
     *
     * @param  array<string, mixed>  $datos
     */
    public static function json(array $datos): string
    {
        return (string) json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    }

    /** La descripción en HTML sencillo (lo que admite Google): la de la vacante y sus requisitos. Todo escapado. */
    private static function descripcion(Vacante $vacante): string
    {
        $html = '';

        foreach (preg_split('/\R{2,}/', trim((string) $vacante->descripcion)) ?: [] as $parrafo) {
            if (trim($parrafo) !== '') {
                $html .= '<p>'.nl2br(e(trim($parrafo)), false).'</p>';
            }
        }

        $requisitos = $vacante->requisitos
            ->filter(fn ($r) => $r->competencia !== null)
            ->map(fn ($r) => '<li>'.e($r->competencia->nombre).($r->tipo === TipoRequisito::Obligatorio ? ' (obligatorio)' : ' (deseable)').'</li>')
            ->implode('');

        if ($requisitos !== '') {
            $html .= '<p>Requisitos:</p><ul>'.$requisitos.'</ul>';
        }

        return $html !== '' ? $html : '<p>'.e($vacante->titulo).'</p>';
    }
}
