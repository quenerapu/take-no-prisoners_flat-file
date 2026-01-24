<?php
/**
 * GENERADOR DE ÍNDICE DE BÚSQUEDA (v4 - FORMATO BONITO)
 * Ejecución: indexer.php?token=TU_TOKEN_SECRETO
 */

// 1. SEGURIDAD Y CONFIGURACIÓN
$config = require __DIR__ . '/../config.php';
$secretToken = 'TU_TOKEN_SECRETO'; // Cambia esto por tu clave personal
$providedToken = $_GET['token'] ?? '';

if ($providedToken !== $secretToken) {
    http_response_code(403);
    die("<h1>Acceso denegado</h1><p>Token de seguridad inválido.</p>");
}

// 2. CARGA DE LIBRERÍAS Y RUTAS
// Importante: Asegúrate de que ExtensionParsedown.php esté en esta ruta
$libPath = dirname(__DIR__) . '/includes/libs/ExtensionParsedown.php';
if (!file_exists($libPath)) {
    die("Error: No se encuentra ExtensionParsedown.php en $libPath");
}
require_once $libPath;

$contentDir = realpath(__DIR__ . '/../content');
$indexFile = $contentDir . '/search_index.json';

if (!$contentDir || !is_dir($contentDir)) {
    die("Error: No se encuentra el directorio de contenido.");
}

$searchIndex = [];
$languages = array_keys($config['languages'] ?? ['es' => []]);
$pd = new \ExtensionParsedown();

// 3. PROCESAMIENTO DE ARCHIVOS POR IDIOMA
foreach ($languages as $lang) {
    $searchIndex[$lang] = [];
    $langPath = $contentDir . DIRECTORY_SEPARATOR . $lang;

    if (!is_dir($langPath)) continue;

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($langPath));

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'md') {
            
            // Excluir página 404
            if ($file->getBasename('.md') === '404') continue;

            $rawContent = file_get_contents($file->getPathname());
            $meta = [];
            $markdownBody = $rawContent;

            // A. Extraer Front Matter
            if (preg_match('/^---[\r\n]+(.*?)[\r\n]+---[\r\n]+(.*)/s', $rawContent, $matches)) {
                $metaText = $matches[1];
                $markdownBody = $matches[2];
                foreach (explode("\n", $metaText) as $line) {
                    if (strpos($line, ':') !== false) {
                        list($k, $v) = explode(':', $line, 2);
                        $meta[strtolower(trim($k))] = trim($v);
                    }
                }
            }

            // B. Sustituir Variables Mágicas (§TITLE, §DATE, §LANG)
            $title = $meta['title'] ?? $file->getBasename('.md');
            $processedBody = str_replace('§TITLE', $title, $markdownBody);
            $processedBody = str_replace('§LANG', $lang, $processedBody);
            
            if (isset($meta['date'])) {
                $dateFn = $config['languages'][$lang]['date'] ?? null;
                $ts = strtotime($meta['date']);
                $formattedDate = ($dateFn && is_callable($dateFn)) ? $dateFn($ts) : $meta['date'];
                $processedBody = str_replace('§DATE', $formattedDate, $processedBody);
            }

            // C. Procesamiento de Snippets ({{archivo.php}})
            $processedBody = preg_replace_callback('/\{\{(.*?)\}\}/', function($m) use ($lang) {
                $sDir = __DIR__ . '/../snippets/';
                $path = $sDir . trim($m[1]);
                if (!strpos($path, '.')) $path .= '.php'; 
                
                if (file_exists($path)) {
                    $ext = pathinfo($path, PATHINFO_EXTENSION);
                    if ($ext === 'php') {
                        ob_start(); include $path; return ob_get_clean();
                    }
                    return file_get_contents($path);
                }
                return "";
            }, $processedBody);

            // D. Renderizado a HTML y limpieza de etiquetas para el índice
            $html = $pd->text($processedBody);
            $cleanText = strip_tags($html);
            $cleanText = preg_replace('/\s+/', ' ', $cleanText);

            // E. Generar el Slug relativo
            $slug = str_replace([$contentDir, '.md', '\\'], ['', '', '/'], $file->getPathname());
            
            $searchIndex[$lang][] = [
                'slug'        => ltrim($slug, '/'),
                'title'       => $title,
                'description' => $meta['description'] ?? '',
                'content'     => mb_substr($cleanText, 0, 5000, 'UTF-8') 
            ];
        }
    }
}

// 4. VALIDACIÓN DE PERMISOS Y ESCRITURA
if (!is_writable($contentDir)) {
    http_response_code(500);
    echo "<h1>❌ Error de Escritura</h1>";
    echo "<p>El servidor no tiene permisos para escribir en <code>/content</code>.</p>";
    echo "<p><strong>Si usas Docker, ejecuta:</strong><br>";
    echo "<code>docker exec -u 0 -it take-no-prisoners_cms chown -R www-data:www-data /var/www/html/content</code></p>";
    exit;
}

$jsonOutput = json_encode($searchIndex, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

if (file_put_contents($indexFile, $jsonOutput)) {
    echo "<h1>✅ Índice actualizado correctamente</h1>";
    echo "<p>Se han indexado los archivos de " . count($languages) . " idioma(s).</p>";
    echo "<p>Archivo generado: <code>content/search_index.json</code></p>";
} else {
    echo "<h1>❌ Error Crítico</h1><p>No se pudo guardar el archivo JSON.</p>";
}
