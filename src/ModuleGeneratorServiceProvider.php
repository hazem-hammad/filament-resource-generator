<?php

namespace Intcore\FilamentResourceGenerator;

use Filament\Support\Assets\Asset;
use Filament\Support\Facades\FilamentAsset;
use Filament\Facades\Filament;
use Intcore\FilamentResourceGenerator\Commands\InstallModuleGeneratorCommand;
use Intcore\FilamentResourceGenerator\Filament\Resources\ModuleGeneratorResource;
use Illuminate\Filesystem\Filesystem;
use Livewire\Features\SupportTesting\Testable;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ModuleGeneratorServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-resource-generator';

    public static string $viewNamespace = 'filament-resource-generator';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasCommands($this->getCommands())
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('hazem/filament-resource-generator');
            });

        $configFileName = $package->shortName();

        if (file_exists($package->basePath("/../config/{$configFileName}.php"))) {
            $package->hasConfigFile();
        }

        if (file_exists($package->basePath('/../database/migrations'))) {
            $package->hasMigrations($this->getMigrations());
        }

        if (file_exists($package->basePath('/../resources/lang'))) {
            $package->hasTranslations();
        }

        if (file_exists($package->basePath('/../resources/views'))) {
            $package->hasViews(static::$viewNamespace);
        }
    }

    public function packageRegistered(): void
    {
        //
    }

    public function packageBooted(): void
    {
        // Asset Registration
        FilamentAsset::register(
            $this->getAssets(),
            $this->getAssetPackageName()
        );

        FilamentAsset::registerScriptData(
            $this->getScriptData(),
            $this->getAssetPackageName()
        );

        // Register the resource with Filament
        Filament::serving(function () {
            $panel = Filament::getCurrentPanel();
            if ($panel) {
                $panel->pages([
                    \Intcore\FilamentResourceGenerator\Filament\Pages\ModuleGenerator::class,
                ]);
            }
        });

        // Handle Stubs
        if (app()->runningInConsole()) {
            $stubsPath = __DIR__ . '/../stubs/';
            if (is_dir($stubsPath)) {
                foreach (app(Filesystem::class)->files($stubsPath) as $file) {
                    $this->publishes([
                        $file->getRealPath() => base_path("stubs/filament-resource-generator/{$file->getFilename()}"),
                    ], 'filament-resource-generator-stubs');
                }
            }
        }
    }

    protected function getAssetPackageName(): ?string
    {
        return 'hazem/filament-resource-generator';
    }

    /**
     * @return array<Asset>
     */
    protected function getAssets(): array
    {
        return [
            // AlpineComponent::make('laravel-filament-module-generator', __DIR__ . '/../resources/dist/components/laravel-filament-module-generator.js'),
            // Css::make('laravel-filament-module-generator-styles', __DIR__ . '/../resources/dist/laravel-filament-module-generator.css'),
            // Js::make('laravel-filament-module-generator-scripts', __DIR__ . '/../resources/dist/laravel-filament-module-generator.js'),
        ];
    }

    /**
     * @return array<class-string>
     */
    protected function getCommands(): array
    {
        return [
            InstallModuleGeneratorCommand::class,
        ];
    }

    /**
     * @return array<string>
     */
    protected function getMigrations(): array
    {
        return [
            // 'create_laravel_filament_module_generator_table',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getScriptData(): array
    {
        return [];
    }
}