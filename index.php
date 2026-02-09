<?php
// -----------------------------------------------------------------------------
// CMS FRONT CONTROLLER (V5.2)
// -----------------------------------------------------------------------------

ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. CARGA DE CONFIGURACIÓN
$config = require 'config.php';

// 2. CARGA DE DEPENDENCIAS
$dependencies = [
    'core/Content.php',
    'core/Request.php',
    'core/Search.php',
    'core/Helpers.php'
];

foreach ($dependencies as $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}

// -----------------------------------------------------------------------------
// INICIALIZACIÓN Y RUTAS
// -----------------------------------------------------------------------------

// CONFIGURACIÓN URL
$basePath = dirname($_SERVER['SCRIPT_NAME']);
if ($basePath === '/' || $basePath === '\\') $basePath = '';
$config['base_url'] = (isset($_SERVER['HTTPS'])?'https':'http')."://$_SERVER[HTTP_HOST]".$basePath;

$requestRaw = urldecode(trim(str_replace($basePath, '', $_SERVER['REQUEST_URI']), '/'));
$slug = str_replace(['..', '.php'], '', explode('?', $requestRaw)[0]);

// DETECCIÓN DE IDIOMA
$validLangs = array_keys($config['languages'] ?? ['es' => []]);
$currentLang = $validLangs[0]; 

if (count($validLangs) > 1) {
    $parts = explode('/', $requestRaw, 2);
    if (in_array($parts[0], $validLangs)) {
        $currentLang = $parts[0];
        $slug = isset($parts[1]) ? explode('?', $parts[1])[0] : '';
    }
}

ob_start();

// -----------------------------------------------------------------------------
// CONTROLADOR DE CONTENIDO
// -----------------------------------------------------------------------------

$htmlContent = '';
$meta = [];
$accumulatedHeader = '';
$accumulatedFooter = '';

if ($slug === 'search') {
    ob_start();
    if (file_exists('includes/search.php')) {
        require 'includes/search.php';
    } else {
        echo "<h1>Módulo de búsqueda no encontrado</h1>";
    }
    $htmlContent = ob_get_clean();
    $qLabel = isset($_GET['q']) ? ': ' . htmlspecialchars($_GET['q']) : '';
    $meta['title'] = 'Búsqueda' . $qLabel;
} 
else {
    $filename = empty($slug) ? 'home' : $slug;
    $currentContentDir = "content/$currentLang/";
    $tryFile = $currentContentDir . $filename . ".md";

    // LÓGICA DE CARPETA ÍNDICE (Corregida para evitar bucles)
    $potentialDir = $currentContentDir . $filename;
    if (!empty($slug) && is_dir($potentialDir)) {
        
        // Obtenemos el path real de la URL para comparar la barra final
        $uriPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Si la URL no termina en barra, redirigimos una sola vez
        if (substr($uriPath, -1) !== '/') {
            header("Location: " . $config['base_url'] . "/" . trim($requestRaw, '/') . "/", true, 301);
            exit;
        }

        $folderHome = $potentialDir . "/home.md";
        if (file_exists($folderHome)) {
            $tryFile = $folderHome;
        }
    }

    if (file_exists($tryFile)) {
        if (class_exists('Core\Content')) {
            $rawContent = file_get_contents($tryFile);
            $engine = new Core\Content($rawContent, null, $config, $currentLang);
            
            $htmlContent = $engine->html;
            $meta = $engine->meta;
            $accumulatedHeader = $engine->header;
            $accumulatedFooter = $engine->footer;
        } else {
            $htmlContent = nl2br(htmlspecialchars(file_get_contents($tryFile)));
        }
    } else {
        http_response_code(404);
        $file404 = "content/$currentLang/404.md";
        if (file_exists($file404)) {
            $engine = new Core\Content(file_get_contents($file404), null, $config, $currentLang);
            $htmlContent = $engine->html;
            $meta = $engine->meta;
        } else {
            $htmlContent = "<h1>404 Not Found</h1>";
            $meta['title'] = "404 Not Found";
        }
    }

    // LÓGICA DE BORRADOR (DRAFT)
    if (isset($meta['draft'])) {
        $draftValue = strtolower(trim($meta['draft']));
        $draftToken = class_exists('Core\Request') ? Core\Request::get('draft', '') : '';

        if (in_array($draftValue, ['true', '1', 'yes', ''])) {
            $isAuthorized = false;
        } 
        else {
            $isAuthorized = ($draftValue === strtolower($draftToken));
        }

        if (!$isAuthorized) {
            http_response_code(404);
            $file404 = "content/$currentLang/404.md";
            if (file_exists($file404)) {
                $engine = new Core\Content(file_get_contents($file404), null, $config, $currentLang);
                $htmlContent = $engine->html;
                $meta = $engine->meta;
            } else {
                $htmlContent = "<h1>404 Not Found</h1>";
                $meta['title'] = "404 Not Found";
            }
            $accumulatedHeader = $accumulatedFooter = '';
        } else {
            $htmlContent = '<div style="background:#fff3cd;padding:15px;border:1px solid #ffeeba;color:#856404;margin-bottom:20px;border-radius:4px;font-family:sans-serif;">👁️ <strong>Modo Previsualización:</strong> Estás viendo un borrador protegido.</div>' . $htmlContent;
        }
    }
}

// -----------------------------------------------------------------------------
// RENDERIZADO DE LA VISTA
// -----------------------------------------------------------------------------

require 'includes/header.php';
?>

<main class="main-content">
    <article>
        <?= $htmlContent ?>
    </article>
</main>

<?php
require 'includes/footer.php';
echo ob_get_clean();
