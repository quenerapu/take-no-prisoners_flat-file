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
     * Bloques de filas con | SIN separador GFM → HTML directo con todos <td>.
     * Bloques CON separador → se dejan intactos para que Parsedown los procese
     * con su <thead>/<th> normal.
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

                // ¿Ya tiene fila separadora?
                $hasSeparator = false;
                foreach ($block as $bline) {
                    if (preg_match('/^\|[\s\-:|]+\|?\s*$/', $bline)) {
                        $hasSeparator = true;
                        break;
                    }
                }

                if (!$hasSeparator && count($block) > 1) {
                    // Generar HTML directamente — todas las filas con <td>
                    $html = '<div class="table-wrapper"><table><tbody>';
                    foreach ($block as $bline) {
                        $stripped = trim(trim($bline, '|'));
                        // Mismo patrón de celda que usa Parsedown internamente
                        preg_match_all('/(?:(\\\\[|])|[^|`]|`[^`]++`|`)++/', $stripped, $matches);
                        $html .= '<tr>';
                        foreach ($matches[0] as $cell) {
                            $html .= '<td>' . $this->elements($this->lineElements(trim($cell))) . '</td>';
                        }
                        $html .= '</tr>';
                    }
                    $html .= '</tbody></table></div>';
                    $result[] = $html;
                } else {
                    // Tabla con separador: Parsedown la procesa normalmente
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
