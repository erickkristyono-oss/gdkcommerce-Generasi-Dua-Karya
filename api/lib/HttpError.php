<?php
declare(strict_types=1);

/** Error yang aman ditampilkan ke pengguna, dengan status HTTP. */
class HttpError extends RuntimeException
{
    public int $status;
    public array $extra;

    public function __construct(string $message, int $status = 400, array $extra = [])
    {
        parent::__construct($message);
        $this->status = $status;
        $this->extra = $extra;
    }
}
