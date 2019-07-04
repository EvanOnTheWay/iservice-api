<?php
/**
 * Created by PhpStorm.
 * @author Wenpeng
 * @email imwwp@outlook.com
 * @time 2019-04-22 11:51:22
 */

namespace App\Utils;


class ResponseWrapper
{
    public static function success(array $data = [])
    {
        return [
            'code' => 200,
            'data' => $data
        ];
    }

    public static function badRequest(string $message)
    {
        return [
            'code' => 400,
            'message' => $message
        ];
    }

    public static function forbidden(string $message)
    {
        return [
            'code' => 403,
            'message' => $message
        ];
    }

    public static function notFound(string $message)
    {
        return [
            'code' => 404,
            'message' => $message
        ];
    }

    public static function badGateway(string $message)
    {
        return [
            'code' => 502,
            'message' => $message
        ];
    }

    public static function gatewayTimeout(string $message)
    {
        return [
            'code' => 504,
            'message' => $message
        ];
    }
}
