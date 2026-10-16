<?php

namespace App\Enums;

/** Estado de una vacante. */
enum EstadoVacante: string
{
    use EsEnumDeEstado;

    case Borrador = 'borrador';
    case Abierta = 'abierta';
    case EnRevision = 'en_revision';
    case Cerrada = 'cerrada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Abierta => 'Abierta',
            self::EnRevision => 'En revisión',
            self::Cerrada => 'Cerrada',
        };
    }

    /**
     * Ciclo de vida: Borrador, Abierta, En revisión y Cerrada.
     * Cerrada es final.
     *
     * @return list<self>
     */
    public function transiciones(): array
    {
        return match ($this) {
            self::Borrador => [self::Abierta],
            self::Abierta => [self::EnRevision, self::Cerrada],
            self::EnRevision => [self::Abierta, self::Cerrada],
            self::Cerrada => [],
        };
    }

    public function puedePasarA(self $destino): bool
    {
        return in_array($destino, $this->transiciones(), true);
    }

    /** Texto del botón que lleva a este estado desde $origen. */
    public function accionDesde(self $origen): string
    {
        return match ($this) {
            self::Abierta => $origen === self::Borrador ? 'Publicar' : 'Reabrir',
            self::EnRevision => 'Pasar a revisión',
            self::Cerrada => 'Cerrar vacante',
            self::Borrador => 'Volver a borrador',
        };
    }

    /**
     * "Vacantes activas" (Dashboard, Mi empresa, Empresas): procesos en curso,
     * publicados y no cerrados.
     *
     * @return list<self>
     */
    public static function activos(): array
    {
        return [self::Abierta, self::EnRevision];
    }

    /** Se pueden editar datos y requisitos mientras no esté cerrada. */
    public function esEditable(): bool
    {
        return $this !== self::Cerrada;
    }

    /** Tono de <x-ui.tag>, según tagFor() del prototipo. */
    public function tono(): string
    {
        return match ($this) {
            self::Borrador => 'neutral',
            self::Abierta => 'accent',
            self::EnRevision => 'neutral',
            self::Cerrada => 'outline',
        };
    }
}
