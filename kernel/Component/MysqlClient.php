<?php
/**
 * Created by PhpStorm.
 * @author Wenpeng
 * @email imwwp@outlook.com
 * @time 2019-04-21 16:19:06
 */

namespace Kernel\Component;

use Kernel\Foundation\Config;
use PDO;
use PDOException;

/**
 * Class MysqlClient
 *
 * @mixin PDO
 * @package Kernel\Component
 */
class MysqlClient
{
    /**
     * @var MysqlClient
     */
    protected static $instance;

    protected $pdo;

    public static function getInstance()
    {
        if (null === static::$instance) {
            self::$instance = new MysqlClient();
        }

        return static::$instance;
    }

    protected function connection()
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        return $this->connect();
    }

    public function connect()
    {
        $config = Config::get('mysql');
        $pdoDsn = "mysql:host={$config['host']};dbname={$config['db_name']}";
        $this->pdo = new PDO($pdoDsn, $config['username'], $config['password']);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $this->pdo;
    }

    public function reconnect()
    {
        $this->pdo = null;
        return $this->connect();
    }

    public function __call($name, array $arguments)
    {
        try {
            $this->connection()
                ->query('SHOW STATUS;')
                ->execute();
        } catch (PDOException $exception) {
            if ($exception->getCode() != 'HY000') {
                throw $exception;
            }
            if (!stristr($exception->getMessage(), 'server has gone away')) {
                throw $exception;
            }

            $this->reconnect();
        }

        return call_user_func_array([$this->connection(), $name], $arguments);
    }
}