<?php

namespace LokaUI\Cli\Commands;

use Illuminate\Console\Command;
use LokaUI\Cli\Support\Config;
use LokaUI\Cli\Support\Registry;
use RuntimeException;

use function Laravel\Prompts\multiselect;

class AddCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'lokaui:add
        {components?* : Component names to add}
        {--all : Install all available components}
        {--overwrite : Overwrite existing components}
        {--dry-run : Show what would be installed without writing files}';

    /**
     * The console command description.
     */
    protected $description = 'Add LokaUI components to your project';

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

        if (! $config->exists()) {
            $this->components->error('LokaUI is not initialized. Run "php artisan lokaui:init" first.');

            return self::FAILURE;
        }

        try {
            $configData = $config->read();
            $registry = new Registry();
            $registryData = $registry->fetch();
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $componentsDir = $configData['componentsDir'] ?? 'resources/views/components/ui';
        $installed = $configData['installed'] ?? [];
        $components = $registryData['components'] ?? [];

        // Determine which components to install.
        $requestedNames = $this->argument('components');
        $isAll = $this->option('all');
        $isOverwrite = $this->option('overwrite');
        $isDryRun = $this->option('dry-run');

        if (! empty($requestedNames)) {
            // Mode 1: Inline — specific components provided.
            $toInstall = $this->resolveInlineComponents($requestedNames, $components, $componentsDir, $installed, $isOverwrite);
        } elseif ($isAll) {
            // Mode 3: All available components.
            $toInstall = $this->resolveAllComponents($components, $componentsDir, $installed, $isOverwrite);
        } else {
            // Mode 2: Interactive selection.
            $toInstall = $this->resolveInteractiveComponents($components, $componentsDir, $installed, $isOverwrite);
        }

        if (empty($toInstall)) {
            $this->components->info('No components to install.');

            return self::SUCCESS;
        }

        // Install components.
        $written = [];
        $skipped = [];

        foreach ($toInstall as $component) {
            $name = $component['name'];
            $fileUrl = $component['url'];
            $targetPath = base_path($componentsDir . '/' . $name . '.blade.php');

            if ($isDryRun) {
                $this->line("  <comment>[dry-run]</comment> Would write: {$componentsDir}/{$name}.blade.php");
                $written[] = $name;

                continue;
            }

            try {
                $content = $registry->fetchFile($fileUrl);

                // Ensure target directory exists.
                $dir = dirname($targetPath);
                if (! is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                file_put_contents($targetPath, $content);
                $written[] = $name;
            } catch (RuntimeException $e) {
                $this->components->error("Failed to fetch {$name}: {$e->getMessage()}");
                $skipped[] = $name;
            }
        }

        // Update config with newly installed components.
        if (! $isDryRun && ! empty($written)) {
            $installed = array_unique(array_merge($installed, $written));
            sort($installed);
            $configData['installed'] = $installed;
            $config->write($configData);
        }

        // Print summary.
        $this->printSummary($written, $skipped, $componentsDir, $isDryRun, $components);

        return self::SUCCESS;
    }

    /**
     * Resolve components from inline arguments.
     *
     * @param array $requestedNames The component names requested by the user.
     * @param array $components The full registry components list.
     * @param string $componentsDir The target components directory.
     * @param array $installed Already installed component names.
     * @param bool $isOverwrite Whether to overwrite existing files.
     * @return array Components to install with name and url keys.
     */
    private function resolveInlineComponents(
        array $requestedNames,
        array $components,
        string $componentsDir,
        array $installed,
        bool $isOverwrite
    ): array {
        $toInstall = [];
        $componentMap = $this->buildComponentMap($components);

        foreach ($requestedNames as $name) {
            $normalized = strtolower(trim($name));

            if (! isset($componentMap[$normalized])) {
                $this->components->warn("Component \"{$normalized}\" not found in registry — skipped.");

                continue;
            }

            $entry = $componentMap[$normalized];

            if (! $this->isAvailableForLaravel($entry)) {
                $this->components->warn("Component \"{$normalized}\" is coming soon for Laravel — skipped.");

                continue;
            }

            if (! $isOverwrite && $this->isAlreadyInstalled($normalized, $componentsDir)) {
                $this->components->warn("Component \"{$normalized}\" already exists — use --overwrite to replace.");

                continue;
            }

            $fileUrl = $this->buildFileUrl($entry);
            if ($fileUrl) {
                $toInstall[] = ['name' => $normalized, 'url' => $fileUrl];
            }
        }

        return $toInstall;
    }

    /**
     * Resolve all available components for installation.
     */
    private function resolveAllComponents(
        array $components,
        string $componentsDir,
        array $installed,
        bool $isOverwrite
    ): array {
        $toInstall = [];
        $comingSoon = 0;

        foreach ($components as $entry) {
            $name = strtolower($entry['name'] ?? '');

            if (empty($name)) {
                continue;
            }

            if (! $this->isAvailableForLaravel($entry)) {
                $comingSoon++;

                continue;
            }

            if (! $isOverwrite && $this->isAlreadyInstalled($name, $componentsDir)) {
                continue;
            }

            $fileUrl = $this->buildFileUrl($entry);
            if ($fileUrl) {
                $toInstall[] = ['name' => $name, 'url' => $fileUrl];
            }
        }

        if ($comingSoon > 0) {
            $this->components->info("{$comingSoon} component(s) coming soon for Laravel — skipped.");
        }

        return $toInstall;
    }

    /**
     * Resolve components via interactive multi-select.
     */
    private function resolveInteractiveComponents(
        array $components,
        string $componentsDir,
        array $installed,
        bool $isOverwrite
    ): array {
        $options = [];
        $disabledOptions = [];
        $componentMap = $this->buildComponentMap($components);

        // Group by category for display.
        foreach ($components as $entry) {
            $name = strtolower($entry['name'] ?? '');
            $category = $entry['category'] ?? 'Other';

            if (empty($name)) {
                continue;
            }

            $label = "{$name} ({$category})";

            if (! $this->isAvailableForLaravel($entry)) {
                $label .= ' (coming soon)';
                $disabledOptions[] = $name;
            } elseif (in_array($name, $installed) || $this->isAlreadyInstalled($name, $componentsDir)) {
                $label .= ' (installed)';
                if (! $isOverwrite) {
                    $disabledOptions[] = $name;
                }
            }

            $options[$name] = $label;
        }

        if (empty($options)) {
            $this->components->warn('No components found in registry.');

            return [];
        }

        $selected = multiselect(
            label: 'Which components would you like to add?',
            options: $options,
            hint: 'Use space to select, enter to confirm.',
        );

        if (empty($selected)) {
            return [];
        }

        $toInstall = [];

        foreach ($selected as $name) {
            if (in_array($name, $disabledOptions) && ! $isOverwrite) {
                continue;
            }

            $entry = $componentMap[$name] ?? null;

            if (! $entry || ! $this->isAvailableForLaravel($entry)) {
                continue;
            }

            $fileUrl = $this->buildFileUrl($entry);
            if ($fileUrl) {
                $toInstall[] = ['name' => $name, 'url' => $fileUrl];
            }
        }

        return $toInstall;
    }

    /**
     * Build a lookup map of components keyed by lowercase name.
     */
    private function buildComponentMap(array $components): array
    {
        $map = [];

        foreach ($components as $entry) {
            $name = strtolower($entry['name'] ?? '');
            if (! empty($name)) {
                $map[$name] = $entry;
            }
        }

        return $map;
    }

    /**
     * Check if a component has a Laravel (HTML-TW) variant available.
     */
    private function isAvailableForLaravel(array $entry): bool
    {
        $variants = $entry['variants'] ?? [];

        foreach ($variants as $variant) {
            if (($variant['key'] ?? '') === self::VARIANT_KEY) {
                return $variant['file'] !== null;
            }
        }

        return false;
    }

    /**
     * Check if a component file already exists on disk.
     */
    private function isAlreadyInstalled(string $name, string $componentsDir): bool
    {
        return file_exists(base_path($componentsDir . '/' . $name . '.blade.php'));
    }

    /**
     * Build the full GitHub raw URL for a component's Laravel variant file.
     */
    private function buildFileUrl(array $entry): ?string
    {
        $variants = $entry['variants'] ?? [];
        $category = $entry['category'] ?? '';
        $componentName = $entry['name'] ?? '';

        foreach ($variants as $variant) {
            if (($variant['key'] ?? '') === self::VARIANT_KEY && $variant['file'] !== null) {
                $filePath = $variant['file'];

                return Registry::baseUrl() . '/src/content/' . $category . '/' . $componentName . '/' . $filePath;
            }
        }

        return null;
    }

    /**
     * Print a summary of the installation.
     */
    private function printSummary(
        array $written,
        array $skipped,
        string $componentsDir,
        bool $isDryRun,
        array $components
    ): void {
        $this->newLine();

        if (! empty($written)) {
            $action = $isDryRun ? 'Would install' : 'Installed';
            $this->components->info("{$action} " . count($written) . ' component(s):');

            foreach ($written as $name) {
                $tag = str_replace('/', '.', $componentsDir) . '.' . $name;
                // Simplify: use just the last two segments for the tag hint.
                $tagName = 'ui.' . $name;
                $this->line("  - {$name} → <comment><x-{$tagName} /></comment>");
            }
        }

        if (! empty($skipped)) {
            $this->newLine();
            $this->components->warn(count($skipped) . ' component(s) failed and were skipped.');
        }

        $this->newLine();
    }
}
