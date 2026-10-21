<?php

namespace Database\Seeders;

use App\DTO\EvaluacionData;
use App\DTO\NuevaEntrevistaData;
use App\DTO\NuevoCandidatoData;
use App\DTO\RegistroPostulanteData;
use App\Enums\CriterioEvaluacion;
use App\Enums\EstadoEvaluacion;
use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Enums\ModalidadEntrevista;
use App\Enums\Rol;
use App\Events\VacanteCreada;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Models\Requisito;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Candidatos\RegistrarCandidato;
use App\Services\Cvs\SubirCv;
use App\Services\Entrevistas\CambiarEstadoEntrevista;
use App\Services\Entrevistas\ProgramarEntrevista;
use App\Services\Evaluaciones\GuardarEvaluacion;
use App\Services\Postulaciones\MoverPostulacion;
use App\Services\Postulaciones\PostularCandidato;
use App\Services\Postulaciones\PostularmeAVacante;
use App\Services\Registro\RegistrarPostulante;
use App\Services\Vacantes\CambiarEstadoVacante;
use App\Services\Vacantes\OperacionNoPermitida;
use App\Tenancy\TenantContext;
use Database\Seeders\Demo\CondicionesDeDemostracion;
use Database\Seeders\Demo\DocumentosDePrueba;
use Database\Seeders\Demo\PerfilesDeEmpresasDeDemostracion;
use Database\Seeders\Demo\Volumen\CatalogoDeVacantes;
use Database\Seeders\Demo\Volumen\GeneradorDePersonas;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Lleva cada empresa a unas diez vacantes con decenas o cientos de postulaciones:
 *
 *   php artisan db:seed --class=VolumenDeDemostracionSeeder   (después de migrate:fresh --seed)
 *
 * Igual que DemoSeeder, reproduce el proceso con los servicios de la aplicación.
 * No es parte de DatabaseSeeder porque tarda varios minutos.
 */
class VolumenDeDemostracionSeeder extends Seeder
{
    private const SEMILLA = 20261014;

    /** Hoy a las 00:00 y el instante real en que se siembra: nada de la historia ocurre después de este. */
    private Carbon $hoy;

    private Carbon $ahora;

    private GeneradorDePersonas $personas;

    /** @var array<string, list<array{candidato: Candidato, usuario: User|null, persona: array<string, mixed>}>> Personas ya creadas, por empresa y familia. */
    private array $reserva = [];

    /** @var array<int, array<string, true>> Horas ya ocupadas de cada entrevistador (Y-m-d H). */
    private array $ocupado = [];

    /** @var array<string, Competencia> */
    private array $competencias = [];

    private int $postulaciones = 0;

    public function run(): void
    {
        if (! Empresa::where('nombre', 'Nimbus Corp')->exists()) {
            $this->command->error('Primero siembra la demostración: php artisan migrate:fresh --seed');

            return;
        }

        $anterior = [
            'queue.default' => config('queue.default'),
            'mail.default' => config('mail.default'),
            'hashing.bcrypt.rounds' => config('hashing.bcrypt.rounds'),
            'talentcloud.dashboard.cache_segundos' => config('talentcloud.dashboard.cache_segundos'),
        ];
        $relojAnterior = Carbon::getTestNow();
        $this->ahora = Carbon::now();
        $hasher = app('hash')->driver('bcrypt');
        $costeAnterior = (int) config('hashing.bcrypt.rounds', 12);

        // Cola en el momento, sin correos y contraseñas rápidas de calcular.
        config(['queue.default' => 'sync', 'mail.default' => 'array', 'hashing.bcrypt.rounds' => 4, 'talentcloud.dashboard.cache_segundos' => 0]);
        app('mail.manager')->forgetMailers();
        if (method_exists($hasher, 'setRounds')) {
            $hasher->setRounds(4);
        }

        $inicio = microtime(true);

        try {
            app(TenantContext::class)->ejecutarGlobal(function () {
                $this->sembrar();
                CondicionesDeDemostracion::aplicar();
                PerfilesDeEmpresasDeDemostracion::aplicar();
            });
        } catch (Throwable $e) {
            $this->command->error('El volumen quedó a medias: '.$e->getMessage().'. Vuelve a empezar con php artisan migrate:fresh --seed');

            throw $e;
        } finally {
            Carbon::setTestNow($relojAnterior);
            auth()->forgetUser();
            config($anterior);
            if (method_exists($hasher, 'setRounds')) {
                $hasher->setRounds($costeAnterior);
            }
            app('mail.manager')->forgetMailers();
        }

        $this->command->info(sprintf('Listo: %d postulaciones sembradas en %.1f min.', $this->postulaciones, (microtime(true) - $inicio) / 60));
    }

