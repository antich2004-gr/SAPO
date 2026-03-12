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
     * Analiza un archivo de audio.
     *
     * $options controla qué checks lentos ejecutar:
     *   'silences'  => true/false   (ffmpeg silencedetect — lento)
     *   'loudness'  => true/false   (ffmpeg loudnorm EBU R128 — muy lento)
     *   'clipping'  => true/false   (ffmpeg astats — lento)
     *
     * Los checks rápidos (ffprobe: metadatos, bitrate, duración, codec,
     * sample rate e integridad) siempre se ejecutan y usan UNA sola llamada.
     *
     * @param string $filepath Ruta absoluta al archivo
     * @param array  $options  Checks lentos a activar
     * @return array Lista de issues encontrados
     */
    public static function analyze(string $filepath, array $options = []): array {
        if (!file_exists($filepath)) {
            return [['type' => 'file_not_found', 'severity' => 'error', 'detail' => 'Archivo no encontrado']];
        }

        $issues = [];

        // ── Check de integridad (una llamada ffmpeg rápida) ──────────────
        $integrity = self::checkIntegrity($filepath);
        $issues    = array_merge($issues, $integrity);

        // Si el archivo está corrupto no seguimos
        foreach ($integrity as $i) {
            if ($i['type'] === 'integrity_error') {
                return $issues;
            }
        }

        // ── Todos los checks rápidos en UNA sola llamada ffprobe ─────────
        $issues = array_merge($issues, self::checkFastProbe($filepath));

        // ── Checks opcionales (lentos, requieren procesar el audio) ──────
        if (!empty($options['silences'])) {
            $issues = array_merge($issues, self::checkSilences($filepath));
        }
        if (!empty($options['loudness'])) {
            $issues = array_merge($issues, self::checkLoudness($filepath));
        }
        if (!empty($options['clipping'])) {
            $issues = array_merge($issues, self::checkClipping($filepath));
        }

        return $issues;
    }

    /**
     * Escanea un directorio de forma recursiva y analiza todos los audios.
     *
     * @param string $directory  Directorio a escanear
     * @param string $basePath   Prefijo a eliminar de las rutas en el informe
     * @param array  $options    Checks lentos a activar (silences, loudness, clipping)
     * @return array
     */
    public static function scanDirectory(string $directory, string $basePath = '', array $options = []): array {
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
            $issues   = self::analyze($filepath, $options);

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
    // CHECK RÁPIDO: integridad del archivo (una llamada ffmpeg breve)
    // ---------------------------------------------------------------
    private static function checkIntegrity(string $file): array {
        $issues = [];
        $cmd    = sprintf('ffmpeg -v error -i %s -f null - 2>&1', escapeshellarg($file));
        $output = shell_exec($cmd) ?? '';

        if (!empty(trim($output))) {
            $lines = array_filter(explode("\n", trim($output)), function ($l) {
                return stripos($l, 'error') !== false
                    || stripos($l, 'invalid') !== false
                    || stripos($l, 'corrupt') !== false
                    || stripos($l, 'truncat') !== false;
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
    // CHECKS RÁPIDOS: una sola llamada ffprobe para todo
    // (metadatos, bitrate, duración, codec, sample rate)
    // ---------------------------------------------------------------
    private static function checkFastProbe(string $file): array {
        $issues = [];

        $cmd  = sprintf(
            'ffprobe -v quiet -print_format json -show_format -show_streams %s 2>/dev/null',
            escapeshellarg($file)
        );
        $json = shell_exec($cmd) ?? '{}';
        $data = json_decode($json, true) ?? [];

        $format  = $data['format']  ?? [];
        $streams = $data['streams'] ?? [];
        $tags    = $format['tags']  ?? [];

        // Normalizar claves de tags a minúsculas
        $normalizedTags = [];
        foreach ($tags as $k => $v) {
            $normalizedTags[strtolower($k)] = $v;
        }

        // ── Metadatos ──────────────────────────────────────────────
        foreach (['title', 'artist'] as $tag) {
            if (empty($normalizedTags[$tag])) {
                $issues[] = [
                    'type'     => 'missing_metadata',
                    'severity' => 'error',
                    'detail'   => "Metadato obligatorio ausente: $tag",
                ];
            }
        }
        foreach (['album', 'date'] as $tag) {
            if (empty($normalizedTags[$tag])) {
                $issues[] = [
                    'type'     => 'missing_metadata',
                    'severity' => 'warning',
                    'detail'   => "Metadato recomendado ausente: $tag",
                ];
            }
        }

        // ── Bitrate ────────────────────────────────────────────────
        $bitrate = intval(($format['bit_rate'] ?? 0) / 1000);
        if ($bitrate > 0) {
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
        }

        // ── Duración ───────────────────────────────────────────────
        $duration = floatval($format['duration'] ?? 0);
        if ($duration > 0) {
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
        }

        // ── Codec vs extensión y sample rate (primer stream de audio) ──
        $audioStream = null;
        foreach ($streams as $s) {
            if (($s['codec_type'] ?? '') === 'audio') {
                $audioStream = $s;
                break;
            }
        }

        if ($audioStream) {
            $codec = $audioStream['codec_name'] ?? '';
            $ext   = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            // Codec vs extensión
            $acceptedCodecs = [
                'mp3'  => ['mp3', 'mp3float'],
                'ogg'  => ['vorbis', 'opus'],
                'wav'  => ['pcm_s16le', 'pcm_s24le', 'pcm_s32le', 'pcm_f32le', 'pcm_u8'],
                'm4a'  => ['aac', 'alac'],
                'flac' => ['flac'],
                'aac'  => ['aac'],
            ];
            if (!empty($codec) && isset($acceptedCodecs[$ext])
                && !in_array($codec, $acceptedCodecs[$ext])) {
                $issues[] = [
                    'type'     => 'codec_mismatch',
                    'severity' => 'error',
                    'detail'   => "Extensión .$ext pero codec detectado: $codec",
                ];
            }

            // Sample rate
            $rate     = intval($audioStream['sample_rate'] ?? 0);
            $standard = [44100, 48000, 22050, 32000];
            if ($rate > 0 && !in_array($rate, $standard)) {
                $issues[] = [
                    'type'     => 'unusual_sample_rate',
                    'severity' => $rate < 22050 ? 'error' : 'warning',
                    'detail'   => "Sample rate inusual: {$rate} Hz (estándar: 44100 / 48000 Hz)",
                ];
            }
        }

        return $issues;
    }

    // ---------------------------------------------------------------
    // CHECK LENTO: Silencios al inicio, al final e internos
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

        preg_match('/Duration:\s+(\d+):(\d+):([\d.]+)/', $output, $durMatch);
        $totalDuration = isset($durMatch[1])
            ? ($durMatch[1] * 3600 + $durMatch[2] * 60 + floatval($durMatch[3]))
            : 0;

        if (empty($starts[1])) return $issues;

        foreach ($starts[1] as $i => $startStr) {
            $start    = floatval($startStr);
            $end      = floatval($ends[1][$i] ?? $totalDuration);
            $duration = floatval($ends[2][$i] ?? ($totalDuration - $start));

            if ($start < 1.0) {
                $issues[] = [
                    'type'     => 'leading_silence',
                    'severity' => 'warning',
                    'detail'   => sprintf("Silencio al inicio: %.2fs", $duration),
                ];
            } elseif ($totalDuration > 0 && ($totalDuration - $end) < 1.0) {
                $issues[] = [
                    'type'     => 'trailing_silence',
                    'severity' => 'warning',
                    'detail'   => sprintf("Silencio al final: %.2fs", $duration),
                ];
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
    // CHECK LENTO: Loudness EBU R128 (-23 LUFS objetivo radiodifusión)
    // ---------------------------------------------------------------
    private static function checkLoudness(string $file): array {
        $issues = [];
        $cmd    = sprintf(
            'ffmpeg -i %s -af loudnorm=print_format=json -f null - 2>&1',
            escapeshellarg($file)
        );
        $output = shell_exec($cmd) ?? '';

        if (preg_match('/\{[^}]+\}/s', $output, $match)) {
            $info = json_decode($match[0], true);
            $lufs = floatval($info['input_i'] ?? 0);

            if ($lufs < -70 || $lufs >= 0) return $issues;

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
    // CHECK LENTO: Clipping / saturación
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
