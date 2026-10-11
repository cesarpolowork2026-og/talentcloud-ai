<?php

namespace App\DTO;

/**
 * Aviso (webhook) de la pasarela, reducido a lo que TalentCloud necesita: qué
 * suscripción releer y si falló un cobro. El estado nunca se toma del aviso,
 * que puede llegar repetido o desordenado: se relee de la pasarela.
 */
final readonly class AvisoDePago
{
    public function __construct(
        /** Id del aviso en la pasarela (distinto en cada aviso, igual si se repite). */
        public string $id,
        public string $tipo,
        public ?string $suscripcionId = null,
        public ?string $clienteId = null,
        public bool $pagoFallido = false,
    ) {}
}
