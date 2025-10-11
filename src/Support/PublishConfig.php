<?php

namespace Timeax\FortiPlugin\Support;

use JsonException;
use RuntimeException;

class PublishConfig
{
    protected mixed $publish;
    protected mixed $plugin;

    /**
     * @throws JsonException
     */
    public function __construct($publishPath = './publish.json', $pluginConfigPath = './fortiplugin.json')
    {
        if (!file_exists($publishPath)) {
            throw new RuntimeException('publish.json not found!');
        }
        $publishRaw = file_get_contents($publishPath);
        $this->publish = json_decode(stripComments($publishRaw), true, 512, JSON_THROW_ON_ERROR);

        if (!isset($this->publish['host'], $this->publish['token'], $this->publish['author'])) {
            throw new RuntimeException('publish.json missing required fields!');
        }

        // Optional: Also load fortiplugin.json for more info
        if (file_exists($pluginConfigPath)) {
            $pluginRaw = file_get_contents($pluginConfigPath);
            $this->plugin = json_decode(stripComments($pluginRaw), true, 512, JSON_THROW_ON_ERROR);
        }
    }

    public function getHost()
    {
        return $this->publish['host'];
    }

    public function getToken()
    {
        return $this->publish['token'];
    }

    public function getAuthor()
    {
        return $this->publish['author'];
    }

    public function getMeta()
    {
        return $this->publish['meta'] ?? [];
    }

    public function getPluginConfig()
    {
        return $this->plugin;
    }

    // Optionally, add more utility getters as needed
}
