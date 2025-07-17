<?php

namespace Common\Files\Response;

use Common\Files\FileEntry;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TelegramFileResponse implements FileResponse
{
    public function make(FileEntry $entry, array $options): StreamedResponse
    {
        $path = $entry->getStoragePath($options['useThumbnail']);

        $response = new StreamedResponse(function () use ($path) {
            $process = new \Symfony\Component\Process\Process([
                'telegram-upload',
                '--download',
                $path,
                '-'
            ]);

            $process->run(function ($type, $buffer) {
                echo $buffer;
            });
        });

        $this->setHeaders($response, $entry, $options);

        return $response;
    }

    protected function setHeaders(StreamedResponse $response, FileEntry $entry, array $options)
    {
        $response->headers->set('Content-Type', $entry->mime);
        $response->headers->set('Content-Length', $entry->file_size);
        $response->headers->set('Content-Disposition', $options['disposition'] . '; filename="' . $entry->name . '"');
    }
}
