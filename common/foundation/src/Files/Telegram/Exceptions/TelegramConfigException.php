<?php

namespace Common\Files\Telegram\Exceptions;

/**
 * Exception thrown when configuration is invalid
 */
class TelegramConfigException extends TelegramException
{
    public static function missingConfig(string $key, ?string $hint = null): self
    {
        $message = "Missing required configuration: {$key}";
        if ($hint) {
            $message .= ". {$hint}";
        }
        
        return new self(
            $message,
            500,
            null,
            ['missing_key' => $key, 'hint' => $hint]
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