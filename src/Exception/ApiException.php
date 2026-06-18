<?php

declare(strict_types=1);

namespace Sendara\Exception;

use Throwable;

class ApiException extends SendaraException
{
    private string $errorCode;
    private int $status;
    private ?string $requestId;
    private ?int $retryAfter;

    public function __construct(
        int $status,
        string $errorCode,
        string $message,
        ?string $requestId = null,
        ?int $retryAfter = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $status, $previous);
        $this->status = $status;
        $this->errorCode = $errorCode;
        $this->requestId = $requestId;
        $this->retryAfter = $retryAfter;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    public static function fromResponse(
        int $status,
        string $code,
        string $message,
        ?string $requestId = null,
        ?int $retryAfter = null
    ): self {
        return new self($status, $code, $message, $requestId, $retryAfter);
    }
}
