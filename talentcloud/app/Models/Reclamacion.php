<?php

namespace App\Models;

use App\Enums\EstadoReclamacion;
use App\Enums\TipoDocumento;
use App\Enums\TipoReclamacion;
use App\Support\Formato;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Hoja del Libro de Reclamaciones. La presenta un consumidor ante TalentCloud AI
 * (no es de ninguna empresa cliente: sin BelongsToEmpresa) y la atiende el admin
 * SaaS. Se crea solo con RegistrarReclamacion (número correlativo) y se responde
 * con ResponderReclamacion.
 *
 * @property int $id
 * @property int $numero
 * @property TipoReclamacion $tipo
 * @property EstadoReclamacion $estado
 * @property string $nombre
 * @property TipoDocumento $tipo_documento
 * @property string $numero_documento
 * @property string $domicilio
 * @property string|null $telefono
 * @property string $email
 * @property bool $es_menor
 * @property string|null $apoderado_nombre
 * @property TipoDocumento|null $apoderado_tipo_documento
 * @property string|null $apoderado_numero_documento
 * @property string $servicio
 * @property string|null $monto
 * @property string $detalle
 * @property string $pedido
 * @property Carbon $fecha_limite
 * @property string|null $respuesta
 * @property Carbon|null $respondida_at
 * @property int|null $respondida_por
 * @property Carbon $created_at
 */
class Reclamacion extends Model
{
    protected $table = 'reclamaciones';

    /** Ni el número, ni el estado, ni la respuesta se asignan en masa: los fijan los servicios. */
    protected $fillable = [
        'tipo',
        'nombre',
        'tipo_documento',
        'numero_documento',
        'domicilio',
        'telefono',
        'email',
        'es_menor',
        'apoderado_nombre',
        'apoderado_tipo_documento',
        'apoderado_numero_documento',
        'servicio',
        'monto',
        'detalle',
        'pedido',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoReclamacion::class,
            'estado' => EstadoReclamacion::class,
            'tipo_documento' => TipoDocumento::class,
            'apoderado_tipo_documento' => TipoDocumento::class,
            'es_menor' => 'boolean',
            'monto' => 'decimal:2',
            'fecha_limite' => 'date',
            'respondida_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function respondidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'respondida_por');
    }

    /** "LR-2026-000001": año de presentación y número correlativo. */
    public function codigo(): string
    {
        return 'LR-'.$this->created_at->year.'-'.str_pad((string) $this->numero, 6, '0', STR_PAD_LEFT);
    }

    /**
     * La hoja como pares etiqueta => valor, en el orden del reglamento. La usan el
     * correo (copia a soporte y al consumidor) y el detalle del admin.
     *
     * @return array<string, string>
     */
    public function hoja(): array
    {
        $documento = fn (?TipoDocumento $tipo, ?string $numero) => $tipo ? $tipo->etiqueta().' '.$numero : (string) $numero;

        return array_filter([
            'Hoja' => $this->codigo(),
            'Fecha' => Formato::fechaHora($this->created_at),
            'Tipo' => $this->tipo->etiqueta().': '.Str::lcfirst($this->tipo->definicion()),
            'Nombre' => $this->nombre,
            'Documento' => $documento($this->tipo_documento, $this->numero_documento),
            'Domicilio' => $this->domicilio,
            'Teléfono' => (string) $this->telefono,
            'Correo' => $this->email,
            'Menor de edad' => $this->es_menor ? 'Sí' : 'No',
            'Padre, madre o apoderado' => $this->es_menor ? $this->apoderado_nombre.' ('.$documento($this->apoderado_tipo_documento, $this->apoderado_numero_documento).')' : '',
            'Servicio contratado' => $this->servicio,
            'Monto reclamado' => $this->monto !== null ? 'S/ '.number_format((float) $this->monto, 2, '.', ',') : '',
            'Detalle' => $this->detalle,
            'Pedido' => $this->pedido,
            'Fecha límite de respuesta' => Formato::fechaLarga($this->fecha_limite),
        ], fn (string $valor) => $valor !== '');
    }

    /** Pendiente y con el plazo de respuesta ya cumplido. */
    public function vencida(): bool
    {
        return $this->estado === EstadoReclamacion::Pendiente && $this->fecha_limite->lt(today());
    }
}
