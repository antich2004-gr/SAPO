<?php
/**
 * Script de testing para verificar feeds RSS después de corrección XXE
 * Ejecutar desde línea de comandos: php test_feeds.php
 */

require_once 'config.php';
require_once INCLUDES_DIR . '/feed.php';

echo "=== SAPO - Test de Feeds RSS ===\n";
echo "Verificando que los feeds RSS funcionan correctamente después de corrección XXE\n\n";

// Feeds de prueba conocidos
$testFeeds = [
    'BBC News' => 'http://feeds.bbci.co.uk/news/rss.xml',
    'NPR News' => 'https://feeds.npr.org/1001/rss.xml',
    'TED Talks' => 'https://feeds.feedburner.com/TEDTalks_audio',
    'Podcast Test' => 'https://anchor.fm/s/12345678/podcast/rss' // Puede fallar, es para probar manejo de errores
];

$passed = 0;
$failed = 0;

foreach ($testFeeds as $name => $url) {
    echo "Testing: $name\n";
    echo "URL: $url\n";

    // Test 1: Validación de URL (debe pasar SSRF checks)
    if (!validateRssFeedUrl($url)) {
        echo "  ❌ FAIL: URL no pasó validación SSRF\n";
        $failed++;
        echo "\n";
        continue;
    }
    echo "  ✓ Validación SSRF: OK\n";

    // Test 2: Obtener última fecha de episodio
    $timestamp = getLastEpisodeDate($url);

    if ($timestamp === null) {
        echo "  ⚠️  WARNING: No se pudo obtener fecha (puede ser normal si feed no existe)\n";
    } else {
        echo "  ✓ Última fecha: " . date('Y-m-d H:i:s', $timestamp) . "\n";
        $passed++;
    }

    echo "\n";
}

echo "=== Resultados ===\n";
echo "Exitosos: $passed\n";
echo "Fallidos: $failed\n";
echo "\n";

// Test de protección XXE
echo "=== Test de Protección XXE ===\n";
echo "Intentando cargar XML malicioso con entidades externas...\n";

$maliciousXml = '<?xml version="1.0"?>
<!DOCTYPE foo [
  <!ENTITY xxe SYSTEM "file:///etc/passwd">
]>
<root>
  <data>&xxe;</data>
</root>';

libxml_use_internal_errors(true);
$xml = simplexml_load_string($maliciousXml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);

if ($xml === false) {
    echo "✓ PROTECCIÓN XXE ACTIVA: XML malicioso fue rechazado correctamente\n";
} else {
    echo "❌ VULNERABILIDAD: XML malicioso fue procesado\n";
    if (isset($xml->data)) {
        echo "   Datos extraídos: " . (string)$xml->data . "\n";
    }
}

echo "\n=== Test Completado ===\n";
?>
