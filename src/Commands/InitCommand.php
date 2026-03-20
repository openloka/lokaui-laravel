<?php

namespace LokaUI\Cli\Commands;

use Illuminate\Console\Command;
use LokaUI\Cli\Support\Config;
use LokaUI\Cli\Support\TokenInjector;
use RuntimeException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

class InitCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'lokaui:init';

    /**
     * The console command description.
     */
    protected $description = 'Initialize LokaUI in your Laravel project';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->newLine();
        $this->components->info('LokaUI v0.1.0');
        $this->newLine();

        // Ask for the components directory.
        $componentsDir = text(
            label: 'Where should components be installed?',
            default: 'resources/views/components/ui',
            hint: 'Relative to your project root',
        );

        // Ask for the CSS file path.
        $cssFile = text(
            label: 'Where is your CSS file?',
            default: 'resources/css/app.css',
            hint: 'Relative to your project root',
        );

        // Confirm CSS token injection.
        $injectTokens = confirm(
            label: "Add LokaUI CSS tokens to {$cssFile}?",
            default: true,
        );

        if ($injectTokens) {
            try {
                $injector = new TokenInjector();
                $injected = $injector->inject(base_path($cssFile));

                if ($injected) {
                    $this->components->info("CSS tokens added to {$cssFile}");
                } else {
                    $this->components->warn('CSS tokens already present — skipped.');
                }
            } catch (RuntimeException $e) {
                $this->components->error("Failed to inject CSS tokens: {$e->getMessage()}");

                return self::FAILURE;
            }
        }

        // Write the config file.
        $config = new Config();
        $config->write([
            'componentsDir' => $componentsDir,
            'cssFile' => $cssFile,
            'installed' => [],
        ]);

        $this->newLine();
        $this->components->info('LokaUI initialized successfully!');
        $this->newLine();

        $this->line('  Next steps:');
        $this->line('  1. Run <comment>php artisan lokaui:add</comment> to add components');
        $this->line('  2. Run <comment>php artisan lokaui:list</comment> to see available components');
        $this->newLine();

        return self::SUCCESS;
    }
}
