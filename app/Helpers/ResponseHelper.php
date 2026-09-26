<?php

// app/Helpers/ResponseHelper.php
namespace App\Helpers;

class ResponseHelper
{
    public static function success($message, $data = [], $statusCode = 200)
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    public static function error($message, $statusCode = 400)
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
        ], $statusCode);
    }

    public static function warning($message, $data = [], $statusCode = 200)
    {
        return response()->json([
            'status' => 'warning',
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }
}
