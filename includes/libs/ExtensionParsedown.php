<?php
require_once 'Parsedown.php';

class ExtensionParsedown extends Parsedown {

    function text($text)
    {
        // Inicializar DefinitionData para que lineElements() funcione
        // antes de que parent::text() lo haga por su cuenta.
        $this->DefinitionData = [];
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = $this->convertSeparatorlessTables($text);
        return parent::text($text);
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
