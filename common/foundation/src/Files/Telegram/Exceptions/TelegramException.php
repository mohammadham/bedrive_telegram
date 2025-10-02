<?php

namespace Common\Files\Telegram\Exceptions;

use Exception;

/**
 * Base exception for Telegram operations
 */
class TelegramException extends Exception
{
    protected $context = [];

    public function __construct(
        string $message = "",
        int $code = 0,
        ?Exception $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): void
    {
        $this->context = $context;
    }
}
