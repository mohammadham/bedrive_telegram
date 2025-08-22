<?php

namespace App\Http\Controllers\Api;

use App\Models\FileEntry;
use App\Services\Storage\TelegramStorageDriver;
use Common\Core\BaseController;
use Common\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Http;
use Exception;

class TelegramDownloadController extends BaseController
{
    protected $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function startDownload(Request $request, FileEntry $fileEntry)
    {
        $this->authorize('download', $fileEntry);

        if (!$fileEntry->telegramFile) {
            abort(404, 'Telegram file association not found.');
        }

        $sourceChatId = $fileEntry->telegramFile->telegram_chat_id;
        $originalMessageId = $fileEntry->telegramFile->telegram_file_id;
        $targetChatId = 'me'; // Always forward to the bot's own chat for temporary access

        try {
            $botToken = $this->settings->get('telegram.telegram_bot_token');
            if (!$botToken) {
                throw new Exception('Telegram Bot Token is not configured.');
            }

            // Forward the file to the 'me' chat to get a new, temporary message
            $response = Http::post("https://api.telegram.org/bot{$botToken}/forwardMessage", [
                'chat_id' => $targetChatId,
                'from_chat_id' => $sourceChatId,
                'message_id' => $originalMessageId,
            ]);

            if (!$response->successful() || !$response->json('ok')) {
                $error = $response->json('description') ?: 'Failed to forward file for download.';
                Log::error("Telegram forward error for download: {$error}");
                abort(500, 'Could not prepare file for download. Check if the bot is an admin in the source channel.');
            }

            $result = $response->json('result');
            $forwardedMessageId = $result['message_id'];
            $fileId = $this->extractFileIdFromMessage($result);

            if (!$fileId) {
                // If we can't get a file_id, we can't download. Delete the evidence.
                Http::post("https://api.telegram.org/bot{$botToken}/deleteMessage", [
                    'chat_id' => $targetChatId,
                    'message_id' => $forwardedMessageId,
                ]);
                throw new Exception('Could not extract file_id from forwarded message.');
            }
            
            // Generate a new temporary signed URL to the stream method
            $streamUrl = URL::temporarySignedRoute(
                'telegram.download.stream',
                now()->addMinutes(5), // Short-lived URL for the stream
                [
                    'message_id' => $forwardedMessageId,
                    'file_id' => $fileId,
                    'chat_id' => $targetChatId,
                    'filename' => $fileEntry->name,
                ]
            );

            // Redirect the user to the stream URL
            return redirect($streamUrl);

        } catch (Exception $e) {
            Log::error("Error starting Telegram download: " . $e->getMessage());
            abort(500, 'Server error: Could not prepare file for download.');
        }
    }
    
    public function streamFile(Request $request)
    {
        $messageId = $request->input('message_id');
        $fileId = $request->input('file_id');
        $chatId = $request->input('chat_id');
        $filename = $request->input('filename');
        $botToken = $this->settings->get('telegram.telegram_bot_token');

        try {
            // 1. Get the file path from the bot API using the file_id
            $fileInfoResponse = Http::get("https://api.telegram.org/bot{$botToken}/getFile", ['file_id' => $fileId]);

            if (!$fileInfoResponse->successful() || !$fileInfoResponse->json('ok')) {
                $this->cleanup($botToken, $chatId, $messageId);
                abort(404, 'File not found on Telegram for streaming.');
            }

            $filePath = $fileInfoResponse->json('result.file_path');
            $fileUrl = "https://api.telegram.org/file/bot{$botToken}/{$filePath}";
            
            // 2. Stream the file to the user
            $response = new StreamedResponse(function () use ($fileUrl) {
                $remoteStream = fopen($fileUrl, 'r');
                $localStream = fopen('php://output', 'w');
                if ($remoteStream) {
                    stream_copy_to_stream($remoteStream, $localStream);
                    fclose($remoteStream);
                }
                fclose($localStream);
            });

            $response->headers->set('Content-Type', 'application/octet-stream');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
            
            // 3. Set a callback to delete the message after the response is sent
            $response->setCallback(function () use ($botToken, $chatId, $messageId) {
                $this->cleanup($botToken, $chatId, $messageId);
            });

            return $response;

        } catch (Exception $e) {
            Log::error("Error streaming Telegram file: " . $e->getMessage());
            $this->cleanup($botToken, $chatId, $messageId);
            abort(500, 'Could not stream file.');
        }
    }

    /**
     * Extract file_id from a Telegram message object.
     */
    private function extractFileIdFromMessage(array $message): ?string
    {
        if (isset($message['document']['file_id'])) {
            return $message['document']['file_id'];
        }
        if (isset($message['video']['file_id'])) {
            return $message['video']['file_id'];
        }
        if (isset($message['audio']['file_id'])) {
            return $message['audio']['file_id'];
        }
        if (isset($message['photo'])) {
            // For photos, get the largest one
            $photo = end($message['photo']);
            return $photo['file_id'] ?? null;
        }
        if (isset($message['sticker']['file_id'])) {
            return $message['sticker']['file_id'];
        }
        if (isset($message['voice']['file_id'])) {
            return $message['voice']['file_id'];
        }
        if (isset($message['video_note']['file_id'])) {
            return $message['video_note']['file_id'];
        }
        if (isset($message['animation']['file_id'])) {
            return $message['animation']['file_id'];
        }

        return null;
    }

    /**
     * Delete the temporary forwarded message from the 'me' chat.
     */
    private function cleanup(?string $botToken, ?string $chatId, ?int $messageId)
    {
        if ($botToken && $chatId && $messageId) {
            try {
                Http::post("https://api.telegram.org/bot{$botToken}/deleteMessage", [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                ]);
            } catch (Exception $e) {
                Log::error("Failed to cleanup temporary Telegram message {$messageId}: " . $e->getMessage());
            }
        }
    }
}
