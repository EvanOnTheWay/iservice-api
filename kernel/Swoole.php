<?php
/**
 * Created by PhpStorm.
 * @author Wenpeng
 * @email imwwp@outlook.com
 * @time 2019-04-21 15:51:43
 */

namespace Kernel;

use App\Scheduler\Scheduler;
use Kernel\Foundation\Config;
use Kernel\Foundation\Router;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

class Swoole
{
    private $config;

    private $pidFile = '/tmp/iservice-api.pid';

    public function __construct()
    {
        $this->config = Config::get('app');
    }

    public function start(): bool
    {
        $server = new Server(
            $this->config['server']['host'],
            $this->config['server']['port']
        );

        $server->set($this->config['server']['swoole']);

        $server->on('Start', [$this, 'onStart']);
        $server->on('Shutdown', [$this, 'onShutdown']);
        $server->on('ManagerStart', [$this, 'onManagerStart']);
        $server->on('WorkerStart', [$this, 'onWorkerStart']);
        $server->on('WorkerError', [$this, 'onWorkerError']);
        $server->on('WorkerStop', [$this, 'onWorkerStop']);
        $server->on('Request', [$this, 'onRequest']);
        $server->on('Task', [$this, 'onTask']);
        $server->on('Finish', [$this, 'onFinish']);

        return $server->start();
    }

    public function shutdown(): bool
    {
        if (is_file($this->pidFile)) {
            $pid = (int)file_get_contents($this->pidFile);
            return posix_kill($pid, SIGTERM);
        }
        return false;
    }

    public function status(): bool
    {
        return !$this->config['debug'] && is_file($this->pidFile);
    }

    public function onStart(Server $server)
    {
        $this->setProcessName('swoole_server_master');

        if (!$this->config['debug']) {
            file_put_contents($this->pidFile, $server->master_pid);
        }

        $this->console('Task 服务已启动');
    }

    public function onShutdown()
    {
        /**
         * 清理 PID 文件
         */
        if (is_file($this->pidFile)) {
            unlink($this->pidFile);
        }

        $this->console('系统已经关机');
    }

    public function onManagerStart()
    {
        $this->setProcessName('swoole_server_manager');
    }

    public function onWorkerStart(Server $server, int $workerId)
    {
        if ($server->taskworker) {
            $title = 'swoole_server_task_' . $workerId;
        } else {
            $title = 'swoole_server_worker_' . $workerId;
        }
        $this->setProcessName($title);

        /**
         * 初始化 Worker 进程实例
         */
        Worker::initialize($server, $workerId);

        /**
         * 为了防止重复调用，仅在 0 号 Worker 中执行回调注册
         */
        if (0 === $workerId) {
            /**
             * 注册定时回调
             */
            $scheduler = $this->config['scheduler'];
            foreach ($scheduler as $interval => $callbacks) {
                $timer = function () use ($callbacks) {
                    foreach ($callbacks as $callback) {
                        $this->callScheduler($callback);
                    }
                };

                Worker::getInstance()
                    ->getServerInstance()
                    ->tick((int)$interval, $timer);
            }
        }

        set_error_handler([$this, 'onErrorHandle'], E_USER_ERROR);

        register_shutdown_function([$this, 'onErrorShutDown']);
    }

    protected function callScheduler(string $scheduler)
    {
        if (is_subclass_of($scheduler, Scheduler::class)) {
            call_user_func([$scheduler::getInstance(), 'handle']);
        }
    }

    public function onWorkerError(Server $server, int $workerId, int $processId, int $exitCode, int $signal)
    {
        $this->console("Worker ERROR:{$exitCode}:{$signal}, Worker:{$workerId} Process:{$processId}");
    }

    public function onWorkerStop(Server $server, int $workerId)
    {
    }

    public function onRequest(Request $request, Response $response)
    {
        $router = new Router();
        $router->dispatch(
            new \Kernel\Foundation\Request($request)
        );

        $appResponse = $router->getResponse();
        $appResponseHeaders = $appResponse->getHeaders();
        foreach ($appResponseHeaders as $key => $val) {
            $response->header($key, $val);
        }

        $response->status($appResponse->getStatus());
        $response->end($appResponse->getBody());
    }

    public function onTask(Server $server, int $taskId, int $fromWorkerId, callable $data)
    {
        if (is_callable($data)) {
            call_user_func($data);
        } else {
            $this->console('无效的异步任务', $data);
        }
    }

    public function onFinish(Server $server, int $taskId, array $result)
    {
    }

    public function onErrorShutdown()
    {
        $error = error_get_last();
        switch ($error['type'] ?? null) {
            case E_ERROR :
            case E_PARSE :
            case E_USER_ERROR:
            case E_CORE_ERROR :
            case E_COMPILE_ERROR :
                break;
            default:
                return;
        }
        $this->console('捕获到致命错误: ', $error);
    }

    public function onErrorHandle(int $errorNo, string $errorStr, string $errorFile, int $errorLine)
    {
        $this->console('捕获到普通错误: ', [
            'code' => $errorNo,
            'message' => $errorStr,
            'file' => $errorFile,
            'line' => $errorLine,
        ]);
    }

    protected function setProcessName($name)
    {
        if (function_exists('swoole_set_process_name') && PHP_OS !== 'Darwin') {
            swoole_set_process_name($name);
        }
    }

    private function console(string $title, $context = null)
    {
        $data = '[' . date('Y-m-d H:i:s') . '] ' . $title . PHP_EOL;
        if ($context !== null) {
            $data .= var_export($context, true) . PHP_EOL;
        }
        echo $data;
    }
}
