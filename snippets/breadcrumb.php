<?php
/**
 * snippet: breadcrumb.php
 * Versión avanzada: Lee los títulos desde el Front Matter de los archivos .md
 */

// 1. Acceder a las variables del ámbito global
$config = $GLOBALS['config'] ?? [];
$currentLang = $GLOBALS['currentLang'] ?? 'es';
$slug = $GLOBALS['slug'] ?? '';

// 2. Obtener la ruta de la URL actual
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . "://$_SERVER[HTTP_HOST]";

// 3. Limpiar la ruta del basePath
$basePath = dirname($_SERVER['SCRIPT_NAME']);
if ($basePath !== '/' && $basePath !== '\\') {
    $uri = str_replace($basePath, '', $uri);
}

// 4. Trocear por cada / y limpiar segmentos vacíos
$segments = array_values(array_filter(explode('/', trim($uri, '/'))));
$links = [];

// 5. Determinar la URL base del idioma
$langs = $config['languages'] ?? [];
$homeLabel = (isset($langs[$currentLang]['name']) && stripos($langs[$currentLang]['name'], 'engl') !== false) ? 'Home' : 'Inicio';
$langPrefix = '';

if (!empty($segments) && in_array($segments[0], array_keys($langs))) {
    $langPrefix = $segments[0];
    array_shift($segments);
}

$homeUrl = rtrim($baseUrl . $basePath, '/') . ($langPrefix !== '' ? '/' . $langPrefix : '');

if (empty($segments)) {
    $links[] = '<span>' . $homeLabel . '</span>';
} else {
    $links[] = '<a href="' . rtrim($homeUrl, '/') . '/">' . $homeLabel . '</a>';
}

// 6. Construir bloques restantes leyendo los títulos de los archivos .md
$pathAccumulator = '';
$contentBaseDir = __DIR__ . '/../content/' . ($currentLang !== '' ? $currentLang . '/' : '');

foreach ($segments as $index => $segment) {
    $pathAccumulator .= $segment . '/';
    
    // Intentar determinar el título real del archivo .md
    $label = ucwords(str_replace('-', ' ', $segment)); // Fallback por defecto
    
    // Buscamos si es un archivo directo o una carpeta con home.md
    $targetFile = $contentBaseDir . rtrim($pathAccumulator, '/') . '.md';
    if (!file_exists($targetFile)) {
        $targetFile = $contentBaseDir . rtrim($pathAccumulator, '/') . '/home.md';
    }

    // Si el archivo existe, extraemos el Title del Front Matter
    if (file_exists($targetFile)) {
        $content = file_get_contents($targetFile);
        if (preg_match('/^Title:\s*(.*)$/mi', $content, $matches)) {
            $label = trim($matches[1]);
        }
    }

    if ($index === array_key_last($segments)) {
        $links[] = '<span>' . $label . '</span>';
    } else {
        $fullPath = rtrim($homeUrl, '/') . '/' . ltrim($pathAccumulator, '/');
        $links[] = '<a href="' . rtrim($fullPath, '/') . '/">' . $label . '</a>';
    }
}

// 7. Imprimir resultado
echo '<nav class="breadcrumb" style="margin-bottom: 2rem; font-size: 0.9rem; color: var(--text-light);">';
echo implode(' <span class="separator">»</span> ', $links);
echo '</nav>';