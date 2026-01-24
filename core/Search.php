<?php
namespace Core;

class Search
{
    private $config;
    private $query;
    private $lang;

    /**
     * Constructor 100% Flat-File.
     * El parámetro $unused_pdo se mantiene como null para evitar errores de firma.
     */
    public function __construct($unused_pdo, $config, $lang)
    {
        $this->config = $config;
        $this->lang = $lang;
    }

    /**
     * Realiza la búsqueda. Prioriza el índice JSON si existe.
     */
    public function search($query)
    {
        $this->query = mb_strtolower(trim($query), 'UTF-8');
        if (mb_strlen($this->query) < 2) return [];

        $results = [];

        // 1. Intentar búsqueda por índice JSON (Más rápido)
        $indexPath = __DIR__ . '/../content/search_index.json';
        if (file_exists($indexPath)) {
            $results = $this->searchInIndex($indexPath);
        } 
        
        // 2. Si no hay resultados o no hay índice, escanear archivos directamente
        if (empty($results)) {
            $results = $this->searchFiles();
        }

        return $results;
    }

    /**
     * Busca dentro de un archivo JSON pre-generado.
     */
    private function searchInIndex($path)
    {
        $found = [];
        $data = json_decode(file_get_contents($path), true);
        
        if (!$data || !isset($data[$this->lang])) return [];

        foreach ($data[$this->lang] as $item) {
            if (mb_stripos($item['title'], $this->query) !== false || 
                mb_stripos($item['content'], $this->query) !== false) {
                
                $found[] = [
                    'title'   => $item['title'],
                    'url'     => $this->config['base_url'] . '/' . ltrim($item['slug'], '/'),
                    'excerpt' => $item['description'] ?? '',
                    'source'  => 'index'
                ];
            }
        }
        return $found;
    }

    /**
     * Escaneo recursivo de la carpeta content/ (Fallback)
     */
    private function searchFiles()
    {
        $found = [];
        $baseDir = realpath(__DIR__ . '/../content');
        
        if (!$baseDir || !is_dir($baseDir)) return [];

        $dirIterator = new \RecursiveDirectoryIterator($baseDir, \FilesystemIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($dirIterator);

        // Identificar otros idiomas para excluirlos de la búsqueda actual
        $otherLangs = array_diff(array_keys($this->config['languages'] ?? []), [$this->lang]);

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'md') {
                $filepath = $file->getPathname();
                
                // Filtro de idioma por carpeta
                foreach ($otherLangs as $badLang) {
                    if (strpos($filepath, DIRECTORY_SEPARATOR . $badLang . DIRECTORY_SEPARATOR) !== false) {
                        continue 2;
                    }
                }

                $rawContent = file_get_contents($filepath);
                $filename = $file->getBasename('.md');

                if (mb_stripos($rawContent, $this->query) !== false || mb_stripos($filename, $this->query) !== false) {
                    
                    $title = $filename;
                    if (preg_match('/^Title:\s*(.*)$/mi', $rawContent, $matches)) {
                        $title = trim($matches[1]);
                    }

                    $description = '';
                    if (preg_match('/^Description:\s*(.*)$/mi', $rawContent, $descMatches)) {
                        $description = trim($descMatches[1]);
                    }

                    $relativePath = str_replace([$baseDir, '.md', '\\'], ['', '', '/'], $filepath);
                    $url = $this->config['base_url'] . '/' . ltrim($relativePath, '/');

                    $found[] = [
                        'title'   => $title,
                        'url'     => $url,
                        'excerpt' => $description,
                        'source'  => 'file'
                    ];
                }
            }
        }
        return $found;
    }
}
