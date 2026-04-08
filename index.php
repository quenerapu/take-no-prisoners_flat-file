<?php
// -----------------------------------------------------------------------------
// CMS FRONT CONTROLLER (V5.7) - Intelligent Create & Edit Logic
// -----------------------------------------------------------------------------

$isLocal = str_starts_with($_SERVER['HTTP_HOST'] ?? '', 'localhost') || str_starts_with($_SERVER['HTTP_HOST'] ?? '', '127.');
ini_set('display_errors', $isLocal ? 1 : 0);
error_reporting($isLocal ? E_ALL : 0);

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

$basePath = dirname($_SERVER['SCRIPT_NAME']);
if ($basePath === '/' || $basePath === '\\') $basePath = '';
$config['base_url'] = (isset($_SERVER['HTTPS'])?'https':'http')."://$_SERVER[HTTP_HOST]".$basePath;

$requestRaw = urldecode(trim(str_replace($basePath, '', $_SERVER['REQUEST_URI']), '/'));
$slug = str_replace(['..', '.php'], '', explode('?', $requestRaw)[0]);

$validLangs = array_keys($config['languages'] ?? ['es' => []]);
$currentLang = $validLangs[0];

$parts = explode('/', $requestRaw, 2);
if (in_array($parts[0], $validLangs)) {
    $currentLang = $parts[0];
    $slug = isset($parts[1]) ? explode('?', $parts[1])[0] : '';
} 
elseif (empty($requestRaw) && isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
    $browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
    if (in_array($browserLang, $validLangs) && $browserLang !== $currentLang) {
        header("Location: " . $config['base_url'] . "/" . $browserLang . "/", true, 302);
        exit;
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
$resolvedFilePath = ''; 
$is404 = false;

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

    $potentialDir = $currentContentDir . $filename;
    if (!empty($slug) && is_dir($potentialDir)) {
        $uriPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
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
        $resolvedFilePath = $tryFile;
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
        $is404 = true;
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
        $resolvedFilePath = $tryFile;
    }

    if (isset($meta['draft'])) {
        $draftValue = strtolower(trim($meta['draft']));
        $draftToken = class_exists('Core\Request') ? Core\Request::get('draft', '') : '';
        $isAuthorized = ($draftValue !== 'true' && $draftValue !== '1' && $draftValue !== 'yes' && $draftValue !== '') 
                        ? ($draftValue === strtolower($draftToken)) 
                        : false;

        if (!$isAuthorized) {
            $is404 = true;
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
// LÓGICA DE BOTÓN DE EDICIÓN / CREACIÓN
// -----------------------------------------------------------------------------
$adminLink = '';
$adminLabel = '✎ Edita esta página';
$isAdminPresent = file_exists('admin.php') || is_dir('admin');

if ($isAdminPresent && !empty($resolvedFilePath)) {
    $adminScript = file_exists('admin.php') ? 'admin.php' : 'admin/';
    $relativeFile = str_replace('content/', '', $resolvedFilePath);
    
    if ($is404) {
        $adminLabel = '✚ Crear esta página';
        
        // REGLA DE CREACIÓN: Nivel raíz -> carpeta/home.md | Nivel interior -> carpeta/archivo.md
        if (strpos(trim($slug, '/'), '/') === false && !empty($slug)) {
            $relativeFile = str_replace('.md', '/home.md', $relativeFile);
        }
    }
    
    $adminLink = $config['base_url'] . '/' . $adminScript . '?tab=content&file=' . urlencode($relativeFile);
}

// -----------------------------------------------------------------------------
// RENDERIZADO DE LA VISTA
// -----------------------------------------------------------------------------

require 'includes/header.php';
?>

<main class="main-content">
    <?php if (!empty($adminLink)): ?>
        <div class="admin-edit-bar" style="text-align: right; margin-bottom: 1rem;">
            <a href="<?= $adminLink ?>" class="edit-link" style="font-size: 0.8rem; background: #eee; padding: 5px 10px; border-radius: 3px; text-decoration: none; color: #333; border: 1px solid #ccc;">
                <?= $adminLabel ?>
            </a>
        </div>
    <?php endif; ?>

    <article>
        <?= $htmlContent ?>
    </article>
</main>

<?php
require 'includes/footer.php';
echo ob_get_clean();
