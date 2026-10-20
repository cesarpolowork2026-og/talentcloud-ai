<?php

namespace App\Services\Competencias;

use App\Models\Competencia;
use App\Models\User;
use App\Services\Plataforma\RegistroDeAuditoria;
use App\Services\Vacantes\OperacionNoPermitida;
use App\Support\BolsaPublica;
use Illuminate\Support\Facades\DB;

/**
 * Administración del catálogo de competencias, que comparten todas las empresas. Lo hace el admin SaaS: crear,
 * renombrar, agrupar bajo una categoría, definir sinónimos («PowerBI» para Power BI), fusionar duplicados (los
 * requisitos y las competencias de los candidatos pasan a la que se conserva) y eliminar las que nadie usa.
 * Cada cambio queda en el registro de acciones.
 */
class CatalogoDeCompetencias
{
    public function __construct(private readonly RegistroDeAuditoria $auditoria) {}

    /** @param  list<string>  $sinonimos */
    public function crear(User $actor, string $nombre, ?string $categoria, array $sinonimos): Competencia
    {
        $nombre = trim($nombre);
        $sinonimos = $this->limpiar($sinonimos, $nombre);
        $this->verificarLibres([$nombre, ...$sinonimos], null);

        $competencia = Competencia::create(['nombre' => $nombre, 'categoria' => $this->categoria($categoria), 'sinonimos' => $sinonimos]);

        $this->auditoria->anotar($actor, RegistroDeAuditoria::COMPETENCIA_CREADA, "Se creó la competencia {$competencia->nombre} en el catálogo.");

        return $competencia;
    }

    /** @param  list<string>  $sinonimos */
    public function actualizar(User $actor, Competencia $competencia, string $nombre, ?string $categoria, array $sinonimos): Competencia
    {
        $nombre = trim($nombre);
        $sinonimos = $this->limpiar($sinonimos, $nombre);
        $this->verificarLibres([$nombre, ...$sinonimos], $competencia->id);

        $anterior = $competencia->nombre;
        $competencia->fill(['nombre' => $nombre, 'categoria' => $this->categoria($categoria), 'sinonimos' => $sinonimos])->save();

        $this->auditoria->anotar(
            $actor,
            RegistroDeAuditoria::COMPETENCIA_EDITADA,
            $anterior === $competencia->nombre ? "Se editó la competencia {$competencia->nombre}." : "Se renombró la competencia {$anterior} a {$competencia->nombre}.",
        );

        return $competencia;
    }

    /**
     * Pasa todo lo que usa $origen a $destino y elimina $origen. Su nombre y sus sinónimos se suman a los de $destino,
     * así lo que se escriba con el nombre antiguo se sigue reconociendo.
     */
    public function fusionar(User $actor, Competencia $origen, Competencia $destino): Competencia
    {
        if ($origen->is($destino)) {
            throw new OperacionNoPermitida('Elige otra competencia: no se puede fusionar una consigo misma.', 'destino');
        }

        DB::transaction(function () use ($origen, $destino): void {
            // Sin pasar por los modelos: las filas son de muchas empresas y no hace falta un contexto de empresa.
            DB::statement(
                'DELETE FROM requisitos r WHERE r.competencia_id = ? AND EXISTS (SELECT 1 FROM requisitos d WHERE d.vacante_id = r.vacante_id AND d.competencia_id = ?)',
                [$origen->id, $destino->id],
            );
            DB::table('requisitos')->where('competencia_id', $origen->id)->update(['competencia_id' => $destino->id]);

            DB::statement(
                'DELETE FROM candidato_competencia c WHERE c.competencia_id = ? AND EXISTS (SELECT 1 FROM candidato_competencia d WHERE d.competencia_id = ? AND d.candidato_id = c.candidato_id AND d.fuente = c.fuente AND d.empresa_id IS NOT DISTINCT FROM c.empresa_id)',
                [$origen->id, $destino->id],
            );
            DB::table('candidato_competencia')->where('competencia_id', $origen->id)->update(['competencia_id' => $destino->id]);

            $destino->sinonimos = $this->limpiar([...$destino->sinonimos, $origen->nombre, ...$origen->sinonimos], $destino->nombre);
            $destino->save();
            $origen->delete();
        });

        BolsaPublica::olvidar();

        $this->auditoria->anotar($actor, RegistroDeAuditoria::COMPETENCIA_FUSIONADA, "Se fusionó la competencia {$origen->nombre} con {$destino->nombre}.");

        return $destino->refresh();
    }

    /** Solo se elimina la que nadie usa; si está en uso, se fusiona con otra. */
    public function eliminar(User $actor, Competencia $competencia): void
    {
        $usos = $this->usos($competencia);

        if ($usos['requisitos'] > 0 || $usos['candidatos'] > 0) {
            throw new OperacionNoPermitida(
                "{$competencia->nombre} está en uso ({$usos['requisitos']} requisitos y {$usos['candidatos']} anotaciones de candidatos). Fusiónala con otra competencia para no perder esos datos.",
                'competencia',
            );
        }

        $nombre = $competencia->nombre;
        $competencia->delete();

        $this->auditoria->anotar($actor, RegistroDeAuditoria::COMPETENCIA_ELIMINADA, "Se eliminó la competencia {$nombre} del catálogo.");
    }

    /** @return array{requisitos: int, candidatos: int} */
    public function usos(Competencia $competencia): array
    {
        return [
            'requisitos' => DB::table('requisitos')->where('competencia_id', $competencia->id)->count(),
            'candidatos' => DB::table('candidato_competencia')->where('competencia_id', $competencia->id)->count(),
        ];
    }

    /**
     * Sinónimos sin espacios sobrantes, sin repetidos y sin el propio nombre.
     *
     * @param  list<string>  $sinonimos
     * @return list<string>
     */
    private function limpiar(array $sinonimos, string $nombre): array
    {
        $vistos = [Competencia::normalizar($nombre) => true];
        $limpios = [];

        foreach ($sinonimos as $sinonimo) {
            $sinonimo = trim($sinonimo);
            $clave = Competencia::normalizar($sinonimo);

            if ($sinonimo === '' || isset($vistos[$clave])) {
                continue;
            }

            $vistos[$clave] = true;
            $limpios[] = $sinonimo;
        }

        return $limpios;
    }

    /**
     * Ningún nombre ni sinónimo puede ser ya el de otra competencia: el catálogo resuelve un texto a una sola.
     *
     * @param  list<string>  $textos
     */
    private function verificarLibres(array $textos, ?int $propia): void
    {
        foreach ($textos as $texto) {
            $existente = Competencia::resolver($texto);

            if ($existente !== null && $existente->id !== $propia) {
                throw new OperacionNoPermitida("«{$texto}» ya es el nombre o un sinónimo de {$existente->nombre}.", 'nombre');
            }
        }
    }

    private function categoria(?string $categoria): ?string
    {
        $categoria = trim((string) $categoria);

        return $categoria === '' ? null : $categoria;
    }
}
