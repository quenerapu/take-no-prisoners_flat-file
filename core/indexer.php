<?php
/**
 * GENERADOR DE ÍNDICE DE BÚSQUEDA (Refactorizado)
 * Ejecución: indexer.php?token=TU_TOKEN_SECRETO
 */

// 1. SEGURIDAD Y CONFIGURACIÓN
$config = require __DIR__ . '/../config.php';
$secretToken = 'TU_TOKEN_SECRETO'; 
$providedToken = $_GET['token'] ?? '';

if ($providedToken !== $secretToken) {
    http_response_code(403);
    die("<h1>Acceso denegado</h1><p>Token de seguridad inválido.</p>");
}

// 2. CARGA DE DEPENDENCIAS DEL NÚCLEO
require_once __DIR__ . '/Content.php';

$contentDir = realpath(__DIR__ . '/../content');
$indexFile = $contentDir . '/search_index.json';

if (!$contentDir || !is_dir($contentDir)) {
    die("Error: No se encuentra el directorio de contenido.");
}

$searchIndex = [];
$languages = array_keys($config['languages'] ?? []);
$isMonolingual = empty($languages);

// 3. PROCESAMIENTO DE ARCHIVOS
if ($isMonolingual) {
    // Modo monolingüe: contenido directamente en content/
    $searchIndex[''] = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($contentDir));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'md') continue;
        if ($file->getBasename('.md') === '404') continue;
        $rawContent = file_get_contents($file->getPathname());
        $engine = new Core\Content($rawContent, null, $config, '');
        if (isset($engine->meta['draft'])) {
            $draftValue = strtolower(trim($engine->meta['draft']));
            if (in_array($draftValue, ['true', '1', 'yes', ''])) continue;
        }
        $cleanHtml = preg_replace('/<(style|script)\b[^>]*>.*?<\/\1>/is', '', $engine->html);
        $cleanText = trim(preg_replace('/\s+/', ' ', strip_tags($cleanHtml)));
        $slug = ltrim(str_replace([$contentDir, '.md', '\\'], ['', '', '/'], $file->getPathname()), '/');
        $searchIndex[''][] = [
            'slug'        => $slug,
            'title'       => $engine->meta['title'] ?? $file->getBasename('.md'),
            'description' => $engine->meta['description'] ?? '',
            'content'     => mb_substr($cleanText, 0, 5000, 'UTF-8')
        ];
    }
} else {
    // Modo multilingüe: contenido en content/{lang}/
    foreach ($languages as $lang) {
        $searchIndex[$lang] = [];
        $langPath = $contentDir . DIRECTORY_SEPARATOR . $lang;

        if (!is_dir($langPath)) continue;

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($langPath));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'md') {

                if ($file->getBasename('.md') === '404') continue;

                $rawContent = file_get_contents($file->getPathname());
                $engine = new Core\Content($rawContent, null, $config, $lang);

                if (isset($engine->meta['draft'])) {
                    $draftValue = strtolower(trim($engine->meta['draft']));
                    if (in_array($draftValue, ['true', '1', 'yes', ''])) continue;
                }

                $cleanHtml = preg_replace('/<(style|script)\b[^>]*>.*?<\/\1>/is', '', $engine->html);
                $cleanText = trim(preg_replace('/\s+/', ' ', strip_tags($cleanHtml)));

                $slug = ltrim(str_replace([$contentDir, '.md', '\\'], ['', '', '/'], $file->getPathname()), '/');
                $slugParts = explode('/', $slug);
                if ($slugParts[0] === $lang) {
                    array_shift($slugParts);
                    $slug = implode('/', $slugParts);
                }

                $searchIndex[$lang][] = [
                    'slug'        => $slug,
                    'title'       => $engine->meta['title'] ?? $file->getBasename('.md'),
                    'description' => $engine->meta['description'] ?? '',
                    'content'     => mb_substr($cleanText, 0, 5000, 'UTF-8')
                ];
            }
        }
    }
}

// 4. ESCRITURA DEL ÍNDICE
if (!is_writable($contentDir)) {
    http_response_code(500);
    die("Error: No se puede escribir en el directorio de contenido.");
}

$jsonOutput = json_encode($searchIndex, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

if (file_put_contents($indexFile, $jsonOutput)) {
    echo "<h1>✅ Índice actualizado</h1>";
    echo "<p>El índice se ha generado utilizando el motor Core\Content de forma coherente.</p>";
} else {
    echo "<h1>❌ Error Crítico al guardar el índice</h1>";
}