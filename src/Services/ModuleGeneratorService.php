<?php

namespace Intcore\FilamentResourceGenerator\Services;

use Intcore\FilamentResourceGenerator\Services\Generators\FilamentResourceGenerator;
use Intcore\FilamentResourceGenerator\Services\Generators\MigrationGenerator;
use Intcore\FilamentResourceGenerator\Services\Generators\ModelGenerator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ModuleGeneratorService
{
    protected array $generators = [];

    /**
     * Get the namespace path for a panel
     */
    protected function getPanelNamespace(string $panelId): string
    {
        return match ($panelId) {
            'admin' => 'Admin',
            'app' => 'App', 
            'user' => 'User',
            'customer' => 'Customer',
            default => Str::studly($panelId)
        };
    }

    public function __construct()
    {
        $this->generators = [
            'model' => new ModelGenerator(),
            'migration' => new MigrationGenerator(),
            'filament' => new FilamentResourceGenerator(),
        ];
    }

    public function generateModule(array $config): array
    {
        $result = [
            'files' => [],
            'directories' => [],
        ];

        try {
            // Log the raw config received
            \Log::info('ModuleGeneratorService received config:', [
                'config_keys' => array_keys($config),
                'table_creation_mode' => $config['table_creation_mode'] ?? 'not_set',
                'columns_raw' => $config['columns'] ?? 'not_set',
                'columns_count' => count($config['columns'] ?? [])
            ]);

            // Normalize config
            $config = $this->normalizeConfig($config);
            
            // Log normalized config
            \Log::info('ModuleGeneratorService normalized config:', [
                'columns_count_after_normalize' => count($config['columns'] ?? []),
                'columns_sample' => array_slice($config['columns'] ?? [], 0, 2)
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in ModuleGeneratorService initialization:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
        
        try {
            // Create base directory structure
            \Log::info('Creating directory structure...');
            $this->createDirectoryStructure($config, $result);

            // Generate model
            \Log::info('Generating model...');
            $this->generateModel($config, $result);

            // Generate migration only for new tables
            if (($config['table_creation_mode'] ?? 'create_new') === 'create_new') {
                \Log::info('Generating migration...');
                $this->generateMigration($config, $result);
            }

            // Generate Filament resource if requested
            if ($config['generate_filament_resource'] ?? false) {
                \Log::info('Generating Filament resource...');
                $this->generateFilamentResource($config, $result);
            }
        } catch (\Exception $e) {
            \Log::error('Error in module generation:', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }


        // Generate additional files
        $this->generateAdditionalFiles($config, $result);

        // Run migrations if requested (for new tables only)
        if (($config['run_migration'] ?? false) && ($config['table_creation_mode'] ?? 'create_new') === 'create_new') {
            $this->runMigration($result);
        }

        // Run seeder if requested
        if ($config['run_seeder'] ?? false) {
            $this->runSeeder($config, $result);
        }

        return $result;
    }


    protected function normalizeConfig(array $config): array
    {
        $config['module_name'] = Str::studly($config['module_name'] ?? '');
        $config['model_name'] = $config['model_name'] ?? $config['module_name'];
        $config['table_name'] = $config['table_name'] ?? Str::snake(Str::plural($config['module_name']));
        
        // Ensure we have default columns
        if (empty($config['columns'])) {
            $config['columns'] = [
                ['column_name' => 'name', 'column_type' => 'string', 'is_nullable' => false],
            ];
        }

        // Add timestamps if requested and creating new table
        if (($config['add_timestamps'] ?? true) && ($config['table_creation_mode'] ?? 'create_new') === 'create_new') {
            $config['columns'][] = ['column_name' => 'created_at', 'column_type' => 'timestamp', 'is_nullable' => true];
            $config['columns'][] = ['column_name' => 'updated_at', 'column_type' => 'timestamp', 'is_nullable' => true];
        }

        // Add soft deletes if requested and creating new table
        if (($config['add_soft_deletes'] ?? false) && ($config['table_creation_mode'] ?? 'create_new') === 'create_new') {
            $config['columns'][] = ['column_name' => 'deleted_at', 'column_type' => 'timestamp', 'is_nullable' => true];
        }

        return $config;
    }

    protected function createDirectoryStructure(array $config, array &$result): void
    {
        $directories = [
            app_path("Models"),
        ];

        // Only create migrations directory for new tables
        if (($config['table_creation_mode'] ?? 'create_new') === 'create_new') {
            $directories[] = database_path("migrations");
        }

        if ($config['generate_filament_resource'] ?? false) {
            $targetPanel = $config['target_panel'] ?? 'admin';
            
            // For admin panel, use the configured path from AdminPanelProvider
            if ($targetPanel === 'admin') {
                $directories[] = app_path("Filament/Resources");
                $directories[] = app_path("Filament/Resources/{$config['model_name']}Resource/Pages");
            } else {
                // For other panels, use panel-specific directories
                $panelNamespace = $this->getPanelNamespace($targetPanel);
                $directories[] = app_path("Filament/{$panelNamespace}/Resources");
                $directories[] = app_path("Filament/{$panelNamespace}/Resources/{$config['model_name']}Resource/Pages");
            }
        }

        foreach ($directories as $directory) {
            if (!File::exists($directory)) {
                File::makeDirectory($directory, 0755, true);
                $result['directories'][] = $directory;
            }
        }
    }

    protected function generateModel(array $config, array &$result): void
    {
        $content = $this->generators['model']->generate($config);
        $path = app_path("Models/{$config['model_name']}.php");
        
        File::put($path, $content);
        $result['files'][] = $path;
    }

    protected function generateMigration(array $config, array &$result): void
    {
        $content = $this->generators['migration']->generate($config);
        $timestamp = date('Y_m_d_His');
        $path = database_path("migrations/{$timestamp}_create_{$config['table_name']}_table.php");
        
        File::put($path, $content);
        $result['files'][] = $path;
    }

    protected function generateFilamentResource(array $config, array &$result): void
    {
        $files = $this->generators['filament']->generate($config);
        
        foreach ($files as $filename => $content) {
            $path = $this->getFilamentPath($filename, $config);
            File::put($path, $content);
            $result['files'][] = $path;
        }
    }


    protected function generateAdditionalFiles(array $config, array &$result): void
    {
        // Generate factory if requested or if seeder is requested (seeder depends on factory)
        if (($config['generate_factory'] ?? false) || ($config['generate_seeder'] ?? false)) {
            $this->generateFactory($config, $result);
        }

        // Generate seeder if requested
        if ($config['generate_seeder'] ?? false) {
            $this->generateSeeder($config, $result);
        }

        // Generate tests if requested
        if ($config['generate_tests'] ?? false) {
            $this->generateTests($config, $result);
        }
    }

    protected function getFilamentPath(string $filename, array $config): string
    {
        $modelName = $config['model_name'];
        $targetPanel = $config['target_panel'] ?? 'admin';
        
        // For admin panel, use the configured path from AdminPanelProvider
        if ($targetPanel === 'admin') {
            return match ($filename) {
                'Resource' => app_path("Filament/Resources/{$modelName}Resource.php"),
                'ListPage' => app_path("Filament/Resources/{$modelName}Resource/Pages/List{$modelName}s.php"),
                'CreatePage' => app_path("Filament/Resources/{$modelName}Resource/Pages/Create{$modelName}.php"),
                'EditPage' => app_path("Filament/Resources/{$modelName}Resource/Pages/Edit{$modelName}.php"),
                'ViewPage' => app_path("Filament/Resources/{$modelName}Resource/Pages/View{$modelName}.php"),
                default => app_path("Filament/Resources/{$filename}.php"),
            };
        } else {
            // For other panels, use panel-specific directories
            $panelNamespace = $this->getPanelNamespace($targetPanel);
            return match ($filename) {
                'Resource' => app_path("Filament/{$panelNamespace}/Resources/{$modelName}Resource.php"),
                'ListPage' => app_path("Filament/{$panelNamespace}/Resources/{$modelName}Resource/Pages/List{$modelName}s.php"),
                'CreatePage' => app_path("Filament/{$panelNamespace}/Resources/{$modelName}Resource/Pages/Create{$modelName}.php"),
                'EditPage' => app_path("Filament/{$panelNamespace}/Resources/{$modelName}Resource/Pages/Edit{$modelName}.php"),
                'ViewPage' => app_path("Filament/{$panelNamespace}/Resources/{$modelName}Resource/Pages/View{$modelName}.php"),
                default => app_path("Filament/{$panelNamespace}/Resources/{$filename}.php"),
            };
        }
    }


    protected function generateFactory(array $config, array &$result): void
    {
        $modelName = $config['model_name'];
        $columns = $config['columns'] ?? [];
        
        $factoryDefinitions = [];
        
        foreach ($columns as $column) {
            $columnName = $column['column_name'];
            $columnType = $column['column_type'];
            
            // Skip timestamps and auto-generated columns
            if (in_array($columnName, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }
            
            $factoryDefinitions[] = $this->generateFactoryDefinition($columnName, $columnType, $column);
        }
        
        $definitionsString = implode("\n            ", $factoryDefinitions);
        
        $content = "<?php

namespace Database\Factories;

use App\Models\\{$modelName};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\\{$modelName}>
 */
class {$modelName}Factory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected \$model = {$modelName}::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            {$definitionsString}
        ];
    }
}";
        
        $path = database_path("factories/{$modelName}Factory.php");
        
        File::put($path, $content);
        $result['files'][] = $path;
    }

    protected function generateFactoryDefinition(string $columnName, string $columnType, array $column): string
    {
        return match ($columnType) {
            'string' => "'{$columnName}' => \$this->faker->words(3, true),",
            'text', 'longText' => "'{$columnName}' => \$this->faker->paragraph(),",
            'integer', 'bigInteger' => "'{$columnName}' => \$this->faker->numberBetween(1, 1000),",
            'boolean' => "'{$columnName}' => \$this->faker->boolean(),",
            'decimal', 'float', 'double' => "'{$columnName}' => \$this->faker->randomFloat(2, 0, 999.99),",
            'date' => "'{$columnName}' => \$this->faker->date(),",
            'time' => "'{$columnName}' => \$this->faker->time(),",
            'dateTime', 'timestamp' => "'{$columnName}' => \$this->faker->dateTime(),",
            'json' => "'{$columnName}' => [],",
            'enum' => !empty($column['enum_values']) 
                ? "'{$columnName}' => \$this->faker->randomElement(['" . implode("', '", $column['enum_values']) . "']),"
                : "'{$columnName}' => \$this->faker->word(),",
            'foreignId' => "'{$columnName}' => 1, // TODO: Update with proper foreign key relationship",
            default => "'{$columnName}' => \$this->faker->word(),",
        };
    }

    protected function generateSeeder(array $config, array &$result): void
    {
        $modelName = $config['model_name'];
        
        $content = "<?php

namespace Database\Seeders;

use App\Models\\{$modelName};
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class {$modelName}Seeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create 10 sample records using the factory
        {$modelName}::factory(10)->create();
    }
}";
        
        $path = database_path("seeders/{$modelName}Seeder.php");
        
        File::put($path, $content);
        $result['files'][] = $path;
    }

    protected function generateTests(array $config, array &$result): void
    {
        // Implementation for test generation
        $content = "<?php\n\n// Tests for {$config['model_name']} - To be implemented";
        $path = base_path("tests/Feature/{$config['model_name']}Test.php");
        
        File::put($path, $content);
        $result['files'][] = $path;
    }

    protected function runMigration(array &$result): void
    {
        try {
            \Log::info('Running migration...');
            \Artisan::call('migrate');
            $result['migration_executed'] = true;
            $result['migration_output'] = \Artisan::output();
        } catch (\Exception $e) {
            \Log::error('Failed to run migration:', ['error' => $e->getMessage()]);
            $result['migration_executed'] = false;
            $result['migration_error'] = $e->getMessage();
        }
    }

    protected function runSeeder(array $config, array &$result): void
    {
        try {
            $seederClass = $config['model_name'] . 'Seeder';
            \Log::info('Running seeder: ' . $seederClass);
            \Artisan::call('db:seed', ['--class' => $seederClass]);
            $result['seeder_executed'] = true;
            $result['seeder_output'] = \Artisan::output();
        } catch (\Exception $e) {
            \Log::error('Failed to run seeder:', ['error' => $e->getMessage()]);
            $result['seeder_executed'] = false;
            $result['seeder_error'] = $e->getMessage();
        }
    }
}