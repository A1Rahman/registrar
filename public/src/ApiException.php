<?php
declare(strict_types=1);

/** An error we expect and want to show to the user, with the HTTP status to send. */
class ApiException extends Exception
{
    public function __construct(string $message, private int $status = 400)
    {
        parent::__construct($message);
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}
