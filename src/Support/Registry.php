<?php

namespace LokaUI\Cli\Support;

use RuntimeException;

class Registry
{
    /**
     * The base URL for raw GitHub content.
     */
    private const BASE_URL = 'https://raw.githubusercontent.com/openloka/LokaUI/main';

    /**
     * The registry endpoint URL.
     */
    private const REGISTRY_URL = self::BASE_URL . '/registry.json';

    /**
     * Maximum number of fetch attempts before failing.
     */
    private const MAX_RETRIES = 3;

    /**
     * Seconds to wait between retry attempts.
     */
    private const RETRY_DELAY = 2;

    /**
     * HTTP request timeout in seconds.
     */
    private const TIMEOUT = 15;

    /**
     * Fetch and decode the component registry from GitHub.
     *
     * @return array The decoded registry data.
     *
     * @throws RuntimeException If the registry cannot be fetched after all retries.
     */
    public function fetch(): array
    {
        $content = $this->fetchWithRetry(self::REGISTRY_URL);

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Failed to parse registry JSON: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Fetch raw file content from a given URL.
     *
     * @param string $url The URL to fetch content from.
     * @return string The raw file content.
     *
     * @throws RuntimeException If the file cannot be fetched.
     */
    public function fetchFile(string $url): string
    {
        return $this->fetchWithRetry($url);
    }

    /**
     * Fetch content from a URL with retry logic.
     *
     * @param string $url The URL to fetch.
     * @return string The response body.
     *
     * @throws RuntimeException If all retry attempts fail.
     */
    private function fetchWithRetry(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => self::TIMEOUT,
                'header' => "User-Agent: LokaUI-CLI/0.1.0\r\n",
            ],
        ]);

        $lastError = null;

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            $content = @file_get_contents($url, false, $context);

            if ($content !== false) {
                return $content;
            }

            $lastError = error_get_last()['message'] ?? 'Unknown error';

            if ($attempt < self::MAX_RETRIES) {
                sleep(self::RETRY_DELAY);
            }
        }

        throw new RuntimeException(
            "Failed to fetch {$url} after " . self::MAX_RETRIES . " attempts. Last error: {$lastError}"
        );
    }

    /**
     * Get the base GitHub raw content URL.
     */
    public static function baseUrl(): string
    {
        return self::BASE_URL;
    }
}
