<?php
require_once 'Parsedown.php';

class ExtensionParsedown extends Parsedown {

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
            $urlReal = substr($src, 0, $pos);
            $hashString = substr($src, $pos + 1); // Lo que va después del #

            // Si hay algo después del #, lo tratamos como clases
            if (!empty($hashString)) {
                // Asignamos la URL limpia
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
