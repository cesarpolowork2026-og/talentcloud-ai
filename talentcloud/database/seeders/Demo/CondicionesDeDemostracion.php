<?php

namespace Database\Seeders\Demo;

use App\Models\Vacante;
use App\Support\BolsaPublica;

/**
 * Jornada y rango de sueldo (en soles al mes) de las vacantes de la demostración, para que la bolsa de empleos muestre las
 * dos situaciones reales: vacantes con condiciones y vacantes en las que la empresa no las indicó («a convenir»: las que
 * no figuran aquí). Son valores inventados para la demostración. Es idempotente: no toca una vacante que ya tiene jornada.
 */
final class CondicionesDeDemostracion
{
    /**
     * título => [jornada, mínimo, máximo]. Sin máximo, la oferta dice «Desde S/ …».
     * Sin entrada: «a convenir» (Arquitecto de Software, Contador General, Brand Designer, Content Strategist,
     * Licenciado(a) en Enfermería UCI y Analista de Marketing Digital).
     */
    public const POR_TITULO = [
        // Tecnología
        'Desarrollador Backend' => ['completa', 7000, 10000],
        'DevOps Engineer' => ['completa', 8000, 12000],
        'Data Analyst' => ['completa', 5000, 7500],
        'QA Engineer' => ['completa', 4500, 6500],
        'Analista de Soporte TI' => ['completa', 2200, 3200],
        'Científico de Datos' => ['completa', 7000, null],
        'Desarrollador Frontend' => ['completa', 4500, 6500],
        'Desarrollador Frontend React' => ['completa', 4500, 6500],
        'Desarrollador Full Stack' => ['completa', 5500, 8000],
        'Desarrollador PHP Laravel' => ['completa', 4500, 6500],
        'Ingeniero de Datos' => ['completa', 6000, 9000],
        'QA Automation Engineer' => ['completa', 4500, 6500],
        'Scrum Master' => ['completa', 5500, 7500],
        'Frontend Developer' => ['completa', 4500, 6500],
        // Diseño y marketing
        'Product Designer' => ['completa', 5500, null],
        'UI Designer' => ['completa', 4000, 5500],
        'UX Researcher' => ['completa', 4500, 6000],
        'Motion Designer' => ['completa', 3500, 5000],
        'Community Manager' => ['completa', 2500, 3500],
        'Marketing Digital Specialist' => ['completa', 3500, 5000],
        'Project Manager' => ['completa', 5500, 7500],
        // Retail y comercial
        'Asesor(a) de Tienda' => ['completa', 1200, 1800],
        'Cajero(a)' => ['parcial', 1100, null],
        'Supervisor(a) de Ventas' => ['completa', 2500, 3300],
        'Visual Merchandiser' => ['completa', 1900, 2600],
        'Jefe de Tienda' => ['completa', 3200, 4200],
        'Jefe de Zona' => ['completa', 5000, 6500],
        'Ejecutivo Comercial B2B' => ['completa', 2500, 4500],
        'Analista de Inventarios' => ['completa', 2200, 3000],
        // Gastronomía
        'Barista' => ['completa', 1500, 2200],
        'Cajero(a) de Cafetería' => ['parcial', 1130, 1400],
        'Mozo(a)' => ['parcial', 1130, 1500],
        'Cocinero(a) de Línea' => ['completa', 1600, 2200],
        'Pastelero(a)' => ['completa', 1700, 2300],
        'Jefe(a) de Local' => ['completa', 2800, 3800],
        'Asistente de Compras' => ['completa', 1800, 2400],
        // Salud y administración
        'Analista de Planillas' => ['completa', 2800, 3600],
        'Analista de Tesorería' => ['completa', 3000, 4000],
        'Asistente de Recursos Humanos' => ['completa', 1800, 2500],
        'Coordinador(a) de Calidad' => ['completa', 4000, 5500],
        'Enfermera(o) de Hospitalización' => ['completa', 3000, 3800],
        'Jefe(a) de Enfermería' => ['completa', 5500, 7000],
        'Recepcionista Clínica' => ['completa', 1500, 2000],
        'Técnico(a) de Enfermería' => ['completa', 1600, 2200],
        // Logística
        'Analista de Comercio Exterior' => ['completa', 3000, 4200],
        'Analista de Logística' => ['completa', 2800, 3800],
        'Asistente Administrativo' => ['completa', 1500, 2100],
        'Coordinador(a) de Inventarios' => ['completa', 3000, 3800],
        'Jefe de Almacén' => ['completa', 4500, 5800],
        'Jefe de Operaciones' => ['completa', 6500, null],
        'Montacarguista' => ['completa', 1800, 2400],
        'Operario(a) de Almacén' => ['completa', 1300, 1800],
        'Supervisor(a) de Distribución' => ['completa', 3000, 4000],
    ];

    /** @return int cuántas vacantes se completaron */
    public static function aplicar(): int
    {
        $completadas = 0;

        foreach (self::POR_TITULO as $titulo => [$jornada, $minimo, $maximo]) {
            $completadas += Vacante::sinFiltroDeEmpresa()
                ->where('titulo', $titulo)
                ->whereNull('jornada')
                ->update(['jornada' => $jornada, 'sueldo_minimo' => $minimo, 'sueldo_maximo' => $maximo]);
        }

        BolsaPublica::olvidar();

        return $completadas;
    }
}
