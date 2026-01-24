<?php
/**
 * GENERADOR DE ÍNDICE DE BÚSQUEDA (v4 - FORMATO BONITO)
 * Ejecución: indexer.php?token=TU_TOKEN_SECRETO
 */

$secretToken = 'TU_TOKEN_SECRETO'; 
$providedToken = $_GET['token'] ?? '';

if ($providedToken !== $secretToken) {
    http_response_code(403);
    die("Acceso denegado: Token inválido.");
}

// 1. CARGA DE CONFIGURACIÓN Y LIBRERÍAS
$config = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/libs/ExtensionParsedown.php';

$contentDir = realpath(__DIR__ . '/../content');
$indexFile = $contentDir . '/search_index.json';

$searchIndex = [];
$languages = array_keys($config['languages'] ?? ['es' => []]);
$pd = new \ExtensionParsedown();

foreach ($languages as $lang) {
    $searchIndex[$lang] = [];
    $langPath = $contentDir . DIRECTORY_SEPARATOR . $lang;
    if (!is_dir($langPath)) continue;

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($langPath));

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'md') {
            
            if ($file->getBasename('.md') === '404') continue;

            $rawContent = file_get_contents($file->getPathname());
            $meta = [];
            $body = $rawContent;

            // A. Extraer Front Matter
            if (preg_match('/^---[\r\n]+(.*?)[\r\n]+---[\r\n]+(.*)/s', $rawContent, $matches)) {
                $metaText = $matches[1];
                $body = $matches[2];
                foreach (explode("\n", $metaText) as $line) {
                    if (strpos($line, ':') !== false) {
                        list($k, $v) = explode(':', $line, 2);
                        $meta[strtolower(trim($k))] = trim($v);
                    }
                }
            }

            // B. Sustituir Variables Mágicas (§TITLE, §DATE...)
            $body = str_replace('§TITLE', $meta['title'] ?? '', $body);
            $body = str_replace('§LANG', $lang, $body);
            
            if (isset($meta['date'])) {
                $dateFn = $config['languages'][$lang]['date'] ?? null;
                $ts = strtotime($meta['date']);
                $formattedDate = ($dateFn && is_callable($dateFn)) ? $dateFn($ts) : $meta['date'];
                $body = str_replace('§DATE', $formattedDate, $body);
            }

            // C. Procesamiento de Snippets ({{archivo.php}})
            $body = preg_replace_callback('/\{\{(.*?)\}\}/', function($m) use ($lang) {
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
            }, $body);

            // D. Renderizado y Limpieza de HTML
            $html = $pd->text($body);
            $cleanText = strip_tags($html);
            $cleanText = preg_replace('/\s+/', ' ', $cleanText);

            $slug = str_replace([$contentDir, '.md', '\\'], ['', '', '/'], $file->getPathname());
            
            $searchIndex[$lang][] = [
                'slug'        => ltrim($slug, '/'),
                'title'       => $meta['title'] ?? $file->getBasename('.md'),
                'description' => $meta['description'] ?? '',
                'content'     => mb_substr($cleanText, 0, 5000, 'UTF-8') 
            ];
        }
    }
}

// 2. GUARDAR CON FORMATO LEGIBLE (Pretty Print y Unicode Real) 
$jsonOutput = json_encode($searchIndex, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

if (file_put_contents($indexFile, $jsonOutput)) {
    echo "<h1>✅ Índice actualizado y formateado</h1>";
    echo "<p>Puedes revisar <code>content/search_index.json</code> para ver el resultado.</p>";
} else {
    echo "<h1>❌ Error de escritura</h1>";
}
