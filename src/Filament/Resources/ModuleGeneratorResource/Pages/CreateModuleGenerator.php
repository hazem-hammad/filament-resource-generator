<?php

namespace Intcore\FilamentResourceGenerator\Filament\Resources\ModuleGeneratorResource\Pages;

use Intcore\FilamentResourceGenerator\Filament\Resources\ModuleGeneratorResource;
use Intcore\FilamentResourceGenerator\Services\ModuleGeneratorService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateModuleGenerator extends CreateRecord
{
    protected static string $resource = ModuleGeneratorResource::class;

    protected static ?string $title = 'Generate New Module';

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('generate')
                ->label('Generate Module')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('success')
                ->size(\Filament\Support\Enums\ActionSize::Large)
                ->action(function () {
                    try {
                        $data = $this->form->getState();
                        
                        // Debug: Let's see exactly what structure we're getting
                        \Log::error('DEBUGGING - Raw form state data:', [
                            'full_data' => $data,
                            'data_structure' => $this->analyzeDataStructure($data)
                        ]);
                        
                        $this->validateConfiguration($data);
                        
                        // For existing tables, ensure we have the proper table name set
                        if (($data['table_creation_mode'] ?? 'create_new') === 'use_existing') {
                            $data['table_name'] = $data['existing_table_name'] ?? $data['table_name'];
                        }
                        
                        // Debug: Log the final data structure being sent to the generator
                        \Log::info('Final data sent to generator:', [
                            'table_creation_mode' => $data['table_creation_mode'] ?? 'not set',
                            'table_name' => $data['table_name'] ?? 'not set',
                            'existing_table_name' => $data['existing_table_name'] ?? 'not set',
                            'columns_count' => count($data['columns'] ?? []),
                            'columns_structure' => array_map(function($col) {
                                return [
                                    'has_column_name' => isset($col['column_name']),
                                    'column_name' => $col['column_name'] ?? 'MISSING',
                                    'has_column_type' => isset($col['column_type']),
                                    'column_type' => $col['column_type'] ?? 'MISSING',
                                    'keys' => array_keys($col ?? [])
                                ];
                            }, $data['columns'] ?? [])
                        ]);
                        
                        $generator = new ModuleGeneratorService();
                        $result = $generator->generateModule($data);

                        // Clear caches
                        Artisan::call('optimize:clear');
                        
                        // Build notification body with execution results
                        $body = "Generated module '{$data['module_name']}' with " . count($result['files']) . " files.";
                        
                        if (isset($result['migration_executed'])) {
                            if ($result['migration_executed']) {
                                $body .= "\n✅ Migration executed successfully.";
                            } else {
                                $body .= "\n❌ Migration failed: " . ($result['migration_error'] ?? 'Unknown error');
                            }
                        }
                        
                        if (isset($result['seeder_executed'])) {
                            if ($result['seeder_executed']) {
                                $body .= "\n✅ Seeder executed successfully.";
                            } else {
                                $body .= "\n❌ Seeder failed: " . ($result['seeder_error'] ?? 'Unknown error');
                            }
                        }
                        
                        // Build notification actions
                        $notificationActions = [
                            \Filament\Notifications\Actions\Action::make('view_files')
                                ->label('View Generated Files')
                                ->button()
                                ->close()
                        ];

                        // Add action to refresh the page if Filament resource was generated
                        if (($data['generate_filament_resource'] ?? false)) {
                            $body .= "\n\n💡 Please refresh your browser or navigate to the admin panel to see the new resource.";
                            
                            $notificationActions[] = \Filament\Notifications\Actions\Action::make('refresh_page')
                                ->label('Refresh Page')
                                ->button()
                                ->action(fn () => redirect()->refresh())
                                ->close();
                        }

                        Notification::make()
                            ->title('Module Generated Successfully!')
                            ->body($body)
                            ->success()
                            ->persistent()
                            ->actions($notificationActions)
                            ->send();
                        
                        // Reset form for next generation
                        $this->form->fill([]);
                        
                    } catch (\Filament\Forms\ValidationException $e) {
                        // Re-throw validation errors to show them in the form
                        throw $e;
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Generation Failed')
                            ->body('Error: ' . $e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                })
                ->requiresConfirmation()
                ->modalHeading('Generate Module')
                ->modalDescription('This will create a new module with all selected components. Are you sure?')
                ->modalSubmitActionLabel('Yes, Generate Module')
                ->visible(function () {
                    try {
                        $data = $this->form->getRawState();
                        return !empty($data['module_name']);
                    } catch (\Exception $e) {
                        return false;
                    }
                }),
        ];
    }

    protected function hasCreateFormButton(): bool
    {
        return false;
    }

    protected function validateConfiguration(array &$data): void
    {
        // Check if module already exists
        $moduleName = $data['module_name'] ?? '';
        if (empty($moduleName)) {
            throw new \Exception('Module name is required');
        }

        // Check if table already exists (only for new tables)
        $tableName = $data['table_name'] ?? '';
        $tableCreationMode = $data['table_creation_mode'] ?? 'create_new';
        
        if (empty($tableName)) {
            throw new \Exception('Table name is required');
        }

        // Only check for table existence when creating new tables
        if ($tableCreationMode === 'create_new' && \Schema::hasTable($tableName)) {
            throw new \Exception("Table '{$tableName}' already exists");
        }
        
        // For existing tables, verify the table actually exists
        if ($tableCreationMode === 'use_existing' && !\Schema::hasTable($tableName)) {
            throw new \Exception("Selected table '{$tableName}' does not exist");
        }

        // Handle columns based on table creation mode
        if ($tableCreationMode === 'use_existing') {
            // For existing tables, automatically load columns from the database
            $columns = $this->loadTableColumns($tableName);
            $data['columns'] = $columns;
        } else {
            // For new tables, validate the manually defined columns
            $columns = $data['columns'] ?? [];
            
            // Filter out any completely empty columns that might have been created by form state
            $columns = array_filter($columns, function($column) {
                return !empty($column['column_name']) && !empty($column['column_type']);
            });
            
            if (empty($columns)) {
                throw new \Exception('At least one database column is required');
            }

            foreach ($columns as $index => $column) {
                if (empty($column['column_name'])) {
                    throw new \Exception('All columns must have a name. Column at index ' . $index . ' is missing a name.');
                }
                if (empty($column['column_type'])) {
                    throw new \Exception('All columns must have a type. Column "' . $column['column_name'] . '" is missing a type.');
                }
            }
            
            // Update the data with filtered columns
            $data['columns'] = $columns;
        }
    }

    public function create(bool $another = false): void
    {
        // Override the default create behavior since we're not actually creating a record
        // The generation is handled by the form actions
        
        // Do nothing - the Generate Module button handles the actual generation
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data;
    }

    private function loadTableColumns(string $tableName): array
    {
        try {
            $systemColumns = ['id', 'created_at', 'updated_at', 'deleted_at'];
            
            return collect(DB::select("DESCRIBE {$tableName}"))
                ->filter(function ($column) use ($systemColumns) {
                    $columnObj = (object) $column;
                    return !in_array($columnObj->Field, $systemColumns);
                })
                ->map(function ($column) {
                    $columnObj = (object) $column;
                    
                    // Map MySQL types to our form types
                    $type = $this->mapMySqlTypeToFormType($columnObj->Type);
                    
                    return [
                        'column_name' => $columnObj->Field,
                        'column_type' => $type,
                        'is_nullable' => $columnObj->Null === 'YES',
                        'is_unique' => $columnObj->Key === 'UNI',
                        'is_indexed' => in_array($columnObj->Key, ['PRI', 'UNI', 'MUL']),
                        'default_value' => $columnObj->Default,
                    ];
                })
                ->values()
                ->toArray();
        } catch (\Exception $e) {
            throw new \Exception('Could not load columns from table "' . $tableName . '": ' . $e->getMessage());
        }
    }

    private function mapMySqlTypeToFormType(string $mysqlType): string
    {
        $mysqlType = strtolower($mysqlType);
        
        if (str_contains($mysqlType, 'varchar') || str_contains($mysqlType, 'char')) {
            return 'string';
        }
        
        if (str_contains($mysqlType, 'text')) {
            return str_contains($mysqlType, 'longtext') ? 'longText' : 'text';
        }
        
        if (str_contains($mysqlType, 'int')) {
            return str_contains($mysqlType, 'bigint') ? 'bigInteger' : 'integer';
        }
        
        if (str_contains($mysqlType, 'decimal') || str_contains($mysqlType, 'numeric')) {
            return 'decimal';
        }
        
        if (str_contains($mysqlType, 'float')) {
            return 'float';
        }
        
        if (str_contains($mysqlType, 'double')) {
            return 'double';
        }
        
        if (str_contains($mysqlType, 'boolean') || str_contains($mysqlType, 'tinyint(1)')) {
            return 'boolean';
        }
        
        if (str_contains($mysqlType, 'json')) {
            return 'json';
        }
        
        if (str_contains($mysqlType, 'date')) {
            return 'date';
        }
        
        if (str_contains($mysqlType, 'time')) {
            return str_contains($mysqlType, 'datetime') ? 'dateTime' : 'time';
        }
        
        if (str_contains($mysqlType, 'timestamp')) {
            return 'timestamp';
        }
        
        if (str_contains($mysqlType, 'enum')) {
            return 'enum';
        }
        
        return 'string'; // Default fallback
    }

    private function analyzeDataStructure($data): array
    {
        $analysis = [];
        
        foreach ($data as $key => $value) {
            if ($key === 'columns') {
                $analysis[$key] = [
                    'type' => gettype($value),
                    'count' => is_array($value) ? count($value) : 0,
                    'sample_structure' => is_array($value) && !empty($value) ? [
                        'first_item_type' => gettype($value[0] ?? null),
                        'first_item_keys' => is_array($value[0] ?? null) ? array_keys($value[0]) : 'not_array',
                        'first_item_values' => $value[0] ?? null,
                    ] : 'empty_or_not_array'
                ];
            } else {
                $analysis[$key] = [
                    'type' => gettype($value),
                    'value' => is_array($value) ? 'array_with_' . count($value) . '_items' : $value
                ];
            }
        }
        
        return $analysis;
    }
}