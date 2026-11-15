<?php
declare(strict_types=1);

/** Sends JSON with an HTTP status code. */
class Response
{
    public static function json(int $status, array $payload): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
    }
}
