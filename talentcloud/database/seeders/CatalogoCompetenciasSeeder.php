<?php

namespace Database\Seeders;

use App\Models\Competencia;
use Illuminate\Database\Seeder;

/**
 * Catálogo base de competencias con sus sinónimos (la extracción de CV los
 * usa para normalizar: "Postgres" → PostgreSQL). Idempotente.
 */
class CatalogoCompetenciasSeeder extends Seeder
{
    /** @var array<string, array<string, list<string>>> categoría => [competencia => sinónimos] */
    public const CATALOGO = [
        'Lenguajes' => [
            'Java' => [],
            'Python' => [],
            'PHP' => [],
            'JavaScript' => ['JS', 'ECMAScript'],
            'TypeScript' => ['TS'],
            'Go' => ['Golang'],
            'SQL' => [],
        ],
        'Frameworks' => [
            'Spring Boot' => ['Spring', 'Spring Framework'],
            'Laravel' => [],
            'React' => ['React.js', 'ReactJS'],
            'Node.js' => ['Node', 'NodeJS'],
            'Django' => [],
        ],
        'Datos' => [
            'PostgreSQL' => ['Postgres', 'psql'],
            'MySQL' => [],
            'MongoDB' => ['Mongo'],
            'Power BI' => ['PowerBI'],
            'Excel avanzado' => ['Excel'],
            'Machine Learning' => ['ML', 'aprendizaje automático'],
        ],
        'Infraestructura' => [
            'Docker' => ['contenedores'],
            'Kubernetes' => ['k8s'],
            'Terraform' => [],
            'AWS' => ['Amazon Web Services'],
            'Certificación AWS' => ['AWS Certified', 'AWS Solutions Architect', 'certificación de AWS'],
            'Google Cloud' => ['GCP', 'Google Cloud Platform'],
            'CI/CD' => ['integración continua', 'GitHub Actions', 'GitLab CI'],
            'Git' => ['GitHub', 'GitLab'],
        ],
        'Calidad' => [
            'Selenium' => [],
            'Cypress' => [],
            'Testing de APIs' => ['Postman', 'pruebas de API'],
            'Pruebas automatizadas' => ['test automation', 'automatización de pruebas'],
        ],
        'Diseño' => [
            'Figma' => [],
            'Research' => ['UX research', 'investigación de usuarios'],
            'Prototipado' => ['prototipos', 'wireframes'],
            'Design systems' => ['sistema de diseño', 'sistemas de diseño'],
        ],
        'Habilidades' => [
            'Scrum' => ['metodologías ágiles', 'Agile'],
            'Comunicación' => [],
            'Inglés' => ['English'],
        ],
    ];

    public function run(): void
    {
        foreach (self::CATALOGO as $categoria => $competencias) {
            foreach ($competencias as $nombre => $sinonimos) {
                Competencia::updateOrCreate(
                    ['nombre_normalizado' => Competencia::normalizar($nombre)],
                    ['nombre' => $nombre, 'categoria' => $categoria, 'sinonimos' => $sinonimos],
                );
            }
        }
    }
}
