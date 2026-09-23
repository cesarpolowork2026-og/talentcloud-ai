<?php

namespace Database\Factories;

use App\Models\Competencia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Competencia>
 */
class CompetenciaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->randomElement([
                'Java', 'Spring Boot', 'PostgreSQL', 'Docker', 'Kubernetes', 'Python', 'SQL', 'Figma',
                'React', 'Laravel', 'PHP', 'Go', 'Rust', 'Terraform', 'Selenium', 'Cypress', 'Git',
                'TypeScript', 'Node.js', 'MongoDB', 'Redis', 'Kafka', 'GraphQL', 'Scrum',
            ]).' '.fake()->unique()->numberBetween(1, 99999),
            'categoria' => 'Tecnología',
            'sinonimos' => [],
        ];
    }
}
