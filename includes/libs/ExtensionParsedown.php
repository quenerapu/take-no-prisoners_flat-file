<?php
require_once 'Parsedown.php';

class ExtensionParsedown extends Parsedown {

    private $contentDir   = null;
    private $validLangs   = [];
    private $currentLang  = null;
    private $currentSlug  = '';

    /**
     * Proporciona el contexto necesario para detectar enlaces internos rotos.
     * @param string $contentDir  Ruta absoluta a la carpeta content/
     * @param array  $validLangs  Códigos de idioma válidos (ej. ['es','en'])
     * @param string $currentLang Idioma activo de la petición actual
     * @param string $currentSlug Slug de la página actual (sin prefijo de idioma)
     */
    protected function blockFencedCode($Line)
    {
        $Block = parent::blockFencedCode($Line);
        if (!isset($Block)) return $Block;

        $marker       = $Line['text'][0];
        $openerLength = strspn($Line['text'], $marker);
        $infostring   = trim(substr($Line['text'], $openerLength), "\t ");

        // Si hay nombre de archivo después del idioma: ```php archivo.php
        if ($infostring !== '' && preg_match('/^(\S+)\s+(\S+)/', $infostring, $m)) {
            $Block['element']['element']['attributes']['class'] = 'language-' . $m[1];
            $Block['element']['attributes']['data-filename']    = $m[2];
        }

        return $Block;
    }

    public function setContentContext($contentDir, array $validLangs, $currentLang, $currentSlug = '')
    {
        $this->contentDir  = rtrim($contentDir, '/');
        $this->validLangs  = $validLangs;
        $this->currentLang = $currentLang;
        $this->currentSlug = trim($currentSlug, '/');
    }

    protected function inlineLink($Excerpt)
    {
        $Link = parent::inlineLink($Excerpt);
        if (!isset($Link)) { return null; }

        $href = $Link['element']['attributes']['href'] ?? '';
        if ($this->contentDir !== null && $this->isMissingInternalPage($href)) {
            $existing = $Link['element']['attributes']['class'] ?? '';
            $Link['element']['attributes']['class'] = trim($existing . ' link-missing');
        }

        return $Link;
    }

    private function isMissingInternalPage($href)
    {
        if (empty($href)) { return false; }

        // Ignorar anclas puras y URLs con esquema (http, mailto, ftp…)
        if ($href[0] === '#') { return false; }
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9+\-.]*:/', $href)) { return false; }

        $path = parse_url($href, PHP_URL_PATH) ?? $href;
        if (empty($path)) { return false; }

        // Ignorar URLs con extensión (assets, PDFs, imágenes…)
        if (pathinfo($path, PATHINFO_EXTENSION) !== '') { return false; }

        // Normalizar: quitar barras y determinar idioma + slug
        if ($path[0] === '/') {
            $trimmed = trim($path, '/');
            if (empty($trimmed)) { return false; } // raíz del sitio

            $segments = explode('/', $trimmed);
            if (in_array($segments[0], $this->validLangs)) {
                $lang = $segments[0];
                $slug = implode('/', array_slice($segments, 1));
            } else {
                $lang = $this->currentLang;
                $slug = $trimmed;
            }
            // Quitar prefijo ':' de cada segmento (convención wiki)
            $slug = implode('/', array_map(fn($s) => ltrim($s, ':'), explode('/', $slug)));
        } else {
            // URL relativa: en lugar de intentar adivinar si la página actual
            // tiene trailing slash o no (heurística frágil), comprobamos ambas
            // resoluciones posibles y damos verde si la página existe en cualquiera.
            $lang    = $this->currentLang;
            $relPath = trim($path, '/');
            // Quitar prefijo ':' (convención wiki: ':pagina' crea pagina/home.md)
            $relPath = ltrim($relPath, ':');

            if (in_array($relPath, ['search', 'sitemap', 'sitemap.xml'])) { return false; }

            // Resolución 1 — página actual actúa como directory (URL con trailing slash):
            //   relativa se resuelve DENTRO de currentSlug/
            $slug1 = trim($this->currentSlug . '/' . $relPath, '/');

            // Resolución 2 — página actual actúa como flat (URL sin trailing slash):
            //   relativa se resuelve en el directorio PADRE de currentSlug
            $parentDir = dirname($this->currentSlug);
            $slug2     = trim(($parentDir === '.' ? '' : $parentDir . '/') . $relPath, '/');

            $langPart = ($lang !== '') ? $lang . '/' : '';
            foreach (array_unique([$slug1, $slug2]) as $candidate) {
                if (empty($candidate)) { continue; }
                $candidate = str_replace('..', '', $candidate);
                if (empty($candidate)) { continue; }
                $base = $this->contentDir . '/' . $langPart . $candidate;
                if (file_exists($base . '.md') || file_exists($base . '/home.md')) {
                    return false; // existe en al menos una resolución
                }
            }
            return true; // no existe en ninguna resolución
        }

        if (empty($slug)) { return false; }

        // Ignorar rutas virtuales del CMS
        if (in_array($slug, ['search', 'sitemap', 'sitemap.xml'])) { return false; }

        // Prevenir directory traversal
        $slug = str_replace('..', '', $slug);
        if (empty($slug)) { return false; }

