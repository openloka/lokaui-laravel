<?php

namespace LokaUI\Cli\Commands;

use Illuminate\Console\Command;
use LokaUI\Cli\Support\Config;
use LokaUI\Cli\Support\Registry;
use RuntimeException;

class ListCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'lokaui:list';

    /**
     * The console command description.
     */
    protected $description = 'List available LokaUI components';

    /**
     * The variant key used for Laravel Blade components.
     */
    private const VARIANT_KEY = 'HTML-TW';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $config = new Config();

        // Read installed components from config if it exists.
        $installed = [];

        if ($config->exists()) {
            try {
                $configData = $config->read();
                $installed = $configData['installed'] ?? [];
            } catch (RuntimeException $e) {
                $this->components->warn("Could not read config: {$e->getMessage()}");
            }
        }

        // Fetch the component registry.
        try {
            $registry = new Registry();
            $registryData = $registry->fetch();
        } catch (RuntimeException $e) {
            $this->components->error("Failed to fetch registry: {$e->getMessage()}");

            return self::FAILURE;
        }

        $components = $registryData['components'] ?? [];

        if (empty($components)) {
            $this->components->info('No components found in registry.');

            return self::SUCCESS;
        }

        // Build table rows.
        $rows = [];

        foreach ($components as $entry) {
            $name = $entry['name'] ?? '';
            $category = $entry['category'] ?? '';

            if (empty($name)) {
                continue;
            }

            $normalizedName = strtolower($name);
            $status = $this->resolveStatus($entry, $installed, $normalizedName);

            $rows[] = [$name, $category, $status];
        }

        // Display the table.
        $this->newLine();
        $this->table(
            ['Component', 'Category', 'Status'],
            $rows,
        );
        $this->newLine();

        // Show summary counts.
        $available = count(array_filter($rows, fn ($r) => $r[2] === '✓'));
        $installedCount = count(array_filter($rows, fn ($r) => $r[2] === 'installed'));
        $comingSoon = count(array_filter($rows, fn ($r) => $r[2] === 'coming soon'));

        $this->line("  Available: <info>{$available}</info>  Installed: <info>{$installedCount}</info>  Coming soon: <comment>{$comingSoon}</comment>");
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Determine the display status for a component.
     */
    private function resolveStatus(array $entry, array $installed, string $normalizedName): string
    {
        // Check if installed.
        if (in_array($normalizedName, $installed)) {
            return 'installed';
        }

        // Check if Laravel variant is available.
        $variants = $entry['variants'] ?? [];

        foreach ($variants as $variant) {
            if (($variant['key'] ?? '') === self::VARIANT_KEY) {
                if ($variant['file'] !== null) {
                    return '✓';
                }

                return 'coming soon';
            }
        }

        return 'coming soon';
    }
}
