<?php
/**
 * GRIJANDER ADMIN (Local CRUD Tool)
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

$config = require 'config.php';
require_once 'core/Request.php';
require_once 'core/Helpers.php';

$appName    = $config['app_name'] ?? 'Grijander Local';
$contentDir = __DIR__ . '/content';
$mediaDir   = __DIR__ . '/media';
$snippetsDir = __DIR__ . '/snippets';
$self       = basename($_SERVER['PHP_SELF']);

if (!is_dir($snippetsDir)) mkdir($snippetsDir, 0777, true);

function recursiveCopy($src, $dst) {
    if (is_dir($src)) {
        if (!is_dir($dst)) mkdir($dst, 0777, true);
        foreach (scandir($src) as $file) {
            if ($file != "." && $file != "..") recursiveCopy("$src/$file", "$dst/$file");
        }
    } elseif (file_exists($src)) copy($src, $dst);
}

// =============================================================================
//  CONTROLLER (ACCIONES)
// =============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = Core\Request::post('action');

    /**
     * ACCIÓN: run_indexer (Unificada con indexer.php y con limpieza profunda)
     */
    if ($action === 'run_indexer') {
        $searchIndex = [];
        $languages = array_keys($config['languages'] ?? ['es' => []]);
        $libPath = __DIR__ . '/includes/libs/ExtensionParsedown.php';
        
        if (!file_exists($libPath)) {
            header('Content-Type: application/json', true, 500);
            echo json_encode(['status' => 'error', 'message' => 'Librería Parsedown no encontrada']);
            exit;
        }
        
        require_once $libPath;
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
                    $markdownBody = $rawContent;

                    // 1. Extraer Front Matter
                    if (preg_match('/^---[\r\n]+(.*?)[\r\n]+---[\r\n]+(.*)/s', $rawContent, $matches)) {
                        $markdownBody = $matches[2];
                        foreach (explode("\n", $matches[1]) as $line) {
                            if (strpos($line, ':') !== false) {
                                list($k, $v) = explode(':', $line, 2);
                                $meta[strtolower(trim($k))] = trim($v);
                            }
                        }
                    }

                    // 2. FILTRADO DE BORRADORES
                    if (isset($meta['draft'])) {
                        $dv = strtolower(trim($meta['draft']));
                        if (in_array($dv, ['true', '1', 'yes', ''])) continue;
                    }

                    // 3. Procesar contenido (Variables Mágicas y Snippets)
                    $title = $meta['title'] ?? $file->getBasename('.md');
                    $body = str_replace(['§TITLE', '§LANG'], [$title, $lang], $markdownBody);
                    
                    $body = preg_replace_callback('/\{\{(.*?)\}\}/', function($m) use ($snippetsDir) {
                        $name = trim($m[1]);
                        $path = $snippetsDir . '/' . $name;
                        if (!strpos($path, '.')) $path .= '.php';
                        
                        if (file_exists($path)) {
                            if (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
                                ob_start(); include $path; return ob_get_clean();
                            }
                            return file_get_contents($path);
                        }
                        return "";
                    }, $body);

                    // 4. LIMPIEZA PROFUNDA (Igual que en indexer.php)
                    $html = $pd->text($body);
                    // Eliminar bloques de <style> y <script> con su contenido
                    $cleanHtml = preg_replace('/<(style|script)\b[^>]*>.*?<\/\1>/is', '', $html);
                    // Eliminar etiquetas x-header/x-footer
                    $cleanHtml = preg_replace('/<\/?x-(header|footer)[^>]*>/i', '', $cleanHtml);

                    $cleanText = strip_tags($cleanHtml);
                    $cleanText = preg_replace('/\s+/', ' ', $cleanText);
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
        
        file_put_contents($contentDir . '/search_index.json', json_encode($searchIndex, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        header('Content-Type: application/json'); 
        echo json_encode(['status' => 'success']); 
        exit;
    }

    if ($action === 'create' || $action === 'create_folder_media') { 
        $activeTab = Core\Request::get('tab', 'content');
        $base = ($activeTab === 'media') ? $mediaDir : (($activeTab === 'snippets') ? $snippetsDir : $contentDir);
        $inputName = trim(Core\Request::post('new_name'), '/');
        $n = Core\Helpers::cleanFilename($inputName); 
        
        if ($activeTab !== 'media') {
            if ($activeTab === 'snippets' && !preg_match('/\.(md|php|html|htm)$/', $n)) $n .= '.md';
            if ($activeTab === 'content' && substr($n, -3) !== '.md') $n .= '.md'; 
            $fullPath = $base . '/' . $n;
            if (!is_dir(dirname($fullPath))) mkdir(dirname($fullPath), 0777, true);
            if (!file_exists($fullPath)) {
                $ext = pathinfo($n, PATHINFO_EXTENSION);
                if ($activeTab === 'content') {
                    $template = "---\n\nTitle: Nuevo\nDescription: \nDate: ".date('Y-m-d')."\nDraft: true\n\n---\n\n# §TITLE";
                } else {
                    if ($ext === 'php') $template = "<?php echo \"Snippet\"; ?>";
                    elseif (in_array($ext, ['html', 'htm'])) $template = "<x-header>\n    <style></style>\n</x-header>\n\nSnippet\n\n<x-footer>\n    <script></script>\n</x-footer>";
                    else $template = "### Snippet";
                }
                file_put_contents($fullPath, $template);
            }
            header("Location: $self?tab=$activeTab&file=" . urlencode($n));
        } else {
            $fullPath = $base . '/' . $n;
            if (!is_dir($fullPath)) mkdir($fullPath, 0777, true);
            header("Location: $self?tab=media&folder=" . urlencode($n));
        }
        exit;
    }

    if ($action === 'duplicate') {
        $activeTab = Core\Request::post('tab_context') ?: 'content';
        $base = ($activeTab === 'snippets') ? $snippetsDir : $contentDir;
        $srcRelative = Core\Request::post('src');
        $newName = Core\Helpers::cleanFilename(Core\Request::post('new_name'));
        $type = Core\Request::post('item_type'); 
        $srcPath = $base . '/' . $srcRelative;
        $destRelative = ltrim(dirname($srcRelative) . '/' . $newName, './');
        if ($type !== 'folder' && !preg_match('/\.(md|php|html|htm)$/', $destRelative)) {
            $ext = pathinfo($srcRelative, PATHINFO_EXTENSION);
            $destRelative .= '.' . $ext;
        }
        $destPath = $base . '/' . $destRelative;
        if (file_exists($srcPath) && !file_exists($destPath)) {
            if ($type === 'folder') recursiveCopy($srcPath, $destPath);
            else { copy($srcPath, $destPath); $openFolder = trim(dirname($srcRelative), '.'); $openParam = $openFolder ? "&open=" . urlencode($openFolder) : ""; usleep(300000); header("Location: $self?tab=$activeTab&file=" . urlencode($destRelative) . $openParam); }
            exit;
        }
    }

    if ($action === 'save_file') { 
        $activeTab = Core\Request::get('tab', 'content');
        $base = ($activeTab === 'snippets') ? $snippetsDir : $contentDir;
        $file = Core\Request::post('file'); $content = $_POST['content']; $path = $base.'/'.$file; 
        if (trim($content) === '') { if (file_exists($path)) { unlink($path); Core\Helpers::cleanEmptyFolders(dirname($path), $base); } header("Location: $self?tab=$activeTab"); }
        else { file_put_contents($path, $content); usleep(400000); header("Location: $self?tab=$activeTab&file=".urlencode($file)); } exit; 
    }

    if ($action === 'move') {
        $tab = Core\Request::post('type'); $src = Core\Request::post('src'); $dest = trim(Core\Request::post('dest_folder'), '/');
        $base = ($tab === 'media') ? $mediaDir : (($tab === 'snippets') ? $snippetsDir : $contentDir);
        $oldPath = $base . '/' . $src; $basename = basename($src); $newRelative = trim($dest . '/' . $basename, '/'); $newPath = $base . '/' . $newRelative;
        if ($dest && !is_dir($base . '/' . $dest)) mkdir($base . '/' . $dest, 0777, true);
        if (file_exists($oldPath) && !file_exists($newPath)) { rename($oldPath, $newPath); Core\Helpers::cleanEmptyFolders(dirname($oldPath), $base); usleep(300000); }
        $openParam = $dest ? "&open=" . urlencode($dest) : ""; $typeParam = is_dir($newPath) ? 'folder' : 'file';
        header("Location: $self?tab=$tab&$typeParam=" . urlencode($newRelative) . $openParam); exit;
    }

    if ($action === 'rename') { 
        $activeTab = Core\Request::get('tab', 'content');
        $base = ($activeTab === 'snippets') ? $snippetsDir : $contentDir;
        $old = Core\Request::post('old_path'); $src = $base.'/'.$old; 
        $new = Core\Helpers::cleanFilename(Core\Request::post('new_name')); 
        if (Core\Request::post('item_type')==='file' && !preg_match('/\.(md|php|html|htm)$/', $new)) {
            $ext = pathinfo($old, PATHINFO_EXTENSION);
            $new .= '.' . $ext;
        }
        $dst = dirname($src).'/'.$new; 
        if (!file_exists($dst)) { rename($src,$dst); header("Location: $self?tab=$activeTab"); exit; } 
    }

    if ($action === 'upload_image' && !empty($_FILES['file'])) { 
        $f = preg_replace('/[^a-zA-Z0-9\/\-\_]/','', trim(Core\Request::post('upload_folder'))); $t = $mediaDir.($f?'/'.$f:''); 
        if(!is_dir($t)) mkdir($t,0777,true); $fileName = preg_replace('/[^a-zA-Z0-9\.\-\_]/','',basename($_FILES['file']['name']));
        move_uploaded_file($_FILES['file']['tmp_name'], $t.'/'.$fileName); 
        header("Location: $self?tab=media&folder=".urlencode($f)); exit; 
    }
    
    if ($action === 'delete_image') { 
        $file = Core\Request::post('file'); $p = realpath($mediaDir.'/'.$file); 
        if($p && strpos($p,realpath($mediaDir))===0){ unlink($p); Core\Helpers::cleanEmptyFolders(dirname($p),$mediaDir); } 
        header("Location: $self?tab=media&folder=".urlencode(Core\Request::post('redirect_folder'))); exit; 
    }
}

// =============================================================================
//  VIEW LOGIC
// =============================================================================

$activeTab = Core\Request::get('tab', 'content');
$currentFile = urldecode(Core\Request::get('file', '')); 
$currentFolder = trim(urldecode(Core\Request::get('folder', '')), '/');
$editorContent = '';
$fileLastModified = time(); 
$base = ($activeTab === 'snippets') ? $snippetsDir : $contentDir;

if (($activeTab === 'content' || $activeTab === 'snippets') && $currentFile) {
    $filePath = $base . '/' . $currentFile;
    if (file_exists($filePath) && is_file($filePath)) { 
        $editorContent = file_get_contents($filePath); 
        $fileLastModified = filemtime($filePath);
    }
}

function renderTree($dir, $root, $currentSelection, $type = 'content') {
    if (!is_dir($dir)) return;
    $items = scandir($dir); $folders = []; $files = [];
    foreach ($items as $item) { 
        if ($item==='.'||$item==='..'||$item==='search_index.json') continue; 
        if(is_dir($dir.'/'.$item)) $folders[]=$item; 
        else { 
            $ext=strtolower(pathinfo($item,PATHINFO_EXTENSION)); 
            if(($type==='content'&&$ext==='md') || 
               ($type==='snippets' && in_array($ext, ['md', 'php', 'html', 'htm'])) ||
               ($type==='media'&&in_array($ext,['jpg','jpeg','png','gif','webp','svg']))) $files[]=$item; 
        } 
    }
    sort($folders); sort($files); echo '<ul class="file-tree">';
    foreach ($folders as $folder) { 
        $relativePath=ltrim(str_replace('\\', '/', substr($dir.'/'.$folder, strlen($root))), '/'); 
        $isCurrentFolder = ($type === 'media' && $relativePath === $currentSelection);
        echo '<li><div class="tree-row folder-row dropzone '.($isCurrentFolder?'active':'').'" data-path="'.htmlspecialchars($relativePath).'" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)" ondrop="handleDrop(event, \''.htmlspecialchars($relativePath).'\')" onclick="toggleFolderRow(event, this)"><div class="row-left"><i class="fa-solid fa-caret-right arrow"></i>'; 
        echo '<span class="folder-name"><i class="fa-solid fa-folder folder-icon"></i> '.htmlspecialchars($folder).'</span>'; 
        echo '</div><div class="row-actions">'; 
        if ($type!=='media') { 
            echo '<button class="btn-icon" title="Duplicar" onclick="event.stopPropagation(); duplicateItem(\''.htmlspecialchars($relativePath).'\', \'folder\')"><i class="fa-regular fa-copy"></i></button>';
            echo '<button class="btn-icon" onclick="event.stopPropagation(); renameItem(\''.htmlspecialchars($relativePath).'\', \'folder\')"><i class="fa-solid fa-pen"></i></button>';
            echo '<button class="btn-icon" onclick="event.stopPropagation(); createNew(\''.htmlspecialchars($relativePath).'\')"><i class="fa-solid fa-plus"></i></button>'; 
        } else { echo '<button class="btn-icon" onclick="event.stopPropagation(); createNewFolderMedia(\''.htmlspecialchars($relativePath).'\')"><i class="fa-solid fa-folder-plus"></i></button>'; }
        echo '</div></div><div class="folder-content" style="display: none;">'; renderTree($dir.'/'.$folder,$root,$currentSelection,$type); echo '</div></li>'; 
    }
    foreach ($files as $file) { 
        $relativePath=ltrim(str_replace('\\', '/', substr($dir.'/'.$file, strlen($root))), '/'); 
        $isActive=($relativePath===$currentSelection);
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $iconClass = 'fa-regular fa-file';
        if ($type === 'media') $iconClass = 'fa-regular fa-image';
        elseif ($ext === 'php') $iconClass = 'fa-brands fa-php';
        elseif (in_array($ext, ['html', 'htm'])) $iconClass = 'fa-brands fa-html5';

        echo '<li><div class="tree-row file-row '.($isActive?'active':'').'" draggable="true" ondragstart="handleDragStart(event, \''.htmlspecialchars($relativePath).'\')" onclick="handleFileRowClick(event, this)"><div class="row-left"><a href="?tab='.$type.'&file='.urlencode($relativePath).'" class="file-link '.($isActive?'active':'').'"><i class="'.$iconClass.' file-icon"></i> '.htmlspecialchars($file).'</a></div><div class="row-actions">'; 
        if($type!=='media'){
            echo '<button class="btn-icon" title="Duplicar" onclick="event.stopPropagation(); duplicateItem(\''.htmlspecialchars($relativePath).'\', \'file\')"><i class="fa-regular fa-copy"></i></button>';
            echo '<button class="btn-icon" title="Renombrar" onclick="event.stopPropagation(); renameItem(\''.htmlspecialchars($relativePath).'\',\'file\')"><i class="fa-solid fa-pen"></i></button>';
        } echo '</div></div></li>'; 
    } echo '</ul>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($appName) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/easymde/dist/easymde.min.css">
    <style>
        html, body { height: 100%; margin: 0; padding: 0; overflow: hidden; }
        body { font-family: system-ui, sans-serif; display: flex; flex-direction: column; background: #f8fafc; }
        .navbar { background: #fff; border-bottom: 1px solid #e2e8f0; padding: 0 1rem; height: 50px; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
        .nav-links { display: flex; gap: 5px; height: 100%; }
        .nav-item { padding: 0 15px; display: flex; align-items: center; gap: 8px; font-size: 0.9rem; color: #64748b; cursor: pointer; border-bottom: 3px solid transparent; height: 100%; box-sizing: border-box; }
        .nav-item:hover { background: #f1f5f9; color: #2563eb; }
        .nav-item.active { border-bottom-color: #2563eb; color: #2563eb; font-weight: 500; }
        .layout-container { display: flex; flex: 1; overflow: hidden; height: calc(100% - 50px); }
        .sidebar { width: 300px; background: #fff; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; flex-shrink: 0; }
        .sidebar-header, .toolbar { padding: 10px 15px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; font-size: 0.85rem; font-weight: 600; color: #475569; position: relative; overflow: hidden; flex-shrink: 0; }
        .toolbar { background: #fff; height: 50px; }
        .progress-container { position: absolute; bottom: 0; left: 0; width: 100%; height: 3px; background: transparent; display: none; }
        .progress-bar { height: 100%; width: 0; background: #2563eb; }
        @keyframes loading-linear { 0% { width: 0%; } 90% { width: 95%; } 100% { width: 100%; } }
        .is-loading .progress-container { display: block; }
        .is-loading .progress-bar { animation: loading-linear 0.5s forwards; }
        .sidebar-content { flex: 1; overflow-y: auto; padding: 10px; }
        .main { flex: 1; display: flex; flex-direction: column; min-width: 0; background: white; height: 100%; overflow: hidden; }
        #editorForm { display: flex; flex-direction: column; height: 100%; overflow: hidden; margin: 0; }
        .editor-wrapper-container { flex: 1; display: flex; flex-direction: column; overflow: hidden; position: relative; }
        .media-scroll-container { flex: 1; overflow-y: auto; padding: 20px; }
        .btn-icon { background: transparent; border: none; cursor: pointer; color: #94a3b8; padding: 4px; border-radius: 3px; }
        .btn-icon:hover { color: #2563eb; background: #e2e8f0; }
        ul.file-tree { list-style: none; padding-left: 0; margin: 0; }
        ul.file-tree ul.file-tree { padding-left: .5rem; border-left: 1px solid #e2e8f0; margin-left: 6px; }
        .tree-row { display: flex; align-items: center; justify-content: space-between; padding: 2px 8px 0 0; border-radius: 4px; cursor: pointer; font-size: 0.9rem; border: 1px solid transparent; }
        .tree-row:hover { background: #f1f5f9; }
        .tree-row.active { background: #eff6ff; color: #2563eb; }
        .row-left { display: flex; align-items: center; overflow: hidden; gap: 4px; width: 100%; }
        .row-actions { display: flex; gap: 2px; opacity: 0; } 
        .tree-row:hover .row-actions { opacity: 1; }
        .folder-icon { color: #fbbf24; margin-right: 6px; }
        .file-icon { color: #94a3b8; margin: 4px 6px 0 0; font-size: 0.9rem; }
        .file-link { color: inherit; text-decoration: none; display: flex; width: 100%; }
        .file-link.active { font-weight: 600; }
        .drag-over { border: 1px dashed #2563eb !important; background: #eff6ff !important; }
        .btn-main { background: #2563eb; color: white; padding: 6px 14px; border-radius: 4px; border: none; cursor: pointer; font-size: 0.85rem; transition: background 0.2s; }
        .btn-outline { background: transparent; color: #2563eb; border: 1px solid #2563eb; padding: 5px 12px; border-radius: 4px; font-size: 0.8rem; cursor: pointer; }
        .btn-outline:hover { background: #eff6ff; }
        .media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; }
        .media-card { border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; aspect-ratio: 1; }
        .media-card img { width: 100%; height: 100%; object-fit: cover; }
        .upload-card { border: 2px dashed #cbd5e1; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #94a3b8; background: transparent !important; }
        .upload-card:hover { color: #2563eb; border-color: #2563eb; }
        .EasyMDEContainer { flex: 1; display: flex; flex-direction: column; overflow: hidden; height: 100%; }
        .CodeMirror { flex: 1; height: 100% !important; font-family: 'SFMono-Regular', Consolas, monospace !important; font-size: 14px !important; line-height: 1.6 !important; }
        .CodeMirror-scroll { height: 100% !important; min-height: 100px !important; overflow-y: auto !important; overflow-x: hidden !important; padding: 10px; }
        .CodeMirror-line.cm-draft-highlight { color: #ef4444 !important; font-weight: 700 !important; }
        .editor-toolbar button.fa-symbol-trigger { font-family: system-ui, sans-serif !important; font-weight: 700; font-size: 1.1rem; line-height: 1; }
        #symbol-picker { position: absolute; background: white; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); padding: 8px; display: grid; grid-template-columns: repeat(6, 1fr); gap: 4px; z-index: 9999; display: none; }
        .symbol-item { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; cursor: pointer; border-radius: 4px; font-size: 1.2rem; }
        .symbol-item:hover { background: #f1f5f9; color: #2563eb; }
        .cm-header-1 { font-size: 1.4rem !important; color: #2563eb !important; }
        .cm-header-2 { font-size: 1.2rem !important; color: #2563eb !important; }
        .cm-s-easymde .cm-comment { background: #f8fafc !important; color: #64748b !important; }
        .editor-empty-placeholder { position: absolute; top: 60px; left: 20px; font-size: 3rem; color: #e2e8f0; font-weight: 700; pointer-events: none; display: none; z-index: 10; }
        .is-editor-empty .editor-empty-placeholder { display: block; }
        .mode-code .editor-toolbar { display: none !important; }
    </style>
</head>
<body>
<nav class="navbar">
    <div style="display:flex; align-items:center; gap:15px;"><div style="font-weight:700;"><i class="fa-solid fa-code" style="color:#2563eb;"></i> <?= htmlspecialchars($appName) ?> <small style="color:#94a3b8;font-weight:400;">(Local)</small></div><button class="btn-outline" id="btnIndexer" onclick="runIndexer()"><i class="fa-solid fa-magnifying-glass-chart"></i> Actualizar Índice</button></div>
    <div class="nav-links">
        <div class="nav-item <?= $activeTab==='content'?'active':'' ?>" onclick="navTo('content')"><i class="fa-solid fa-file-pen"></i> Contenido</div>
        <div class="nav-item <?= $activeTab==='snippets'?'active':'' ?>" onclick="navTo('snippets')"><i class="fa-solid fa-puzzle-piece"></i> Snippets</div>
        <div class="nav-item <?= $activeTab==='media'?'active':'' ?>" onclick="navTo('media')"><i class="fa-solid fa-images"></i> Media</div>
    </div>
    <div style="font-size: 0.8rem; color: #94a3b8;">v<?= $config['app_version'] ?? '1.0' ?></div>
</nav>

<div class="layout-container">
    <aside class="sidebar">
        <div class="sidebar-header" id="sidebarHeader">
            <span><?= strtoupper($activeTab) ?></span>
            <div style="display:flex; gap:4px;">
                <button class="btn-icon" title="Cerrar todo" onclick="closeAllFolders()"><i class="fa-solid fa-folder-minus"></i></button>
                <?php if($activeTab!=='media'): ?><button class="btn-icon" title="Nuevo archivo (Ctrl+M)" onclick="createNew()"><i class="fa-solid fa-plus"></i></button><?php else: ?><button class="btn-icon" title="Nueva carpeta" onclick="createNewFolderMedia()"><i class="fa-solid fa-folder-plus"></i></button><a href="?tab=media" class="btn-icon"><i class="fa-solid fa-home"></i></a><?php endif; ?>
            </div>
            <div class="progress-container"><div class="progress-bar"></div></div>
        </div>
        <div class="sidebar-content" id="sidebarTree" ondragover="event.preventDefault()" ondrop="handleDrop(event, '')">
            <?php renderTree(($activeTab==='content'?$contentDir:($activeTab==='snippets'?$snippetsDir:$mediaDir)), ($activeTab==='content'?$contentDir:($activeTab==='snippets'?$snippetsDir:$mediaDir)), ($currentFile ?: $currentFolder), $activeTab); ?>
        </div>
    </aside>

    <main class="main">
        <?php if($activeTab!=='media'): ?>
            <form method="post" id="editorForm">
                <div class="toolbar" id="editorToolbar"><span style="font-weight:600;color:#334155;"><?= htmlspecialchars($currentFile ?: 'Selecciona un archivo') ?></span><?php if($currentFile): ?><input type="hidden" name="action" value="save_file"><input type="hidden" name="file" value="<?= htmlspecialchars($currentFile) ?>"><button type="button" id="btnSave" class="btn-main"><i class="fa-solid fa-floppy-disk"></i> Guardar (Ctrl+S)</button><?php endif; ?><div class="progress-container"><div class="progress-bar"></div></div></div>
                <div class="editor-wrapper-container" id="editorWrapper">
                    <?php if($currentFile): ?><textarea id="mdEditor" name="content"><?= htmlspecialchars($editorContent) ?></textarea><div class="editor-empty-placeholder">Documento vacío</div>
                    <?php else: ?><div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#cbd5e1;"><i class="fa-solid fa-feather fa-4x"></i></div><?php endif; ?>
                </div>
                <div id="symbol-picker"></div>
            </form>
        <?php elseif($activeTab==='media'): ?>
            <div class="media-scroll-container">
                <?php if($currentFile): ?>
                    <div style="text-align:center; background:white; padding:20px; border-radius:8px; border:1px solid #e2e8f0;"><img src="media/<?= $currentFile ?>" style="max-height:60vh; max-width:100%;"><div style="margin-top:15px; display:flex; justify-content:center; gap:10px;"><button id="btnCopyPath" class="btn-main" onclick="copyPathToClipboard('/media/<?= $currentFile ?>')"><i class="fa-solid fa-link"></i> Copiar Ruta</button><form method="post" style="display:inline;" onsubmit="return confirm('¿Borrar definitivamente?');"><input type="hidden" name="action" value="delete_image"><input type="hidden" name="file" value="<?= htmlspecialchars($currentFile) ?>"><input type="hidden" name="redirect_folder" value="<?= htmlspecialchars($currentFolder) ?>"><button class="btn-main" style="background:#ef4444;"><i class="fa-solid fa-trash-can"></i> Borrar</button></form></div></div>
                <?php else: ?>
                    <div class="media-grid">
                        <form id="mediaUploadForm" action="?tab=media&folder=<?= urlencode($currentFolder) ?>" method="post" enctype="multipart/form-data" class="media-card upload-card" onclick="document.getElementById('fileInput').click()">
                            <input type="hidden" name="action" value="upload_image"><input type="hidden" name="upload_folder" value="<?= htmlspecialchars($currentFolder) ?>">
                            <input type="file" name="file" id="fileInput" style="display:none" onchange="showLoading('sidebar'); this.form.submit()">
                            <i class="fa-solid fa-cloud-arrow-up fa-3x"></i>
                        </form>
                        <?php foreach(Core\Helpers::getImagesInDir($mediaDir.($currentFolder?'/'.$currentFolder:''),$currentFolder) as $i): ?><div class="media-card"><a href="?tab=media&file=<?= urlencode($i['path']) ?>"><img src="media/<?= $i['path'] ?>" loading="lazy"></a></div><?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<form method="post" id="createForm"><input type="hidden" name="action" value="create"><input type="hidden" name="new_name" id="new_name_input"></form>
<form method="post" id="createMediaFolderForm"><input type="hidden" name="action" value="create_folder_media"><input type="hidden" name="new_name" id="new_name_input_folder"></form>
<form method="post" id="renameForm"><input type="hidden" name="action" value="rename"><input type="hidden" name="old_path" id="rename_old_path"><input type="hidden" name="new_name" id="rename_new_name"><input type="hidden" name="item_type" id="rename_item_type"></form>
<form method="post" id="duplicateForm"><input type="hidden" name="action" value="duplicate"><input type="hidden" name="src" id="dup_src"><input type="hidden" name="new_name" id="dup_new_name"><input type="hidden" name="item_type" id="dup_type"><input type="hidden" name="tab_context" value="<?= $activeTab ?>"></form>
<form method="post" id="moveForm"><input type="hidden" name="action" value="move"><input type="hidden" name="type" id="move_type"><input type="hidden" name="src" id="move_src"><input type="hidden" name="dest_folder" id="move_dest"></form>

<script src="https://unpkg.com/easymde/dist/easymde.min.js"></script>
<script>
    const FILE_KEY = 'grijander_last_file'; 
    const SNIPPET_KEY = 'grijander_last_snippet';
    const MEDIA_KEY = 'grijander_last_media'; 
    const STORAGE_KEY = 'grijander_open_folders';
    const activeTab = "<?= $activeTab ?>";
    const SYMBOLS = ["§", "¶", "†", "‡", "•", "−", "—", "≈", "≠", "≤", "≥", "→", "←", "↑", "↓", "✓", "⚠", "💡", "📁", "🖼", "🔗", "✨", "⭐", "📝"];

    function navTo(tab) {
        if (tab === 'content') { const last = localStorage.getItem(FILE_KEY); window.location.href = last ? `?tab=content&file=${encodeURIComponent(last)}` : '?tab=content'; }
        else if (tab === 'snippets') { const last = localStorage.getItem(SNIPPET_KEY); window.location.href = last ? `?tab=snippets&file=${encodeURIComponent(last)}` : '?tab=snippets'; }
        else if (tab === 'media') { const last = localStorage.getItem(MEDIA_KEY); window.location.href = last ? last : '?tab=media'; }
    }

    <?php if(($activeTab==='content' || $activeTab==='snippets') && $currentFile): ?>
    const fileName = "<?= addslashes($currentFile) ?>";
    const fileExt = fileName.split('.').pop().toLowerCase();
    const isMarkdown = (fileExt === 'md');
    const autosaveId = "<?= md5($currentFile . $fileLastModified) ?>";
    const editorConfig = { element: document.getElementById('mdEditor'), spellChecker: false, status: false, autosave: {enabled: true, uniqueId: autosaveId, delay: 1000} };

    if (isMarkdown) {
        editorConfig.toolbar = ["bold", "italic", "heading", "|", "quote", "unordered-list", "link", "image", "|", { name: "symbol-trigger", action: (editor) => toggleSymbolPicker(editor), className: "fa-symbol-trigger", title: "Insertar símbolos" }, "|", "side-by-side", "fullscreen"];
    } else {
        editorConfig.toolbar = false;
        document.getElementById('editorWrapper').classList.add('mode-code');
    }

    const easyMDE = new EasyMDE(editorConfig);
    const highlightDraft = () => {
        easyMDE.codemirror.eachLine(lineHandle => {
            const text = lineHandle.text;
            const lineNo = easyMDE.codemirror.getLineNumber(lineHandle);
            if (text.startsWith('Draft:')) { easyMDE.codemirror.addLineClass(lineNo, 'text', 'cm-draft-highlight'); } 
            else { easyMDE.codemirror.removeLineClass(lineNo, 'text', 'cm-draft-highlight'); }
        });
    };
    easyMDE.codemirror.on('change', highlightDraft);
    setTimeout(highlightDraft, 100); 
    
    function toggleSymbolPicker(editor) {
        const picker = document.getElementById('symbol-picker'), trigger = document.querySelector('.fa-symbol-trigger');
        if (picker.style.display === 'grid') { picker.style.display = 'none'; } else {
            const rect = trigger.getBoundingClientRect(); picker.style.left = rect.left + 'px'; picker.style.top = rect.bottom + 'px'; picker.style.display = 'grid';
            picker.innerHTML = ''; SYMBOLS.forEach(s => { const item = document.createElement('div'); item.className = 'symbol-item'; item.textContent = s; item.onclick = () => { editor.codemirror.replaceRange(s, editor.codemirror.getCursor()); picker.style.display = 'none'; editor.codemirror.focus(); }; picker.appendChild(item); });
        }
    }
    document.addEventListener('mousedown', (e) => { const picker = document.getElementById('symbol-picker'); if (picker && !picker.contains(e.target) && !e.target.classList.contains('fa-symbol-trigger')) picker.style.display = 'none'; });
    const triggerBtn = document.querySelector('.fa-symbol-trigger');
    if (triggerBtn) triggerBtn.textContent = '§';
    localStorage.setItem(activeTab === 'content' ? FILE_KEY : SNIPPET_KEY, fileName);
    const updatePlaceholder = () => { const wrapper = document.getElementById('editorWrapper'); if (easyMDE.value().trim() === '') wrapper.classList.add('is-editor-empty'); else wrapper.classList.remove('is-editor-empty'); };
    easyMDE.codemirror.on('change', updatePlaceholder); updatePlaceholder();
    function triggerSave() { showLoading('editor'); localStorage.removeItem('smde_' + autosaveId); document.getElementById('editorForm').submit(); }
    document.getElementById('btnSave').addEventListener('click', e => { e.preventDefault(); triggerSave(); });
    <?php elseif($activeTab==='media'): ?>
    localStorage.setItem(MEDIA_KEY, window.location.search);
    function copyPathToClipboard(path) { navigator.clipboard.writeText(path).then(() => { const btn = document.getElementById('btnCopyPath'); const oldText = btn.innerHTML; btn.innerHTML = '<i class="fa-solid fa-check"></i> ¡Copiado!'; btn.style.background = '#22c55e'; setTimeout(() => { btn.innerHTML = oldText; btn.style.background = ''; }, 1000); }); }
    <?php endif; ?>

    document.addEventListener('keydown', e => {
        if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); if (typeof triggerSave === "function") triggerSave(); }
        if ((e.ctrlKey || e.metaKey) && e.key === 'm') { e.preventDefault(); createNew(); }
    });

    function handleDragStart(e, path) { e.dataTransfer.setData("srcPath", path); e.dataTransfer.effectAllowed = "move"; }
    function handleDragOver(e) { e.preventDefault(); e.stopPropagation(); const row = e.currentTarget; if (row.classList.contains('folder-row')) row.classList.add('drag-over'); }
    function handleDragLeave(e) { e.currentTarget.classList.remove('drag-over'); }
    function handleDrop(e, destPath) { e.preventDefault(); e.stopPropagation(); e.currentTarget.classList.remove('drag-over'); const src = e.dataTransfer.getData("srcPath"); if (!src || src === destPath || destPath.startsWith(src + '/')) return; showLoading('sidebar'); const f = document.getElementById('moveForm'); f.type.value = activeTab; f.src.value = src; f.move_dest.value = destPath; f.submit(); }

    async function runIndexer() {
        const btn = document.getElementById('btnIndexer'); btn.disabled = true; 
        const originalText = btn.innerHTML; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Indexando...';
        showLoading('sidebar'); 
        const formData = new FormData(); formData.append('action', 'run_indexer');
        try { 
            const response = await fetch('admin.php', { method: 'POST', body: formData }); 
            if (response.ok) { btn.innerHTML = '✅ Actualizado'; setTimeout(() => { btn.innerHTML = originalText; btn.disabled = false; }, 1500); }
            else { btn.innerHTML = '❌ Error'; setTimeout(() => { btn.innerHTML = originalText; btn.disabled = false; }, 1500); }
        } catch (e) { btn.innerHTML = '❌ Error'; setTimeout(() => { btn.innerHTML = originalText; btn.disabled = false; }, 1500); } 
        finally { document.getElementById('sidebarHeader').classList.remove('is-loading'); }
    }

    function showLoading(target) { if (target === 'sidebar') document.getElementById('sidebarHeader').classList.add('is-loading'); if (target === 'editor') { const et = document.getElementById('editorToolbar'); if (et) et.classList.add('is-loading'); } }
    
    function toggleFolderRow(event, rowElement) { 
        if (event.target.closest('button')) return; 
        const path = rowElement.getAttribute('data-path'), arrow = rowElement.querySelector('.arrow'), content = rowElement.nextElementSibling; 

        if (activeTab === 'media' && !event.target.classList.contains('arrow')) {
            const isActive = rowElement.classList.contains('active');
            if (!isActive) {
                showLoading('sidebar');
                window.location.href = '?tab=media&folder=' + encodeURIComponent(path);
                return;
            }
        }

        if (content) { 
            const isHidden = (content.style.display === 'none'); 
            content.style.display = isHidden ? 'block' : 'none'; 
            arrow.classList.toggle('fa-caret-right', !isHidden); arrow.classList.toggle('fa-caret-down', isHidden); 
            let openFolders = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'); 
            if (isHidden) { if (!openFolders.includes(path)) openFolders.push(path); } 
            else { openFolders = openFolders.filter(p => p !== path); } 
            localStorage.setItem(STORAGE_KEY, JSON.stringify(openFolders)); 
        } 
    }
    
    function closeAllFolders() { localStorage.removeItem(STORAGE_KEY); document.querySelectorAll('.folder-content').forEach(c => c.style.display = 'none'); document.querySelectorAll('.arrow').forEach(a => { a.classList.add('fa-caret-right'); a.classList.remove('fa-caret-down'); }); }

    document.addEventListener('DOMContentLoaded', () => {
        let openFolders = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
        const urlParams = new URLSearchParams(window.location.search);
        const folderFromUrl = urlParams.get('folder');
        if (folderFromUrl) {
            const parts = folderFromUrl.split('/'); let current = '';
            parts.forEach(p => { current = current ? current + '/' + p : p; if(!openFolders.includes(current)) openFolders.push(current); });
        }
        const openFromUrl = urlParams.get('open');
        if (openFromUrl) { openFromUrl.split('|').forEach(f => { if(!openFolders.includes(f)) openFolders.push(f); }); }
        localStorage.setItem(STORAGE_KEY, JSON.stringify(openFolders));
        openFolders.forEach(path => { const row = document.querySelector(`.folder-row[data-path="${path}"]`); if (row) { const content = row.nextElementSibling; if (content) { content.style.display = 'block'; row.querySelector('.arrow').classList.replace('fa-caret-right', 'fa-caret-down'); } } });
        const activeItem = document.querySelector('.tree-row.active'); if (activeItem) activeItem.scrollIntoView({ block: 'center', behavior: 'smooth' });
    });

    function handleFileRowClick(event, rowElement) { if (event.target.closest('button')) return; const link = rowElement.querySelector('.file-link'); if (link) { showLoading('sidebar'); window.location.href = link.href; } }
    function createNew(b=''){ 
        let promptMsg = activeTab === 'snippets' ? "Nombre snippet (incluye .php, .html o .md):" : "Nombre archivo:";
        let n=prompt(promptMsg); if(n && n.trim() !== ""){ showLoading('sidebar'); document.getElementById('new_name_input').value = b ? b+'/'+n : n; document.getElementById('createForm').submit(); } 
    }
    function createNewFolderMedia(b=''){ let n=prompt("Nombre nueva carpeta:"); if(n && n.trim() !== ""){ showLoading('sidebar'); document.getElementById('new_name_input_folder').value = b ? b+'/'+n : n; document.getElementById('createMediaFolderForm').submit(); } }
    function renameItem(p,t){ let o=p.split('/').pop().replace('.md',''); let n=prompt("Renombrar:",o); if(n&&n!==o){ showLoading('sidebar'); document.getElementById('rename_old_path').value=p; document.getElementById('rename_new_name').value=n; document.getElementById('rename_item_type').value=t; document.getElementById('renameForm').submit(); } }
    function duplicateItem(p, t){ 
        let filename = p.split('/').pop();
        let lastDotIndex = filename.lastIndexOf('.');
        let nameOnly = filename;
        let ext = '';
        if (t === 'file' && lastDotIndex !== -1) {
            nameOnly = filename.substring(0, lastDotIndex);
            ext = filename.substring(lastDotIndex); 
        }
        let n = prompt("Nombre duplicado:", nameOnly + '_copy' + ext); 
        if(n && n.trim() !== ""){ 
            showLoading('sidebar'); 
            document.getElementById('dup_src').value = p; 
            document.getElementById('dup_new_name').value = n; 
            document.getElementById('dup_type').value = t; 
            document.getElementById('duplicateForm').submit(); 
        } 
    }
</script>
</body>
</html>
