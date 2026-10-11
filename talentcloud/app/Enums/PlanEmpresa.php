<?php

namespace App\Enums;

/**
 * Plan de suscripción de la empresa: límites de uso y precio de lista (en soles,
 * más IGV). Se cobra por vacantes activas, no por usuario. Starter y Pro se pagan
 * en línea si hay pasarela (App\Pagos); si no, y para Enterprise, el cambio lo
 * aplica el admin SaaS.
 * Origen de los precios: «Estudio de mercado y planes - TalentCloud AI.docx».
 */
enum PlanEmpresa: string
{
    use EsEnumDeEstado;

    case Gratis = 'gratis';
    case Starter = 'starter';
    case Pro = 'pro';
    case Enterprise = 'enterprise';

    /** Toda empresa que se registra prueba este plan durante DIAS_DE_PRUEBA días. */
    public const PLAN_DE_PRUEBA = self::Pro;

    public const DIAS_DE_PRUEBA = 14;

    public function etiqueta(): string
    {
        return match ($this) {
            self::Gratis => 'Gratis',
            self::Starter => 'Starter',
            self::Pro => 'Pro',
            self::Enterprise => 'Enterprise',
        };
    }

    /** Para quién es, en la tabla de planes. */
    public function descripcion(): string
    {
        return match ($this) {
            self::Gratis => 'Para conocer TalentCloud con una vacante.',
            self::Starter => 'Para pequeñas empresas que contratan de vez en cuando.',
            self::Pro => 'Para equipos de RR. HH. con varios procesos a la vez.',
            self::Enterprise => 'Para grandes empresas, con contrato y soporte a medida.',
        };
    }

    /** Vacantes abiertas o en revisión a la vez (null = sin límite). */
    public function limiteVacantesActivas(): ?int
    {
        return match ($this) {
            self::Gratis => 1,
            self::Starter => 3,
            self::Pro => 15,
            self::Enterprise => null,
        };
    }

    /** Usuarios activos de la empresa, su cuenta incluida (null = sin límite). */
    public function limiteUsuarios(): ?int
    {
        return match ($this) {
            self::Gratis => 2,
            self::Starter => 3,
            self::Pro => 10,
            self::Enterprise => null,
        };
    }

    /**
     * Postulaciones evaluadas con el matching de IA por mes calendario (null = sin
     * límite). Agotado el cupo, las nuevas se evalúan con el análisis básico.
     */
    public function cupoIaMensual(): ?int
    {
        return match ($this) {
            self::Gratis => 30,
            self::Starter => 300,
            self::Pro => 2000,
            self::Enterprise => null,
        };
    }

    /** Precio mensual en soles, sin IGV (Enterprise: precio desde, se cotiza). */
    public function precioMensual(): int
    {
        return match ($this) {
            self::Gratis => 0,
            self::Starter => 129,
            self::Pro => 349,
            self::Enterprise => 1200,
        };
    }

    /** Precio por mes con pago anual (20 % menos); null = no aplica. */
    public function precioMensualAnual(): ?int
    {
        return match ($this) {
            self::Starter => 99,
            self::Pro => 279,
            default => null,
        };
    }

    /** Starter y Pro se contratan pagando en línea (mensual o anual); Gratis no se paga y Enterprise se cotiza. */
    public function sePagaEnLinea(): bool
    {
        return $this === self::Starter || $this === self::Pro;
    }

    /** Enterprise no tiene precio cerrado: se cotiza con contrato anual. */
    public function seCotiza(): bool
    {
        return $this === self::Enterprise;
    }

    public function soporte(): string
    {
        return match ($this) {
            self::Gratis => 'Ayuda en línea',
            self::Starter => 'Soporte por correo',
            self::Pro => 'Soporte prioritario y ayuda para empezar',
            self::Enterprise => 'SLA, SSO, API y facturación a medida',
        };
    }

    /** "Hasta 3 vacantes activas, 3 usuarios y 300 postulaciones con IA al mes." */
    public function descripcionDeLimites(): string
    {
        $partes = [
            $this->limiteVacantesActivas() === null ? 'vacantes activas sin límite' : 'hasta '.$this->limiteVacantesActivas().' '.($this->limiteVacantesActivas() === 1 ? 'vacante activa' : 'vacantes activas'),
            $this->limiteUsuarios() === null ? 'usuarios sin límite' : $this->limiteUsuarios().' usuarios',
            $this->cupoIaMensual() === null ? 'matching con IA sin límite' : number_format($this->cupoIaMensual(), 0, ',', ' ').' postulaciones con IA al mes',
        ];

        return ucfirst($partes[0].', '.$partes[1].' y '.$partes[2]).'.';
    }
}
