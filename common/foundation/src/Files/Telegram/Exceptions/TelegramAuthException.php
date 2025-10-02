<?php

namespace Common\Files\Telegram\Exceptions;

/**
 * Exception thrown when authentication fails
 */
class TelegramAuthException extends TelegramException
{
    public static function invalidToken(): self
    {
        return new self("Invalid bot token", 401);
    }

    public static function invalidCredentials(string $reason = 'Invalid credentials'): self
    {
        return new self($reason, 401);
    }

    public static function sessionExpired(): self
    {
        return new self("Session expired. Please re-authenticate.", 401);
    }
}