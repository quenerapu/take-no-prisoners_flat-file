<?php
/**
 * Generador de Sitemap XML
 */

// 1. Configuración Básica
header("Content-Type: application/xml; charset=utf-8");
$config = require 'config.php';

// Detectar URL Base si no está en config
if (!isset($config['base_url'])) {
    $basePath = dirname($_SERVER['SCRIPT_NAME']);
    if ($basePath === '/' || $basePath === '\\') $basePath = '';
    $config['base_url'] = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . "://" . $_SERVER['HTTP_HOST'] . $basePath;
}

$urls = [];
$contentDir = __DIR__ . '/content';

// --------------------------------------------------------------------------
// ESCANEO DE ARCHIVOS (Única fuente de datos)
// --------------------------------------------------------------------------
if (is_dir($contentDir)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($contentDir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        // Solo procesamos archivos Markdown
        if ($file->isFile() && strtolower($file->getExtension()) === 'md') {
            
            // Ignorar archivos de sistema (como 404.md)
            if ($file->getBasename('.md') === '404') continue;

            $filepath = $file->getPathname();
            $rawContent = file_get_contents($filepath);

            // A. Verificar si es un borrador (Draft)
            if (preg_match('/^draft:\s*(true|yes|1)/mi', $rawContent)) {
                continue;
            }

            // B. Calcular Ruta Relativa (Slug)
            // Ejemplo: /var/www/content/es/hola.md -> es/hola
            $relativePath = str_replace([realpath($contentDir), '.md', '\\'], ['', '', '/'], $filepath);
            $slug = ltrim($relativePath, '/');

            // C. Calcular Fecha de Modificación (Prioridad FrontMatter 'date')
            $lastMod = $file->getMTime(); 
            if (preg_match('/^date:\s*(.+)$/mi', $rawContent, $m)) {
                $ts = strtotime(trim($m[1]));
                if ($ts) $lastMod = $ts;
            }

            $urls[$slug] = [
                'loc' => $config['base_url'] . '/' . $slug,
                'lastmod' => date('c', $lastMod),
                'changefreq' => 'weekly',
                'priority' => ($slug === 'home' || strpos($slug, '/home') !== false) ? '1.0' : '0.8'
            ];
        }
    }
}

// --------------------------------------------------------------------------
// GENERACIÓN DEL XML
// --------------------------------------------------------------------------
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc><?= $config['base_url'] ?>/</loc>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>

    <?php foreach ($urls as $slug => $data): 
        // Evitamos duplicar la home si ya salió como archivo 'home.md' o 'es/home'
        if (basename($slug) === 'home') continue; 
    ?>
    <url>
        <loc><?= htmlspecialchars($data['loc']) ?></loc>
        <lastmod><?= $data['lastmod'] ?></lastmod>
        <changefreq><?= $data['changefreq'] ?></changefreq>
        <priority><?= $data['priority'] ?></priority>
    </url>
    <?php endforeach; ?>
</urlset>
