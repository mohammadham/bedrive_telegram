<?php

namespace Common\Files\Providers;

use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Config;
use Telegram\Upload\Client;

class TelegramAdapter extends AbstractAdapter
{
    protected $client;

    public function __construct($api_id, $api_hash, $channel)
    {
        $this->client = new Client($api_id, $api_hash);
        $this->client->connect();
        $this->channel = $channel;
    }

    public function write($path, $contents, Config $config)
    {
        $this->client->send_file($this->channel, $contents);
        return true;
    }

    public function writeStream($path, $resource, Config $config)
    {
        //
    }

    public function update($path, $contents, Config $config)
    {
        //
    }

    public function updateStream($path, $resource, Config $config)
    {
        //
    }

    public function rename($path, $newpath)
    {
        //
    }

    public function copy($path, $newpath)
    {
        //
    }

    public function delete($path)
    {
        //
    }

    public function deleteDir($dirname)
    {
        //
    }

    public function createDir($dirname, Config $config)
    {
        //
    }

    public function has($path)
    {
        //
    }

    public function read($path)
    {
        //
    }

    public function readStream($path)
    {
        //
    }

    public function listContents($directory = '', $recursive = false)
    {
        //
    }

    public function getMetadata($path)
    {
        //
    }

    public function getSize($path)
    {
        //
    }

    public function getMimetype($path)
    {
        //
    }

    public function getTimestamp($path)
    {
        //
    }
}