        $langPart = ($lang !== '') ? $lang . '/' : '';
        $base = $this->contentDir . '/' . $langPart . $slug;
        return !file_exists($base . '.md') && !file_exists($base . '/home.md');
    }

    function text($text)
    {
        // Inicializar DefinitionData para que lineElements() funcione
        // antes de que parent::text() lo haga por su cuenta.
        $this->DefinitionData = [];
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = $this->convertSeparatorlessTables($text);
        return $this->renderTaskLists(parent::text($text));
    }

    private function renderTaskLists($html)
    {
        // Listas tight: <li>[ ] y <li>[x]
        $html = preg_replace('/<li>\[ \]/', '<li class="task-item"><input type="checkbox" disabled> ', $html);
        $html = preg_replace('/<li>\[x\]/i', '<li class="task-item"><input type="checkbox" checked disabled> ', $html);
        // Listas loose (con <p> interno): <li><p>[ ] y <li><p>[x]
        $html = preg_replace('/<li><p>\[ \]/', '<li class="task-item"><p><input type="checkbox" disabled> ', $html);
        $html = preg_replace('/<li><p>\[x\]/i', '<li class="task-item"><p><input type="checkbox" checked disabled> ', $html);
        return $html;
    }

    /**
     * Normaliza bloques de filas con | antes de pasarlos a Parsedown:
     *
     *   Caso A — separador en posición > 0 (tabla GFM normal con cabecera):
     *            se deja intacto; Parsedown lo procesa con <thead>/<th>.
     *
     *   Caso B — separador en posición 0 (sin cabecera pero con alineación):
     *            se genera HTML con <td> aplicando las alineaciones del separador.
     *
     *   Caso C — sin separador (sin cabecera ni alineación):
     *            se genera HTML con <td> sin estilos de alineación.
     */
    private function convertSeparatorlessTables($text)
    {
        $lines  = explode("\n", $text);
        $result = [];
        $i      = 0;
        $n      = count($lines);

        while ($i < $n) {
            $line = $lines[$i];

            if (isset($line[0]) && $line[0] === '|') {
                // Recoger todas las líneas consecutivas con |
                $block = [];
                $j = $i;
                while ($j < $n && isset($lines[$j][0]) && $lines[$j][0] === '|') {
                    $block[] = $lines[$j];
                    $j++;
                }

                // Localizar la primera fila separadora dentro del bloque
                $separatorIndex = -1;
                foreach ($block as $k => $bline) {
                    if (preg_match('/^\|[\s\-:|]+\|?\s*$/', $bline)) {
                        $separatorIndex = $k;
                        break;
                    }
                }

                if ($separatorIndex > 0) {
                    // Caso A: tabla GFM con cabecera — Parsedown se encarga
                    foreach ($block as $bline) {
                        $result[] = $bline;
                    }
                } elseif ($separatorIndex === 0 && count($block) > 1) {
                    // Caso B: separador primero, sin cabecera, con alineación
                    $alignments = $this->parseSeparatorAlignments($block[0]);
                    $dataRows   = array_slice($block, 1);
                    $result[]   = $this->buildTableHtml($dataRows, $alignments);
                } elseif ($separatorIndex === -1 && count($block) > 1) {
                    // Caso C: sin separador, sin cabecera, sin alineación
                    $result[] = $this->buildTableHtml($block, []);
                } else {
                    // Fila suelta o separador solo — pasar tal cual
                    foreach ($block as $bline) {
                        $result[] = $bline;
                    }
                }

                $i = $j;
            } else {
                $result[] = $line;
                $i++;
            }
        }

        return implode("\n", $result);
    }

    /** Extrae el array de alineaciones de una fila separadora. */
    private function parseSeparatorAlignments($separatorLine)
    {
        $stripped = trim(trim($separatorLine, '|'));
        $cells    = explode('|', $stripped);
        $alignments = [];
        foreach ($cells as $cell) {
            $cell = trim($cell);
            if ($cell === '') { continue; }
            if ($cell[0] === ':' && substr($cell, -1) === ':') {
                $alignments[] = 'center';
            } elseif ($cell[0] === ':') {
                $alignments[] = 'left';
            } elseif (substr($cell, -1) === ':') {
                $alignments[] = 'right';
            } else {
                $alignments[] = null;
            }
        }
        return $alignments;
    }

    /** Construye el HTML completo de una tabla con <td> (sin cabecera). */
    private function buildTableHtml(array $rows, array $alignments)
    {
        $html = '<div class="table-wrapper"><table><tbody>';
        foreach ($rows as $row) {
            $stripped = trim(trim($row, '|'));
            preg_match_all('/(?:(\\\\[|])|[^|`]|`[^`]++`|`)++/', $stripped, $matches);
            $html .= '<tr>';
            foreach ($matches[0] as $k => $cell) {
                $style = (isset($alignments[$k]) && $alignments[$k] !== null)
                    ? ' style="text-align: ' . $alignments[$k] . ';"'
                    : '';
                $html .= '<td' . $style . '>'
                       . $this->elements($this->lineElements(trim($cell)))
                       . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div>';
        return $html;
    }

    protected function blockTableComplete(array $Block)
    {
        $Block['element'] = [
            'name' => 'div',
            'attributes' => ['class' => 'table-wrapper'],
            'element' => $Block['element'],
        ];
        return $Block;
    }

    protected function inlineImage($Excerpt) {
        $Image = parent::inlineImage($Excerpt);
        if (!isset($Image)) { return null; }
        $src = $Image['element']['attributes']['src'];

        if (($pos = strpos($src, '#')) !== false) {
            $urlReal    = substr($src, 0, $pos);
            $hashString = substr($src, $pos + 1);

            if (!empty($hashString)) {
                $Image['element']['attributes']['src'] = $urlReal;
                $classes = str_replace('.', ' ', $hashString);
                if (isset($Image['element']['attributes']['class'])) {
                    $Image['element']['attributes']['class'] .= ' ' . $classes;
                } else {
                    $Image['element']['attributes']['class'] = $classes;
                }
            }
        }

        return $Image;
    }
}
