<?php
namespace Core;

$libPath = dirname(__DIR__) . '/includes/libs/ExtensionParsedown.php';
if (file_exists($libPath)) { require_once $libPath; }

class Content
{
    public $html;           // El HTML final para imprimir
    public $meta = [];      // Los metadatos (Front Matter)
    public $header = '';    // CSS/JS inyectado para el <head>
    public $footer = '';    // JS inyectado para el <footer>

    private $raw;           // Contenido crudo
    private $config;        // Configuración global
    private $lang;          // Idioma actual

    /**
     * @param string $rawContent El contenido Markdown crudo (de archivo)
     * @param null $unused_pdo   Parámetro eliminado (mantenido null por compatibilidad si fuera necesario)
     * @param array $config      Configuración del sitio
     * @param string $lang       Código de idioma actual
     */
    public function __construct($rawContent, $unused_pdo = null, $config = [], $lang = 'es')
    {
        $this->raw = $rawContent;
        $this->config = $config;
        $this->lang = $lang;

        $this->process();
    }

    private function process()
    {
        // 1. Extraer Front Matter
        if (preg_match('/^---[\r\n]+(.*?)[\r\n]+---[\r\n]+(.*)/s', $this->raw, $matches)) {
            $this->parseMeta($matches[1]);
            $body = $matches[2];
        } else {
            $body = $this->raw;
        }

        // 2. Procesar Snippets ({{archivo.php}}) - SOLO FLAT-FILE
        $body = $this->processSnippets($body);

        // 3. Sustituir variables mágicas
        $body = str_replace('§TITLE', $this->meta['title'] ?? '', $body);
        
        if (isset($this->meta['date'])) {
            $dateFn = $this->config['languages'][$this->lang]['date'] ?? null;
            $timestamp = strtotime($this->meta['date']);
            $formattedDate = ($dateFn && is_callable($dateFn)) ? $dateFn($timestamp) : $this->meta['date'];
            $body = str_replace('§DATE', $formattedDate, $body);
        }

        // 4. Convertir Markdown a HTML
        $pd = new \ExtensionParsedown();
        $this->html = $pd->text($body);
    }

    private function parseMeta($metaText)
    {
        $lines = explode("\n", $metaText);
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $this->meta[strtolower(trim($key))] = trim($value);
            }
        }
    }

    private function processSnippets($text)
    {
        $depth = 0;
        $maxDepth = 5;

        while (strpos($text, '{{') !== false && $depth < $maxDepth) {
            $text = preg_replace_callback('/\{\{(.*?)\}\}/', function($matches) {
                $name = trim($matches[1]);
                $snippetsDir = __DIR__ . '/../snippets/';
                
                // Candidatos a archivos físicos
                $candidates = [
                    $snippetsDir . $name,
                    $snippetsDir . $name . '.php',
                    $snippetsDir . $name . '.md',
                    $snippetsDir . $name . '.html'
                ];

                foreach ($candidates as $path) {
                    if (file_exists($path) && !is_dir($path)) {
                        $ext = pathinfo($path, PATHINFO_EXTENSION);
                        
                        if ($ext === 'php') {
                            ob_start();
                            include $path;
                            return ob_get_clean();
                        }
                        
                        $content = file_get_contents($path);
                        if ($ext === 'md') {
                            $pd = new \ExtensionParsedown();
                            return $pd->text($content);
                        }
                        return $content;
                    }
                }

                // Si no existe el archivo, no hay fallback a BD.
                return "";

            }, $text);
            $depth++;
        }
        return $text;
    }
}
