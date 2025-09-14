<?php

namespace Intcore\FilamentResourceGenerator;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Intcore\FilamentResourceGenerator\Filament\Resources\ModuleGeneratorResource;

class ResourceGenerator implements Plugin
{
    public function getId(): string
    {
        return 'intcore-resource-generator';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            ModuleGeneratorResource::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }
}