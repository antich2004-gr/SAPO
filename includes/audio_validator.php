<?php
// includes/audio_validator.php - Validación de calidad de audios para emisoras

class AudioValidator {

    // Umbrales configurables
    const SILENCE_THRESHOLD_DB   = -50;   // dB para detección de silencio
    const SILENCE_MIN_DURATION   = 0.5;   // segundos mínimos para considerar silencio
    const SILENCE_INTERNAL_ERROR = 5.0;   // segundos de silencio interno para error (vs warning)
    const BITRATE_MIN_KBPS       = 96;    // kbps mínimo aceptable
    const BITRATE_MAX_KBPS       = 320;   // kbps máximo recomendado
    const LUFS_TARGET            = -23.0; // EBU R128 objetivo para radiodifusión
    const LUFS_TOLERANCE         = 6.0;   // ±LU tolerancia aceptable
    const DURATION_MIN_SECONDS   = 10;    // duración mínima razonable
    const DURATION_MAX_SECONDS   = 14400; // 4 horas máximo
    const CLIPPING_ERROR_THRESHOLD = 100; // muestras saturadas para error (vs warning)

    /**
     * Analiza un archivo de audio y devuelve un array de issues.
     * Cada issue: ['type' => string, 'severity' => 'error|warning', 'detail' => string]
     *
     * @param string $filepath Ruta absoluta al archivo
     * @return array Lista de problemas encontrados
     */
    public static function analyze(string $filepath): array {
        if (!file_exists($filepath)) {
            return [['type' => 'file_not_found', 'severity' => 'error', 'detail' => 'Archivo no encontrado']];
        }

        $issues = [];
        $issues = array_merge($issues, self::checkIntegrity($filepath));

        // Si el archivo está corrupto, no seguir analizando
        foreach ($issues as $issue) {
            if ($issue['type'] === 'integrity_error') {
                return $issues;
            }
        }

        $issues = array_merge($issues, self::checkMetadata($filepath));
        $issues = array_merge($issues, self::checkBitrate($filepath));
        $issues = array_merge($issues, self::checkDuration($filepath));
        $issues = array_merge($issues, self::checkCodecFormat($filepath));
        $issues = array_merge($issues, self::checkSampleRate($filepath));
        $issues = array_merge($issues, self::checkSilences($filepath));
        $issues = array_merge($issues, self::checkLoudness($filepath));
        $issues = array_merge($issues, self::checkClipping($filepath));

        return $issues;
    }