    private function sembrar(): void
    {
        $this->hoy = Carbon::today();
        $this->personas = new GeneradorDePersonas(self::SEMILLA, $this->hoy);
        $this->competencias = Competencia::all()->keyBy('nombre')->all();
        mt_srand(self::SEMILLA);

        foreach (CatalogoDeVacantes::porEmpresa() as $nombre => $especificaciones) {
            $empresa = Empresa::where('nombre', $nombre)->first();

            if ($empresa === null) {
                continue;
            }

            $personal = User::where('empresa_id', $empresa->id)->get();
            $entrevistadores = $personal->filter(fn (User $u) => $u->tieneRol(Rol::Reclutador))->values();
            $autor = $entrevistadores->first() ?? $personal->first();

            if ($autor === null) {
                continue;
            }

            if ($entrevistadores->isEmpty()) {
                $entrevistadores = $personal->values();
            }

            $edad = max(2, (int) $empresa->created_at->diffInDays($this->hoy));

            // De la más antigua a la más reciente: las cerradas dejan su lugar antes de que se publiquen las nuevas.
            usort($especificaciones, fn (array $a, array $b) => $b['hace'] <=> $a['hace']);

            foreach ($especificaciones as $spec) {
                if (Vacante::sinFiltroDeEmpresa()->where('empresa_id', $empresa->id)->where('titulo', $spec['titulo'])->exists()) {
                    continue;
                }

                $this->vacante($empresa, $autor, $entrevistadores->all(), $spec, $edad);
            }
        }
    }

    /**
     * @param  list<User>  $entrevistadores
     * @param  array<string, mixed>  $spec
     */
    private function vacante(Empresa $empresa, User $autor, array $entrevistadores, array $spec, int $edad): void
    {
        $hace = max(1, min((int) $spec['hace'], $edad - 1));
        $creada = $this->hoy->copy()->subDays($hace)->setTime(9, 0);
        $this->fijarReloj($creada, $autor);

        $vacante = $this->crearVacante($autor, $spec);

        try {
            Carbon::setTestNow(now()->addMinutes(30));
            $vacante = app(CambiarEstadoVacante::class)->ejecutar($vacante, EstadoVacante::Abierta);
        } catch (OperacionNoPermitida $e) {
            // El plan no admite otra vacante activa a la vez: queda como borrador, que también es un estado real.
            $this->command->warn("{$empresa->nombre}: «{$spec['titulo']}» queda en borrador ({$e->getMessage()})");

            return;
        }

        [$minimo, $maximo] = CatalogoDeVacantes::VOLUMEN[$spec['clase']];
        $cantidad = max(3, (int) round(mt_rand($minimo, $maximo) * (float) config('talentcloud.demo.escala_de_volumen', 1)));
        $cerrada = $spec['estado'] === EstadoVacante::Cerrada;
        $cierre = $creada->copy()->addDays(max(1, (int) round($hace * 0.7)))->setTime(18, 0);
        $limite = $cerrada ? $cierre->copy()->subDays(10) : $this->ahora->copy()->subMinutes(30);
        $desde = $creada->copy()->addHours(3);
        $segundos = max(3600, (int) $desde->diffInSeconds($limite));

        // Más postulaciones al principio, cuando la vacante es nueva.
        $momentos = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $momentos[] = $desde->copy()->addSeconds((int) ($segundos * (mt_rand(0, 1000) / 1000) ** 1.5));
        }
        usort($momentos, fn (Carbon $a, Carbon $b) => $a <=> $b);

        $contratados = 0;
        $aplicaron = [];
        foreach ($momentos as $momento) {
            $postulacion = $this->postular($empresa, $autor, $vacante, $spec['familias'], array_column($spec['requisitos'], 0), $momento, $aplicaron);

            if ($postulacion === null) {
                continue;
            }

            $this->postulaciones++;
            $contratados += $this->avanzar($postulacion, $vacante, $entrevistadores, $momento, $cerrada, $contratados);
        }

