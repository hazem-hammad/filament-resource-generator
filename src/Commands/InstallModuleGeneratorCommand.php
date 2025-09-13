<?php

namespace Intcore\FilamentResourceGenerator\Commands;

use Illuminate\Console\Command;

class InstallModuleGeneratorCommand extends Command
{
    public $signature = 'module-generator:install';

    public $description = 'Install the Laravel Filament Module Generator package';

    public function handle(): int
    {
        $this->info('Installing Laravel Filament Module Generator...');

        $this->info('Publishing configuration...');
        $this->callSilent('vendor:publish', [
            '--tag' => 'filament-resource-generator-config',
        ]);

        $this->info('Laravel Filament Module Generator installed successfully!');

        return self::SUCCESS;
    }
}
