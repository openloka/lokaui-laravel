<?php

namespace LokaUI\Cli\Support;

use RuntimeException;

class Config
{
    /**
     * The config filename stored at the Laravel project root.
     */
    private const FILENAME = 'lokaui.json';

    /**
     * Get the full path to the config file.
     */
    public function path(): string
    {
        return base_path(self::FILENAME);
    }

    /**
     * Check whether the config file exists.
     */
    public function exists(): bool
    {
        return file_exists($this->path());
    }

    /**
     * Read and decode the config file.
     *
     * @return array The decoded configuration data.
     *
     * @throws RuntimeException If the config file does not exist or cannot be parsed.
     */
    public function read(): array
    {
        if (! $this->exists()) {
            throw new RuntimeException(
                'LokaUI is not initialized. Run "php artisan lokaui:init" first.'
            );
        }

        $content = file_get_contents($this->path());

        if ($content === false) {
            throw new RuntimeException('Failed to read ' . self::FILENAME);
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Failed to parse ' . self::FILENAME . ': ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Write configuration data to the config file.
     *
     * @param array $config The configuration data to write.
     *
     * @throws RuntimeException If the file cannot be written.
     */
    public function write(array $config): void
    {
        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('Failed to encode configuration to JSON.');
        }

        $result = file_put_contents($this->path(), $json . "\n");

        if ($result === false) {
            throw new RuntimeException('Failed to write ' . self::FILENAME);
        }
    }
}