        if ($cerrada) {
            $this->cerrar($vacante, $autor, $cierre);
        } elseif ($spec['estado'] === EstadoVacante::EnRevision) {
            $this->fijarReloj($this->ahora->copy()->subMinutes(5), $autor);
            app(CambiarEstadoVacante::class)->ejecutar($vacante->fresh() ?? $vacante, EstadoVacante::EnRevision);
        }

        $this->command->info(sprintf('%s · %s (%s): %d postulaciones', $empresa->nombre, $spec['titulo'], $spec['estado']->etiqueta(), count($aplicaron)));
    }

    /** @param  array<string, mixed>  $spec */
    private function crearVacante(User $autor, array $spec): Vacante
    {
        $vacante = new Vacante([
            'titulo' => $spec['titulo'],
            'descripcion' => $spec['descripcion'],
            'ubicacion' => $spec['modalidad']->normalizarUbicacion($spec['ubicacion']),
            'modalidad' => $spec['modalidad'],
            'estado' => EstadoVacante::Borrador,
        ]);
        $vacante->empresa_id = (int) $autor->empresa_id;
        $vacante->creada_por = $autor->id;
        $vacante->save();

        foreach ($spec['requisitos'] as $orden => [$competencia, $tipo, $peso, $anios]) {
            $requisito = new Requisito(['vacante_id' => $vacante->id, 'competencia_id' => $this->competencia($competencia)->id]);
            $requisito->fill(['tipo' => $tipo, 'peso' => $peso, 'anios_minimos' => $anios, 'orden' => $orden]);
            $requisito->empresa_id = $vacante->empresa_id;
            $requisito->save();
        }

        VacanteCreada::dispatch($vacante, $autor);

        return $vacante;
    }

    /**
     * Una persona postula a la vacante: una que ya estaba registrada en la empresa (35 %) o una nueva
     * (se registra, sube su CV y postula). Una de cada cuatro la registra el equipo; el resto, ella misma.
     *
     * @param  list<string>  $familias
     * @param  list<string>  $buscadas  Competencias que pide la vacante.
     * @param  array<int, true>  $aplicaron  Candidatos que ya postularon a esta vacante.
     */
    private function postular(Empresa $empresa, User $reclutador, Vacante $vacante, array $familias, array $buscadas, Carbon $momento, array &$aplicaron): ?Postulacion
    {
        $familia = $familias[mt_rand(0, count($familias) - 1)];
        $clave = $empresa->id.'|'.$familia;

        $existente = null;
        if (! empty($this->reserva[$clave]) && mt_rand(1, 100) <= 35) {
            $existente = $this->reserva[$clave][mt_rand(0, count($this->reserva[$clave]) - 1)];
            if (isset($aplicaron[$existente['candidato']->id])) {
                $existente = null;
            }
        }

        try {
            if ($existente !== null) {
                $sujeto = $existente;
            } else {
                $persona = $this->personas->persona($familia, $buscadas);
                $sujeto = $this->registrar($empresa, $reclutador, $persona, $momento);
                $this->reserva[$clave][] = $sujeto;
            }

            $this->fijarReloj($this->noDespuesDeAhora($momento->copy()->addMinutes(mt_rand(3, 40))), $sujeto['usuario'] ?? $reclutador);

            $postulacion = $sujeto['usuario'] !== null
                ? app(PostularmeAVacante::class)->ejecutar($sujeto['usuario'], $vacante->id)
                : app(PostularCandidato::class)->ejecutar($vacante, $sujeto['candidato']);
        } catch (OperacionNoPermitida) {
            return null;
        }

        $aplicaron[$sujeto['candidato']->id] = true;

        return $postulacion;
    }

    /**
     * @param  array<string, mixed>  $persona
     * @return array{candidato: Candidato, usuario: User|null, persona: array<string, mixed>}
     */
    private function registrar(Empresa $empresa, User $reclutador, array $persona, Carbon $momento): array
    {
        $archivo = $this->archivo($persona);

        if (mt_rand(1, 100) <= 25) {
            $this->fijarReloj($momento, $reclutador);

            $candidato = DB::transaction(function () use ($empresa, $reclutador, $persona, $archivo): Candidato {
                $candidato = app(RegistrarCandidato::class)->ejecutar($empresa, new NuevoCandidatoData($persona['nombre'], $persona['email'], $persona['telefono'], $persona['titular'], true));
                app(SubirCv::class)->ejecutar($candidato, $archivo, $reclutador);

                return $candidato;
            });

            return ['candidato' => $candidato, 'usuario' => null, 'persona' => $persona];
        }

        $this->fijarReloj($momento, null);
        $usuario = app(RegistrarPostulante::class)->ejecutar(new RegistroPostulanteData($persona['nombre'], $persona['email'], 'password', true));
        $usuario->forceFill(['email_verified_at' => now()])->save();

        $candidato = $usuario->candidato()->firstOrFail();
        $candidato->update(['titular' => $persona['titular']]);

        $this->fijarReloj($momento->copy()->addMinutes(2), $usuario);
        app(SubirCv::class)->ejecutar($candidato, $archivo, $usuario);

        return ['candidato' => $candidato, 'usuario' => $usuario, 'persona' => $persona];
    }

    /**
     * Qué pasó después con la postulación (según su fecha): sigue en «Postulado», se revisó, se entrevistó, tuvo
     * oferta o contratación, o se descartó. Devuelve 1 si terminó contratada (para no contratar a toda una vacante).
     *
     * @param  list<User>  $entrevistadores
     */
    private function avanzar(Postulacion $postulacion, Vacante $vacante, array $entrevistadores, Carbon $momento, bool $cerrada, int $contratados): int
    {
        $postulacion = $postulacion->fresh() ?? $postulacion;
        $quien = $entrevistadores[mt_rand(0, count($entrevistadores) - 1)];
        $antigua = $momento->lessThan($this->hoy->copy()->subDays(8));
        $suerte = mt_rand(1, 100);

        $plan = match (true) {
            $cerrada => match (true) {
                $suerte <= 4 && $contratados < 2 && $antigua => 'contratado',
                $suerte <= 20 && $antigua => 'descartado_tras_entrevista',
                $suerte <= 55 => 'descartado',
                default => 'revision_y_descartado',
            },
            default => match (true) {
                $suerte <= 38 => 'postulado',
                $suerte <= 62 => 'revision',
                $suerte <= 75 => $antigua ? 'entrevista_pasada' : 'entrevista_futura',
                $suerte <= 82 => $antigua ? 'oferta' : 'entrevista_futura',
                $suerte <= 85 => $antigua && $contratados < 1 ? 'contratado' : 'revision',
                default => 'descartado',
            },
        };

        $terminado = false;

        switch ($plan) {
            case 'postulado':
                break;

            case 'revision':
                $this->mover($postulacion, EtapaPostulacion::EnRevision, $quien, $momento->copy()->addDays(mt_rand(1, 3)));
                break;

            case 'revision_y_descartado':
                $this->mover($postulacion, EtapaPostulacion::EnRevision, $quien, $momento->copy()->addDays(mt_rand(1, 3)));
                // Las que quedan abiertas se descartan al cerrar la vacante (ver cerrar()).
                break;

            case 'descartado':
                $this->mover($postulacion, EtapaPostulacion::Descartado, $quien, $momento->copy()->addDays(mt_rand(2, 8)));
                break;

            case 'entrevista_futura':
                $this->entrevistar($postulacion, $quien, $momento->copy()->addDays(mt_rand(1, 2)), false);
                break;

            case 'entrevista_pasada':
            case 'oferta':
            case 'contratado':
            case 'descartado_tras_entrevista':
                $inicio = $this->entrevistar($postulacion, $quien, $momento->copy()->addDays(mt_rand(1, 3)), true);

                if ($inicio === null) {
                    break;
                }

                $this->evaluar($postulacion, $quien, $inicio->copy()->addHours(3), $plan);

                if ($plan === 'oferta' || $plan === 'contratado') {
                    $this->mover($postulacion, EtapaPostulacion::Oferta, $quien, $inicio->copy()->addDay()->setTime(10, 0));
                }
                if ($plan === 'contratado' && $inicio->copy()->addDays(3)->lessThan($this->ahora)) {
                    $this->mover($postulacion, EtapaPostulacion::Contratado, $quien, $inicio->copy()->addDays(3)->setTime(11, 0));
                    $terminado = true;
                }
                if ($plan === 'descartado_tras_entrevista') {
                    $this->mover($postulacion, EtapaPostulacion::Descartado, $quien, $inicio->copy()->addDays(2)->setTime(12, 0));
                }
                break;
        }

        return $terminado ? 1 : 0;
    }

    /** Programa la entrevista y, si ya pasó, la completa. Devuelve cuándo fue (null si no se pudo agendar). */
    private function entrevistar(Postulacion $postulacion, User $entrevistador, Carbon $cuando, bool $pasada): ?Carbon
    {
        $cuando = $this->noDespuesDeAhora($cuando->copy()->setTime(mt_rand(9, 17), mt_rand(0, 3) * 15));
        $this->fijarReloj($cuando, $entrevistador);

        $base = $pasada
            ? $cuando->copy()->addDays(mt_rand(2, 6))
            : $this->hoy->copy()->addDays(mt_rand(1, 9));
        // La entrevista ya pasada ocurrió antes de ahora; la futura, después de ahora y de la fecha de agendarla.
        if ($pasada && $base->greaterThanOrEqualTo($this->hoy)) {
            $base = $this->hoy->copy()->subDays(mt_rand(1, 3));
        }

        $inicio = $this->horaLibre($entrevistador, $base);

        for ($intento = 0; $intento < 8; $intento++) {
            try {
                $entrevista = app(ProgramarEntrevista::class)->ejecutar(new NuevaEntrevistaData(
                    postulacionId: $postulacion->id,
                    entrevistadorId: $entrevistador->id,
                    iniciaAt: $inicio->toImmutable(),
                    duracionMin: 45,
                    modalidad: ModalidadEntrevista::Videollamada,
                    enlace: 'https://meet.google.com/demo-'.$postulacion->id,
                    notas: null,
                    moverAEntrevista: true,
                ), $entrevistador);
            } catch (OperacionNoPermitida) {
                // Choque de horario con otra entrevista (la historia de la demo o una anterior): siguiente hora libre.
                $inicio = $this->horaLibre($entrevistador, $inicio->copy()->addHour());

                continue;
            }

            $this->ocupado[$entrevistador->id][$inicio->format('Y-m-d H')] = true;

            if ($pasada) {
                $this->fijarReloj($inicio->copy()->addMinutes(50), $entrevistador);
                app(CambiarEstadoEntrevista::class)->completar($entrevista);
            } elseif (mt_rand(1, 100) <= 55) {
                $this->fijarReloj($this->noDespuesDeAhora($cuando->copy()->addHour()), $entrevistador);
                app(CambiarEstadoEntrevista::class)->confirmar($entrevista);
            }

            return $inicio;
        }

        return null;
    }

    /** Evaluación tras la entrevista: una nota base por candidato (algo mejor si avanza) y algo de ruido en cada criterio. */
    private function evaluar(Postulacion $postulacion, User $evaluador, Carbon $cuando, string $plan): void
    {
        $this->fijarReloj($cuando, $evaluador);

        $base = 4.5 + (mt_rand(35, 90) / 100) * 4.5 + (($plan === 'contratado' || $plan === 'oferta') ? 0.8 : 0) - ($plan === 'descartado_tras_entrevista' ? 0.8 : 0);

        $puntajes = [];
        foreach (CriterioEvaluacion::cases() as $criterio) {
            $puntajes[$criterio->value] = round(max(3.5, min(9.8, $base + (mt_rand(-12, 12) / 10))), 1);
        }
        $promedio = array_sum($puntajes) / count($puntajes);

        $estado = match (true) {
            $plan === 'oferta' || $plan === 'contratado' => EstadoEvaluacion::Aprobado,
            $promedio >= 7.5 => EstadoEvaluacion::Aprobado,
            $promedio >= 6 => EstadoEvaluacion::EnRevision,
            default => EstadoEvaluacion::Rechazado,
        };

        $comentarios = [
            'Buena comunicación y claridad al explicar su experiencia.',
            'Domina lo técnico del puesto; falta práctica en casos de mayor escala.',
            'Muy buen ajuste con el equipo y con la cultura de la empresa.',
            'Respuestas sólidas, con ejemplos concretos de logros anteriores.',
            'Conocimientos correctos pero conviene profundizar en algunas áreas.',
            'Proactiva y ordenada; mostró interés genuino por el puesto.',
        ];

        app(GuardarEvaluacion::class)->registrar($postulacion->id, $evaluador, new EvaluacionData($puntajes, $comentarios[mt_rand(0, count($comentarios) - 1)], $estado));
    }

    /** Descarta lo que sigue abierto, como al cerrar una vacante tras contratar, y la cierra. */
    private function cerrar(Vacante $vacante, User $autor, Carbon $cierre): void
    {
        $this->fijarReloj($cierre, $autor);

        $pendientes = Postulacion::sinFiltroDeEmpresa()
            ->where('vacante_id', $vacante->id)
            ->whereNotIn('etapa', [EtapaPostulacion::Contratado, EtapaPostulacion::Descartado])
            ->get();

        foreach ($pendientes as $pendiente) {
            // Quien tuviera una entrevista por realizar la cancela antes (la aplicación lo exige).
            Entrevista::sinFiltroDeEmpresa()->where('postulacion_id', $pendiente->id)->whereIn('estado', ['pendiente', 'confirmada'])->get()
                ->each(fn (Entrevista $e) => app(CambiarEstadoEntrevista::class)->cancelar($e));

            app(MoverPostulacion::class)->descartar($pendiente->fresh() ?? $pendiente, $autor);
        }

        app(CambiarEstadoVacante::class)->ejecutar($vacante->fresh() ?? $vacante, EstadoVacante::Cerrada);
    }

    private function mover(Postulacion $postulacion, EtapaPostulacion $destino, User $quien, Carbon $cuando): void
    {
        // Lo que habría ocurrido «mañana» todavía no pasó: la postulación se queda donde está.
        if ($cuando->greaterThan($this->ahora)) {
            return;
        }

        $this->fijarReloj($cuando, $quien);

        $actual = Postulacion::findOrFail($postulacion->id);
        $alFinal = Postulacion::where('vacante_id', $actual->vacante_id)->where('etapa', $destino)->count();

        app(MoverPostulacion::class)->ejecutar($actual, $destino, $alFinal, $quien);
    }

    private function noDespuesDeAhora(Carbon $momento): Carbon
    {
        return $momento->greaterThan($this->ahora) ? $this->ahora->copy()->subMinutes(10) : $momento;
    }

    /** Primera hora laboral libre del entrevistador desde `$desde` (lunes a viernes, de 9 a 17). */
    private function horaLibre(User $entrevistador, Carbon $desde): Carbon
    {
        $hora = $desde->copy()->minute(0)->second(0);

        for ($i = 0; $i < 400; $i++) {
            if ($hora->isWeekend() || $hora->hour < 9 || $hora->hour > 17) {
                $hora = $hora->isWeekend() || $hora->hour > 17 ? $hora->copy()->addDay()->setTime(9, 0) : $hora->copy()->setTime(9, 0);

                continue;
            }

            if (! isset($this->ocupado[$entrevistador->id][$hora->format('Y-m-d H')])) {
                return $hora;
            }

            $hora = $hora->copy()->addHour();
        }

        return $hora;
    }

    /** @param  array<string, mixed>  $persona */
    private function archivo(array $persona): UploadedFile
    {
        $esPdf = str_ends_with($persona['archivo'], '.pdf');
        $contenido = $esPdf ? DocumentosDePrueba::pdf($persona['texto']) : DocumentosDePrueba::docx($persona['texto']);

        return UploadedFile::fake()->createWithContent($persona['archivo'], $contenido);
    }

    private function fijarReloj(Carbon $momento, ?User $quien): void
    {
        Carbon::setTestNow($momento);

        if ($quien === null) {
            auth()->forgetUser();
            app(TenantContext::class)->establecerGlobal();

            return;
        }

        auth()->setUser($quien);
        app(TenantContext::class)->establecerParaUsuario($quien);
    }

    private function competencia(string $nombre): Competencia
    {
        return $this->competencias[$nombre] ??= Competencia::obtenerOCrear($nombre);
    }
}
