<?php
/**
 * Created by PhpStorm.
 * @author Wenpeng
 * @email imwwp@outlook.com
 * @time 2019-04-21 17:20:47
 */

namespace Kernel\Foundation;

use Throwable;

class Router
{
    /**
     * @var Response
     */
    private $response;

    public function dispatch(Request $request): Response
    {
        $routes = Config::get('route');
        $requestUri = $request->server('request_uri');
        $mappingPath = trim((string)$requestUri, '/');

        $this->response = new Response();

        if (!isset($routes[$mappingPath])) {
            return $this->response->setStatus(Response::HTTP_NOT_FOUND);
        }

        if (!is_callable($routes[$mappingPath])) {
            return $this->response->setStatus(Response::HTTP_METHOD_NOT_ALLOWED);
        }

        try {

            $body = call_user_func($routes[$mappingPath], $request);

            if ($body instanceof Response) {
                return $body;
            }

            if (!is_string($body)) {
                $this->response->setHeader('Content-type', 'application/json; charset=utf-8');
                $body = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $this->prepareHeaders();

            return $this->response->setBody($body);
        } catch (Throwable $exception) {
            if (Config::get('app')['debug']) {
                $this->response->setBody($exception->getMessage() . PHP_EOL . $exception->getTraceAsString());
            }

            return $this->response->setStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @return Response
     */
    public function getResponse(): Response
    {
        return $this->response;
    }

    protected function prepareHeaders()
    {
        $headers = headers_list();
        foreach ($headers as $header) {
            if (strpos($header, ':')) {
                list($key, $val) = explode(':', $header);
                $this->response->setHeader(trim($key), trim($val));
            }
        }
        unset($headers, $header);
    }
}
