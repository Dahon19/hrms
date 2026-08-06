<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class BaseApiController extends Controller
{
    /**
     * Return a success response.
     */
    protected function successResponse(
        mixed $data = null,
        string $message = 'Success',
        int $statusCode = 200
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return an error response.
     */
    protected function errorResponse(
        string $message = 'Error',
        int $statusCode = 400,
        mixed $errors = null
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return a paginated response.
     */
    protected function paginatedResponse(
        LengthAwarePaginator $paginator,
        string $message = 'Success',
        int $statusCode = 200
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ], $statusCode);
    }

    /**
     * Return a resource response.
     */
    protected function resourceResponse(
        JsonResource $resource,
        string $message = 'Success',
        int $statusCode = 200
    ): JsonResponse {
        return $this->successResponse($resource, $message, $statusCode);
    }

    /**
     * Return a collection response.
     */
    protected function collectionResponse(
        ResourceCollection $collection,
        string $message = 'Success',
        int $statusCode = 200
    ): JsonResponse {
        return $this->successResponse($collection, $message, $statusCode);
    }

    /**
     * Return a created response.
     */
    protected function createdResponse(
        mixed $data = null,
        string $message = 'Resource created successfully'
    ): JsonResponse {
        return $this->successResponse($data, $message, 201);
    }

    /**
     * Return a no content response.
     */
    protected function noContentResponse(string $message = 'Resource deleted successfully'): JsonResponse
    {
        return $this->successResponse(null, $message, 204);
    }

    /**
     * Return a not found response.
     */
    protected function notFoundResponse(string $message = 'Resource not found'): JsonResponse
    {
        return $this->errorResponse($message, 404);
    }

    /**
     * Return an unauthorized response.
     */
    protected function unauthorizedResponse(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->errorResponse($message, 403);
    }

    /**
     * Return an unauthenticated response.
     */
    protected function unauthenticatedResponse(string $message = 'Unauthenticated'): JsonResponse
    {
        return $this->errorResponse($message, 401);
    }

    /**
     * Return a validation error response.
     */
    protected function validationErrorResponse(
        mixed $errors,
        string $message = 'Validation failed'
    ): JsonResponse {
        return $this->errorResponse($message, 422, $errors);
    }
}
