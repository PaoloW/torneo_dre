<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportarCsv extends Command
{
    protected $signature = 'torneo:importar
        {ruta : Ruta del archivo CSV}
        {--fresh : Vaciar la tabla participantes antes de importar}';

    protected $description = 'Importa clasificados desde CSV (lee por encabezados; acepta 28 o 31 columnas)';

    /** Encabezado del CSV => columna de la tabla participantes. */
    private const MAPEO = [
        'ID_ANIO' => 'id_anio',
        'ID_FICHA_EVENTO' => 'id_ficha_evento',
        'DRE' => 'dre',
        'UGEL' => 'ugel',
        'REGIONIE' => 'regionie',
        'PROVINCIAIE' => 'provinciaie',
        'DISTRITOIE' => 'distritoie',
        'COD_MOD' => 'cod_mod',
        'COD_MOD_ORIGEN' => 'cod_mod_origen',
        'IE' => 'ie',
        'NIVEL_EDUCATIVO' => 'nivel_educativo',
        'GRADO' => 'grado',
        'CATEGORIA' => 'categoria',
        'DISCIPLINA' => 'disciplina',
        'CLASE_VARIANTE_DE_DISCIPLINA' => 'clase_variante',
        'GENERO' => 'genero',
        'NOMBRE_ETAPA' => 'nombre_etapa',
        'TIPO_PARTICIPANTE' => 'tipo_participante',
        'TIPO_DOCUMENTO' => 'tipo_documento',
        'NUMDOC_PARTICIPANTE' => 'numdoc_participante',
        'APEPAT_PARTICIPANTE' => 'apepat',
        'APEMAT_PARTICIPANTE' => 'apemat',
        'NOMBRES_PARTICIPANTE' => 'nombres',
        'FECHA_NACIMIENTO' => 'fecha_nacimiento',
        'TELEFONO' => 'telefono',
        'FECHA_REGISTRO' => 'fecha_registro',
        'FECHA_ACTUALIZACION' => 'fecha_actualizacion',
        'ESTADO_FICHA' => 'estado_ficha',
        'DSC_PRUEBA' => 'dsc_prueba',
        'METRICA' => 'metrica',
        'MARCA' => 'marca',
    ];

    public function handle(): int
    {
        $ruta = $this->argument('ruta');

        if (! is_file($ruta)) {
            $this->error('No existe el archivo: '.$ruta);

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            DB::table('participantes')->truncate();
            $this->info('Tabla participantes vaciada (--fresh).');
        }

        // Encoding: CP1252/ISO-8859-1 → UTF-8 (+ BOM)
        $contenido = file_get_contents($ruta);
        if (str_starts_with($contenido, "\xEF\xBB\xBF")) {
            $contenido = substr($contenido, 3);
        }
        if (! mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contenido);
        rewind($stream);

        $encabezados = fgetcsv($stream);
        if ($encabezados === false) {
            $this->error('CSV vacío.');

            return self::FAILURE;
        }

        $columnas = [];
        foreach ($encabezados as $i => $titulo) {
            $titulo = strtoupper(trim((string) $titulo));
            if (isset(self::MAPEO[$titulo])) {
                $columnas[$i] = self::MAPEO[$titulo];
            }
        }

        $this->info('Encabezados mapeados: '.count($columnas).' de '.count($encabezados).' columnas.');

        $total = 0;
        $lote = [];

        while (($fila = fgetcsv($stream)) !== false) {
            $registro = [];
            foreach ($columnas as $i => $columna) {
                $valor = trim((string) ($fila[$i] ?? ''));
                $registro[$columna] = $valor === '' ? null : $valor;
            }

            if ($registro === []) {
                continue;
            }

            // numéricos
            foreach (['id_anio', 'id_ficha_evento'] as $numerico) {
                if (isset($registro[$numerico]) && ! is_numeric($registro[$numerico])) {
                    $registro[$numerico] = null;
                }
            }

            // fechas: d/m/Y → DATE; d/m/Y H:i → DATETIME
            $registro['fecha_nacimiento'] = $this->fecha($registro['fecha_nacimiento'] ?? null, 'd/m/Y');
            $registro['fecha_registro'] = $this->fecha($registro['fecha_registro'] ?? null, 'd/m/Y H:i');
            $registro['fecha_actualizacion'] = $this->fecha($registro['fecha_actualizacion'] ?? null, 'd/m/Y H:i');

            $lote[] = $registro;
            $total++;

            if (count($lote) >= 500) {
                DB::table('participantes')->insert($lote);
                $lote = [];
            }
        }

        if ($lote !== []) {
            DB::table('participantes')->insert($lote);
        }

        fclose($stream);

        $this->info('Importados: '.$total.' participantes.');

        return self::SUCCESS;
    }

    private function fecha(?string $valor, string $formato): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $fecha = \DateTime::createFromFormat($formato, $valor);

        if ($fecha === false) {
            return null;
        }

        return $fecha->format($formato === 'd/m/Y' ? 'Y-m-d' : 'Y-m-d H:i:s');
    }
}
