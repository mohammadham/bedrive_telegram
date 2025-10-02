<?php

namespace Common\Files\Telegram\Exceptions;

/**
 * Exception thrown when configuration is invalid
 */
class TelegramConfigException extends TelegramException
{
    public static function missingConfig(string $key): self
    {
        return new self(
            "Missing required configuration: {$key}",
            500,
            null,
            ['missing_key' => $key]
        );
    }

    public static function invalidConfig(string $key, string $reason): self
    {
        return new self(
            "Invalid configuration for {$key}: {$reason}",
            500,
            null,
            ['config_key' => $key, 'reason' => $reason]
        );
    }
}