<?php

namespace Database\Seeders\Demo;

use App\Models\Empresa;
use App\Support\BolsaPublica;

/**
 * Una descripción breve de cada empresa de la demostración: es lo que lee quien postula en «Sobre la empresa». Sin ella
 * la ficha de una vacante solo dice el sector. Empresas y textos son ficticios. Es idempotente: no pisa una descripción
 * que la empresa ya escribió.
 */
final class PerfilesDeEmpresasDeDemostracion
{
    public const POR_EMPRESA = [
        'Nimbus Corp' => 'Empresa de software a medida y soporte tecnológico para pymes de Lima. Equipos pequeños, trabajo híbrido, revisión de código entre pares y buena documentación.',
        'Vela Studio' => 'Estudio de diseño de producto y experiencias digitales. Trabajamos con startups y marcas en investigación de usuarios, diseño de interfaces, identidad y prototipos.',
        'Orbita Labs' => 'Laboratorio de desarrollo de software y análisis de datos para el sector financiero. Equipo técnico con foco en calidad, pruebas y automatización.',
        'Ruta Sur Logística' => 'Operador logístico con distribución y almacenes en el sur del Perú. Entregas puntuales, trazabilidad de cada pedido y turnos con capacitación en seguridad.',
        'Brío Retail' => 'Cadena de tiendas de retail con locales en Lima y venta en línea. Atención cercana al cliente, equipos con línea de carrera y campañas de temporada.',
        'Nuna Salud' => 'Red de centros de salud y atención ambulatoria. Profesionales de la salud y personal administrativo comprometidos con la seguridad y el trato al paciente.',
        'Altura Café' => 'Cafeterías de especialidad con granos de altura. Equipos jóvenes de cocina, barra y salón, con capacitación continua y horarios rotativos.',
    ];

    /** @return int cuántas empresas se completaron */
    public static function aplicar(): int
    {
        $completadas = 0;

        foreach (self::POR_EMPRESA as $nombre => $descripcion) {
            $completadas += Empresa::query()
                ->where('nombre', $nombre)
                ->where(fn ($q) => $q->whereNull('descripcion')->orWhere('descripcion', ''))
                ->update(['descripcion' => $descripcion]);
        }

        BolsaPublica::olvidar();

        return $completadas;
    }
}
