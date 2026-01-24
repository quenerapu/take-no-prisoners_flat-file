<?php
namespace Core;

class Helpers
{
    /**
     * Limpia y normaliza nombres de archivo (slugs)
     */
    public static function cleanFilename($str)
    {
        if (function_exists('iconv')) {
            $str = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
        }
        $str = strtolower($str);
        $str = preg_replace('/[^a-z0-9\/\.]+/', '-', $str);
        return trim(preg_replace('/-+/', '-', $str), '-');
    }

    /**
     * Elimina carpetas vacías recursivamente
     */
    public static function cleanEmptyFolders($dir, $root)
    {
        if (!is_dir($dir) || $dir == $root) return;
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        if (empty($files)) {
            rmdir($dir);
            self::cleanEmptyFolders(dirname($dir), $root);
        }
    }

    /**
     * Renderiza un snippet de sistema (Header/Footer)
     * SOLO busca en archivos físicos localizados en /snippets/
     */
    public static function renderSystemSnippet($name, $config)
    {
        $snippetsDir = __DIR__ . '/../snippets/';
        $pathPhp = $snippetsDir . $name . '.php';
        $pathHtml = $snippetsDir . $name . '.html';

        // 1. Intento Archivo PHP
        if (file_exists($pathPhp)) {
            include $pathPhp;
            return;
        }
        
        // 2. Intento Archivo HTML
        if (file_exists($pathHtml)) {
            readfile($pathHtml);
            return;
        }

        // El bloque de búsqueda en Base de Datos ha sido eliminado por completo.
    }

    /**
     * Obtiene lista de imágenes en un directorio (útil para galerías flat-file)
     */
    public static function getImagesInDir($dir, $base)
    {
        if (!is_dir($dir)) return [];
        
        $items = scandir($dir);
        $results = [];
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $path = $dir . '/' . $item;
            $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            
            if (!is_dir($path) && in_array($ext, ['jpg','jpeg','png','gif','webp','svg'])) {
                $results[] = [
                    'path' => ($base ? $base . '/' : '') . $item, 
                    'name' => $item
                ];
            }
        }
        return $results;
    }
}