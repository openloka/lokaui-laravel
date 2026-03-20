<?php

namespace LokaUI\Cli\Support;

use RuntimeException;

class TokenInjector
{
    /**
     * The URL to fetch the LokaUI CSS tokens file.
     */
    private const TOKENS_URL = 'https://raw.githubusercontent.com/openloka/LokaUI/main/tokens.css';

    /**
     * The marker used to detect if tokens have already been injected.
     */
    private const INJECTION_MARKER = '--bg:';

    /**
     * Inject LokaUI CSS tokens into the given CSS file.
     *
     * Returns true if tokens were injected, false if they were already present.
     *
     * @param string $cssFilePath Absolute path to the target CSS file.
     * @return bool Whether tokens were injected.
     *
     * @throws RuntimeException If tokens cannot be fetched or the file cannot be written.
     */
    public function inject(string $cssFilePath): bool
    {
        // Check if the file already contains LokaUI tokens.
        if (file_exists($cssFilePath)) {
            $existingContent = file_get_contents($cssFilePath);

            if ($existingContent !== false && str_contains($existingContent, self::INJECTION_MARKER)) {
                return false;
            }
        }

        // Fetch tokens from GitHub.
        $registry = new Registry();
        $tokens = $registry->fetchFile(self::TOKENS_URL);

        if (empty($tokens)) {
            throw new RuntimeException('Fetched tokens file is empty.');
        }

        // Prepend tokens to existing content or create new file.
        $existingContent = '';
        if (file_exists($cssFilePath)) {
            $existingContent = file_get_contents($cssFilePath) ?: '';
        }

        $newContent = $tokens . "\n\n" . $existingContent;

        // Ensure the directory exists.
        $directory = dirname($cssFilePath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $result = file_put_contents($cssFilePath, $newContent);

        if ($result === false) {
            throw new RuntimeException("Failed to write CSS tokens to {$cssFilePath}");
        }

        return true;
    }
}
