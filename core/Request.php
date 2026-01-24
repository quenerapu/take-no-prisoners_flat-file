<?php
namespace Core;

class Request
{
    // Obtener valor de $_POST limpio
    public static function post($key, $default = null)
    {
        return isset($_POST[$key]) ? self::clean($_POST[$key]) : $default;
    }

    // Obtener valor de $_GET limpio
    public static function get($key, $default = null)
    {
        return isset($_GET[$key]) ? self::clean($_GET[$key]) : $default;
    }

    // Limpieza profunda recursiva (para arrays y strings)
    private static function clean($data)
    {
        if (is_array($data)) {
            return array_map([self::class, 'clean'], $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}