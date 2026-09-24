<?php

use App\Enums\EstadoEvaluacion;
use App\Enums\EstadoVacante;
use App\Enums\FuenteCompetencia;
use App\Enums\MotorMatching;
use App\Exports\CandidatosExport;
use App\Exports\EvaluacionesExport;
use App\Exports\ExportacionDeListado;
use App\Exports\VacantesExport;
use App\Livewire\Candidatos\Listado as Candidatos;
use App\Livewire\Evaluaciones\Listado as Evaluaciones;
use App\Livewire\Vacantes\Listado as Vacantes;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\Empresa;
use App\Models\Evaluacion;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Repositories\Contracts\CandidatoRepository;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Maatwebsite\Excel\Excel as FormatoExcel;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    Carbon::setTestNow('2026-09-24 09:00');

    $this->nimbus = Empresa::factory()->create(['nombre' => 'Nimbus Corp']);
    $this->marta = User::factory()->deEmpresa($this->nimbus)->reclutador()->create(['name' => 'Marta Solís']);
    $this->backend = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Desarrollador Backend']);
    Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Borrador)->create(['titulo' => 'QA Engineer']);

    $this->elena = Candidato::factory()->create(['nombre' => 'Elena Ruiz', 'email' => 'elena@correo.com']);
    $this->elena->competencias()->attach(Competencia::obtenerOCrear('Java')->id, ['fuente' => FuenteCompetencia::Extraccion->value]);
    $this->postulacion = Postulacion::factory()->paraVacante($this->backend)->create([
        'candidato_id' => $this->elena->id, 'match_score' => 78.4, 'match_motor' => MotorMatching::Http,
    ]);
    Evaluacion::factory()->create([
        'postulacion_id' => $this->postulacion->id,
        'evaluador_id' => $this->marta->id,
        'criterios' => [['nombre' => 'Conocimiento técnico', 'puntaje' => 9], ['nombre' => 'Comunicación', 'puntaje' => 8]],
        'puntaje' => 8.5,
        'estado' => EstadoEvaluacion::Aprobado,
    ]);

    // Datos de otra empresa: no deben exportarse para Nimbus.
    app(TenantContext::class)->ejecutarGlobal(function () {
        $vela = Empresa::factory()->create(['nombre' => 'Vela Studio']);
        $vacante = Vacante::factory()->deEmpresa($vela)->create(['titulo' => 'Product Designer']);
        Postulacion::factory()->paraVacante($vacante)->create(['candidato_id' => Candidato::factory()->create(['nombre' => 'Iván Torres'])->id]);
        // Elena también postuló en Vela: esa postulación no es de Nimbus.
        Postulacion::factory()->paraVacante($vacante)->create(['candidato_id' => $this->elena->id]);
    });

    actuarComo($this->marta);
});

afterEach(fn () => Carbon::setTestNow());

/** Filas que escribiría la exportación (sin encabezado). */
function filasDe(ExportacionDeListado $exportacion): array
{
    return $exportacion->query()->get()
        ->flatMap(function ($modelo) use ($exportacion) {
            $filas = $exportacion->map($modelo);

            return is_array($filas[0] ?? null) ? $filas : [$filas];
        })
        ->all();
}

it('exporta las vacantes de la empresa', function () {
    Excel::fake();

    Livewire::test(Vacantes::class)->call('exportar');

    Excel::assertDownloaded('vacantes-2026-09-24.xlsx', function (VacantesExport $exportacion) {
        return $exportacion->headings() === ['Vacante', 'Empresa', 'Ubicación', 'Modalidad', 'Estado', 'Candidatos', 'Creada', 'Publicada']
            && array_column(filasDe($exportacion), 0) === ['Desarrollador Backend', 'QA Engineer']
            && filasDe($exportacion)[0][4] === 'Abierta'
            && filasDe($exportacion)[0][5] === 1;
    });
});

it('exporta solo lo que se ve con la búsqueda y el filtro vigentes', function () {
    Excel::fake();

    Livewire::test(Vacantes::class)->set('busqueda', 'qa')->call('exportar');

    Excel::assertDownloaded('vacantes-2026-09-24.xlsx', fn (VacantesExport $e) => array_column(filasDe($e), 0) === ['QA Engineer']);
});

it('exporta los candidatos con el match de cada postulación de la empresa', function () {
    Excel::fake();

    Livewire::test(Candidatos::class)->call('exportar');

    Excel::assertDownloaded('candidatos-2026-09-24.xlsx', function (CandidatosExport $exportacion) {
        return filasDe($exportacion) === [
            ['Elena Ruiz', 'elena@correo.com', $this->elena->titular, 'Desarrollador Backend', 'Postulado', 78, 'Servicio de IA', 'Java', '2026-09-24'],
        ];
    });
});

it('exporta las evaluaciones con cada criterio en su columna', function () {
    Excel::fake();

    Livewire::test(Evaluaciones::class)->call('exportar');

    Excel::assertDownloaded('evaluaciones-2026-09-24.xlsx', function (EvaluacionesExport $exportacion) {
        return $exportacion->headings() === [
            'Candidato', 'Vacante', 'Evaluador', 'Conocimiento técnico', 'Comunicación', 'Resolución de problemas', 'Ajuste cultural',
            'Puntaje', 'Estado', 'Comentario', 'Fecha',
        ] && filasDe($exportacion) === [
            ['Elena Ruiz', 'Desarrollador Backend', 'Marta Solís', 9, 8, null, null, 8.5, 'Aprobado', null, '2026-09-24'],
        ];
    });
});

it('escribe los textos como texto, nunca como fórmula', function () {
    $this->elena->update(['nombre' => '=HYPERLINK("https://malicioso.example","Clic")']);

    $archivo = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($archivo, Excel::raw(new CandidatosExport(app(CandidatoRepository::class)->consultaListado()), FormatoExcel::XLSX));
    $celda = IOFactory::load($archivo)->getActiveSheet()->getCell('A2');
    unlink($archivo);

    expect($celda->getDataType())->toBe(DataType::TYPE_STRING)
        ->and($celda->getValue())->toBe('=HYPERLINK("https://malicioso.example","Clic")');
});

it('no exporta sin permiso de ver el listado', function () {
    actuarComo(User::factory()->postulante()->create());

    Livewire::test(Vacantes::class)->assertForbidden();
});
