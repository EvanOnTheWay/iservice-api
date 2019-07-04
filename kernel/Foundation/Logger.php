<?php
/**
 * Created by PhpStorm.
 * @author Wenpeng
 * @email imwwp@outlook.com
 * @time 2019-04-21 16:13:00
 */

namespace Kernel\Foundation;

class Logger
{
    private static $instance;

    protected static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new Logger();
        }

        return self::$instance;
    }

    /**
     * Debug Log
     *
     * @param string $title
     * @param null $context
     */
    public static function debug(string $title, $context = null)
    {
        self::getInstance()->write('debug', $title, $context);
    }

    /**
     * Warning Log
     *
     * @param string $title
     * @param null $context
     */
    public static function warning(string $title, $context = null)
    {
        self::getInstance()->write('warning', $title, $context);
    }

    /**
     * Info Log
     *
     * @param string $title
     * @param null $context
     */
    public static function info(string $title, $context = null)
    {
        self::getInstance()->write('info', $title, $context);
    }

    /**
     * Error Log
     *
     * @param string $title
     * @param null $context
     */
    public static function error(string $title, $context = null)
    {
        self::getInstance()->write('error', $title, $context);
    }

    /**
     * 写入到日志文件
     *
     * @param string $level
     * @param string $title
     * @param null $extra
     */
    private static function write(string $level, string $title, $extra = null)
    {
        $pid = getmypid();
        $date = date('Y-m-d H:i:s');
        $config = Config::get('app');

        $content = "[{$date}] {$level} - {$pid} - {$title}" . PHP_EOL;;
        if ($extra !== null) {
            if (!is_string($extra)) {
                if ($config['debug']) {
                    $extra = var_export($extra, true);
                } else {
                    $extra = json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }
            $content .= $extra . PHP_EOL;
        }

        if ($config['debug']) {
            echo $content;
        } else {
            if (!is_dir($config['logs_dir'])) {
                @mkdir($config['logs_dir'], 0777, true);
            }

            $file = $config['logs_dir'] . date('Y-m-d') . '.log';
            @file_put_contents($file, $content, FILE_APPEND | LOCK_EX);
        }
    }
}
