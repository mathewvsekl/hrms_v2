<?php

namespace App\Core;

/**
 * BaseApiController
 * 
 * Provides standardized response patterns for RESTful APIs.
 */
abstract class BaseApiController extends Controller
{
    /**
     * Standard success response
     */
    protected function apiSuccess($data = null, string $message = "Success", int $code = 200)
    {
        return $this->jsonResponse([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ], $code);
    }

    /**
     * Standard error response
     */
    protected function apiError(string $message = "Error", int $code = 400, $errors = null)
    {
        return $this->jsonResponse([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s')
        ], $code);
    }

    /**
     * Standard paginated response
     */
    protected function apiPaginated($data, int $total, int $page, int $limit)
    {
        return $this->jsonResponse([
            'status' => 'success',
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ], 200);
    }
}
