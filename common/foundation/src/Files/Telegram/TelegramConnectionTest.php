<?php

namespace Common\Files\Telegram;

use Telegram\Bot\Api as TelegramBotApi;
use danog\MadelineProto\API as MadelineProtoApi;
use Exception;

/**
 * Test class for verifying Telegram connections
 * This will be used during Phase 1 to verify credentials
 */
class TelegramConnectionTest
{
    /**
     * Test Bot API connection
     *
     * @param string $botToken
     * @return array
     */
    public static function testBotConnection(string $botToken): array
    {
        try {
            $telegram = new TelegramBotApi($botToken);
            $me = $telegram->getMe();
            
            return [
                'success' => true,
                'bot_id' => $me->getId(),
                'bot_username' => $me->getUsername(),
                'bot_name' => $me->getFirstName(),
                'message' => 'Bot API connection successful'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Bot API connection failed'
            ];
        }
    }

    /**
     * Test User Account MTProto connection
     *
     * @param int $apiId
     * @param string $apiHash
     * @param string $phoneNumber
     * @param string $sessionFile
     * @return array
     */
    public static function testUserConnection(
        int $apiId,
        string $apiHash,
        string $phoneNumber,
        string $sessionFile = 'session.madeline'
    ): array {
        try {
            $settings = [
                'app_info' => [
                    'api_id' => $apiId,
                    'api_hash' => $apiHash,
                ],
            ];

            $MadelineProto = new MadelineProtoApi($sessionFile, $settings);
            $MadelineProto->start();

            // Get current user info
            $me = $MadelineProto->getSelf();
            
            return [
                'success' => true,
                'user_id' => $me['id'] ?? null,
                'phone' => $me['phone'] ?? null,
                'username' => $me['username'] ?? null,
                'message' => 'User account connection successful'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'User account connection failed'
            ];
        }
    }

    /**
     * Test sending a message to a channel
     *
     * @param string $botToken
     * @param string $channelId
     * @return array
     */
    public static function testChannelAccess(string $botToken, string $channelId): array
    {
        try {
            $telegram = new TelegramBotApi($botToken);
            
            // Try to send a test message
            $response = $telegram->sendMessage([
                'chat_id' => $channelId,
                'text' => '🔍 Testing channel access...'
            ]);
            
            return [
                'success' => true,
                'message_id' => $response->getMessageId(),
                'message' => 'Channel access verified'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Channel access test failed'
            ];
        }
    }

    /**
     * Full connectivity test
     *
     * @param array $config
     * @return array
     */
    public static function fullTest(array $config): array
    {
        $results = [
            'bot_api' => null,
            'user_account' => null,
            'channel_access' => null,
            'overall_status' => 'pending'
        ];

        // Test Bot API
        if (isset($config['bot_token']) && !empty($config['bot_token'])) {
            $results['bot_api'] = self::testBotConnection($config['bot_token']);
        }

        // Test User Account
        if (
            isset($config['api_id'], $config['api_hash'], $config['phone']) &&
            !empty($config['api_id']) && !empty($config['api_hash'])
        ) {
            $results['user_account'] = self::testUserConnection(
                (int) $config['api_id'],
                $config['api_hash'],
                $config['phone'],
                $config['session_file'] ?? storage_path('app/telegram/session.madeline')
            );
        }

        // Test Channel Access
        if (
            isset($config['bot_token'], $config['channel_id']) &&
            !empty($config['bot_token']) && !empty($config['channel_id'])
        ) {
            $results['channel_access'] = self::testChannelAccess(
                $config['bot_token'],
                $config['channel_id']
            );
        }

        // Determine overall status
        $allSuccess = true;
        foreach ($results as $key => $result) {
            if ($key !== 'overall_status' && is_array($result)) {
                if (!($result['success'] ?? false)) {
                    $allSuccess = false;
                    break;
                }
            }
        }

        $results['overall_status'] = $allSuccess ? 'success' : 'failed';

        return $results;
    }
}