    /**
     * Escanea un directorio de forma recursiva y analiza todos los audios.
     *
     * @param string $directory Directorio a escanear
     * @param string $basePath  Prefijo a eliminar de las rutas en el informe
     * @return array  ['files' => [...], 'total_scanned' => int, 'total_issues' => int]
     */
    public static function scanDirectory(string $directory, string $basePath = ''): array {
        $result = [
            'files'         => [],
            'total_scanned' => 0,
            'total_issues'  => 0,
        ];

        if (!is_dir($directory)) {
            return $result;
        }

        $extensions = ['mp3', 'ogg', 'wav', 'm4a', 'flac', 'aac'];

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
            );
        } catch (Exception $e) {
            return $result;
        }

        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, $extensions)) continue;

            $filepath = $file->getPathname();
            $issues   = self::analyze($filepath);

            $result['total_scanned']++;

            if (!empty($issues)) {
                $displayPath = $basePath
                    ? str_replace($basePath, '', $filepath)
                    : $filepath;

                $result['files'][] = [
                    'path'     => $displayPath,
                    'fullpath' => $filepath,
                    'size'     => $file->getSize(),
                    'issues'   => $issues,
                    'severity' => self::maxSeverity($issues),
                ];
                $result['total_issues'] += count($issues);
            }
        }

        // Ordenar: errores primero, luego warnings
        usort($result['files'], function ($a, $b) {
            $order = ['error' => 0, 'warning' => 1];
            return ($order[$a['severity']] ?? 2) <=> ($order[$b['severity']] ?? 2);
        });

        return $result;
    }

    /**
     * Devuelve la severidad máxima de un array de issues.
     */
    public static function maxSeverity(array $issues): string {
        foreach ($issues as $i) {
            if ($i['severity'] === 'error') return 'error';
        }
        return 'warning';
    }

    // ---------------------------------------------------------------
    // CHECK: Integridad del archivo (lo primero)
    // ---------------------------------------------------------------
    private static function checkIntegrity(string $file): array {
        $issues = [];
        $cmd    = sprintf('ffmpeg -v error -i %s -f null - 2>&1', escapeshellarg($file));
        $output = shell_exec($cmd);

        if (!empty(trim($output ?? ''))) {
            // Filtrar líneas de error relevantes (ignorar warnings menores)
            $lines = array_filter(explode("\n", trim($output)), function ($l) {
                return stripos($l, 'error') !== false || stripos($l, 'invalid') !== false
                    || stripos($l, 'corrupt') !== false || stripos($l, 'truncat') !== false;
            });
            if (!empty($lines)) {
                $detail = trim(implode(' | ', array_slice(array_values($lines), 0, 2)));
                $issues[] = [
                    'type'     => 'integrity_error',
                    'severity' => 'error',
                    'detail'   => 'Archivo corrupto o truncado: ' . substr($detail, 0, 200),
                ];
            }
        }
        return $issues;
    }

    // ---------------------------------------------------------------
    // CHECK: Metadatos (title, artist obligatorios; album, date recomendados)
    // ---------------------------------------------------------------
    private static function checkMetadata(string $file): array {
        $issues = [];
        $cmd    = sprintf(
            'ffprobe -v quiet -print_format json -show_format %s 2>/dev/null',
            escapeshellarg($file)
        );
        $data = json_decode(shell_exec($cmd) ?? '{}', true);
        $tags = $data['format']['tags'] ?? [];

        // Normalizar claves a minúsculas
        $normalizedTags = [];
        foreach ($tags as $k => $v) {
            $normalizedTags[strtolower($k)] = $v;
        }

        $required    = ['title', 'artist'];
        $recommended = ['album', 'date'];

        foreach ($required as $tag) {
            if (empty($normalizedTags[$tag])) {
                $issues[] = [
                    'type'     => 'missing_metadata',
                    'severity' => 'error',
                    'detail'   => "Metadato obligatorio ausente: $tag",
                ];
            }
        }
        foreach ($recommended as $tag) {
            if (empty($normalizedTags[$tag])) {
                $issues[] = [
                    'type'     => 'missing_metadata',
                    'severity' => 'warning',
                    'detail'   => "Metadato recomendado ausente: $tag",
                ];
            }
        }
        return $issues;
    }

    // ---------------------------------------------------------------
    // CHECK: Bitrate
    // ---------------------------------------------------------------
    private static function checkBitrate(string $file): array {
        $issues = [];
        $cmd    = sprintf(
            'ffprobe -v quiet -print_format json -show_format %s 2>/dev/null',
            escapeshellarg($file)
        );
        $data    = json_decode(shell_exec($cmd) ?? '{}', true);
        $bitrate = intval(($data['format']['bit_rate'] ?? 0) / 1000);

        if ($bitrate <= 0) return $issues;

        if ($bitrate < self::BITRATE_MIN_KBPS) {
            $issues[] = [
                'type'     => 'low_bitrate',
                'severity' => 'error',
                'detail'   => "Bitrate muy bajo: {$bitrate} kbps (mínimo recomendado: " . self::BITRATE_MIN_KBPS . " kbps)",
            ];
        } elseif ($bitrate > self::BITRATE_MAX_KBPS) {
            $issues[] = [
                'type'     => 'high_bitrate',
                'severity' => 'warning',
                'detail'   => "Bitrate alto: {$bitrate} kbps (máximo recomendado: " . self::BITRATE_MAX_KBPS . " kbps)",
            ];
        }
        return $issues;
    }

    // ---------------------------------------------------------------
    // CHECK: Duración anómala
    // ---------------------------------------------------------------
    private static function checkDuration(string $file): array {
        $issues = [];
        $cmd    = sprintf(
            'ffprobe -v quiet -print_format json -show_format %s 2>/dev/null',
            escapeshellarg($file)
        );
        $data     = json_decode(shell_exec($cmd) ?? '{}', true);
        $duration = floatval($data['format']['duration'] ?? 0);

        if ($duration <= 0) return $issues;

        if ($duration < self::DURATION_MIN_SECONDS) {
            $issues[] = [
                'type'     => 'duration_too_short',
                'severity' => 'error',
                'detail'   => sprintf("Duración anómalamente corta: %.1fs", $duration),
            ];
        } elseif ($duration > self::DURATION_MAX_SECONDS) {
            $issues[] = [
                'type'     => 'duration_too_long',
                'severity' => 'warning',
                'detail'   => "Duración muy larga: " . gmdate('H:i:s', (int) $duration),
            ];
        }
        return $issues;
    }

    // ---------------------------------------------------------------
    // CHECK: Codec vs extensión
    // ---------------------------------------------------------------
    private static function checkCodecFormat(string $file): array {
        $issues = [];
        $cmd    = sprintf(
            'ffprobe -v quiet -print_format json -show_streams %s 2>/dev/null',
            escapeshellarg($file)
        );
        $data  = json_decode(shell_exec($cmd) ?? '{}', true);
        $codec = $data['streams'][0]['codec_name'] ?? '';
        $ext   = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if (empty($codec) || empty($ext)) return $issues;

        // Mapeo extensión → codecs aceptables
        $acceptedCodecs = [
            'mp3'  => ['mp3', 'mp3float'],
            'ogg'  => ['vorbis', 'opus'],
            'wav'  => ['pcm_s16le', 'pcm_s24le', 'pcm_s32le', 'pcm_f32le', 'pcm_u8'],
            'm4a'  => ['aac', 'alac'],
            'flac' => ['flac'],
            'aac'  => ['aac'],
        ];

        if (isset($acceptedCodecs[$ext]) && !in_array($codec, $acceptedCodecs[$ext])) {
            $issues[] = [
                'type'     => 'codec_mismatch',
                'severity' => 'error',
                'detail'   => "Extensión .$ext pero codec detectado: $codec",
            ];
        }
        return $issues;
    }

    // ---------------------------------------------------------------
    // CHECK: Sample rate
    // ---------------------------------------------------------------
    private static function checkSampleRate(string $file): array {
        $issues   = [];
        $cmd      = sprintf(
            'ffprobe -v quiet -print_format json -show_streams %s 2>/dev/null',
            escapeshellarg($file)
        );
        $data = json_decode(shell_exec($cmd) ?? '{}', true);
        $rate = intval($data['streams'][0]['sample_rate'] ?? 0);

        if ($rate <= 0) return $issues;

        $standard = [44100, 48000, 22050, 32000];
        if (!in_array($rate, $standard)) {
            $issues[] = [
                'type'     => 'unusual_sample_rate',
                'severity' => $rate < 22050 ? 'error' : 'warning',
                'detail'   => "Sample rate inusual: {$rate} Hz (estándar: 44100 / 48000 Hz)",
            ];
        }
        return $issues;
    }

    // ---------------------------------------------------------------
    // CHECK: Silencios al inicio, al final e internos
    // ---------------------------------------------------------------
    private static function checkSilences(string $file): array {
        $issues    = [];
        $threshold = self::SILENCE_THRESHOLD_DB;
        $minDur    = self::SILENCE_MIN_DURATION;

        $cmd    = sprintf(
            'ffmpeg -i %s -af silencedetect=noise=%ddB:d=%s -f null - 2>&1',
            escapeshellarg($file),
            $threshold,
            $minDur
        );
        $output = shell_exec($cmd) ?? '';

        preg_match_all('/silence_start: ([\d.]+)/', $output, $starts);
        preg_match_all('/silence_end: ([\d.]+) \| silence_duration: ([\d.]+)/', $output, $ends);

        // Duración total del archivo
        preg_match('/Duration:\s+(\d+):(\d+):([\d.]+)/', $output, $durMatch);
        $totalDuration = isset($durMatch[1])
            ? ($durMatch[1] * 3600 + $durMatch[2] * 60 + floatval($durMatch[3]))
            : 0;

        if (empty($starts[1])) return $issues;

        foreach ($starts[1] as $i => $startStr) {
            $start    = floatval($startStr);
            $end      = floatval($ends[1][$i] ?? $totalDuration);
            $duration = floatval($ends[2][$i] ?? ($totalDuration - $start));

            // Silencio al inicio: empieza antes del primer segundo
            if ($start < 1.0) {
                $issues[] = [
                    'type'     => 'leading_silence',
                    'severity' => 'warning',
                    'detail'   => sprintf("Silencio al inicio: %.2fs", $duration),
                ];
            // Silencio al final: termina cerca del final del archivo
            } elseif ($totalDuration > 0 && ($totalDuration - $end) < 1.0) {
                $issues[] = [
                    'type'     => 'trailing_silence',
                    'severity' => 'warning',
                    'detail'   => sprintf("Silencio al final: %.2fs", $duration),
                ];
            // Silencio interno
            } else {
                $issues[] = [
                    'type'     => 'internal_silence',
                    'severity' => $duration >= self::SILENCE_INTERNAL_ERROR ? 'error' : 'warning',
                    'detail'   => sprintf(
                        "Silencio interno en %s: %.2fs de duración",
                        gmdate('H:i:s', (int) $start),
                        $duration
                    ),
                ];
            }
        }
        return $issues;
    }

    // ---------------------------------------------------------------
    // CHECK: Loudness EBU R128 (-23 LUFS objetivo de radiodifusión)
    // ---------------------------------------------------------------
    private static function checkLoudness(string $file): array {
        $issues = [];
        $cmd    = sprintf(
            'ffmpeg -i %s -af loudnorm=print_format=json -f null - 2>&1',
            escapeshellarg($file)
        );
        $output = shell_exec($cmd) ?? '';

        // ffmpeg escupe el JSON al final del stderr
        if (preg_match('/\{[^}]+\}/s', $output, $match)) {
            $data = json_decode($match[0], true);
            $lufs = floatval($data['input_i'] ?? 0);

            if ($lufs < -70 || $lufs >= 0) return $issues; // valores inválidos

            $diff = abs($lufs - self::LUFS_TARGET);
            if ($diff > self::LUFS_TOLERANCE) {
                $issues[] = [
                    'type'     => 'loudness_out_of_range',
                    'severity' => $diff > 12 ? 'error' : 'warning',
                    'detail'   => sprintf(
                        "Nivel de volumen: %.1f LUFS (objetivo EBU R128: %.1f LUFS ± %.1f LU)",
                        $lufs,
                        self::LUFS_TARGET,
                        self::LUFS_TOLERANCE
                    ),
                ];
            }
        }
        return $issues;
    }

    // ---------------------------------------------------------------
    // CHECK: Clipping / saturación
    // ---------------------------------------------------------------
    private static function checkClipping(string $file): array {
        $issues = [];
        $cmd    = sprintf(
            'ffmpeg -i %s -af astats=metadata=1:reset=1,ametadata=print:key=lavfi.astats.Overall.Number_of_clipping_samples -f null - 2>&1',
            escapeshellarg($file)
        );
        $output = shell_exec($cmd) ?? '';

        preg_match_all('/Number_of_clipping_samples=(\d+)/', $output, $matches);
        $total = array_sum($matches[1] ?? []);

        if ($total > 0) {
            $issues[] = [
                'type'     => 'clipping',
                'severity' => $total >= self::CLIPPING_ERROR_THRESHOLD ? 'error' : 'warning',
                'detail'   => "Muestras saturadas (clipping): $total",
            ];
        }
        return $issues;
    }
}
