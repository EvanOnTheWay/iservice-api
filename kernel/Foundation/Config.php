<?php
/**
 * Created by PhpStorm.
 * @author Wenpeng
 * @email imwwp@outlook.com
 * @time 2019-04-21 15:40:20
 */

namespace Kernel\Foundation;

class Config
{
    private static $configs = [];

    /**
     * @param string $key
     * @return mixed
     */
    public static function get(string $key)
    {
        if (isset(self::$configs[$key]) === false) {
            $path = ROOT_PATH . '/config/' . $key . '.php';
            self::$configs[$key] = (array)include_once $path;
        }
        return self::$configs[$key];
    }

    public static function env(string $key, $default = null)
    {
        $iniFilePath = ROOT_PATH . '/config.ini';
        if (is_file($iniFilePath)) {
            $items = parse_ini_file($iniFilePath, true);
            if (isset($items[$key])) {
                return $items[$key];
            }
        }

        return $default;
    }
}
