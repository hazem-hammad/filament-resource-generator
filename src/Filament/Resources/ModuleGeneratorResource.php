<?php

namespace Intcore\FilamentResourceGenerator\Filament\Resources;

use Intcore\FilamentResourceGenerator\Filament\Resources\ModuleGeneratorResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ModuleGeneratorResource extends Resource
{
    protected static ?string $model = null;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Development Tools';

    protected static ?string $navigationLabel = 'Module Generator';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Module Generator')
                    ->description('Generate a complete module with model, migration, and Filament resources')
                    ->schema([
                        Forms\Components\Wizard::make([
                            Forms\Components\Wizard\Step::make('Module Information')
                                ->description('Configure the basic details of your new module')
                                ->schema([
                                    Forms\Components\TextInput::make('module_name')
                                        ->label('Module Name')
                                        ->required()
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function ($state, callable $set) {
                                            $set('table_name', Str::snake(Str::plural($state)));
                                            $set('model_name', Str::studly($state));
                                        })
                                        ->helperText('This will be used as the model name (e.g., Product, Customer)')
                                        ->placeholder('Product'),

                                    Forms\Components\TextInput::make('model_name')
                                        ->label('Model Name')
                                        ->required()
                                        ->helperText('Auto-generated from module name but can be modified')
                                        ->placeholder('Product'),

                                    Forms\Components\TextInput::make('table_name')
                                        ->label('Database Table Name')
                                        ->required()
                                        ->helperText('Auto-generated from module name but can be modified')
                                        ->placeholder('products'),

                                    Forms\Components\Textarea::make('description')
                                        ->label('Module Description')
                                        ->placeholder('Brief description of what this module does')
                                        ->columnSpanFull(),
                                ])
                                ->columns(2),

                            Forms\Components\Wizard\Step::make('Database Schema')
                                ->description('Define the database structure for your module')
                                ->schema([
                                    Forms\Components\Select::make('table_creation_mode')
                                        ->label('Table Creation Mode')
                                        ->options([
                                            'create_new' => 'Create New Table',
                                            'use_existing' => 'Use Existing Table',
                                        ])
                                        ->default('create_new')
                                        ->live()
                                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                            // Clear existing table selection when switching modes
                                            $set('existing_table_name', null);

                                            if ($state === 'create_new') {
                                                // Reset to default columns for new table
                                                $set('columns', [
                                                    ['column_name' => 'name', 'column_type' => 'string', 'is_nullable' => false, 'is_unique' => false, 'is_indexed' => false],
                                                    ['column_name' => 'description', 'column_type' => 'text', 'is_nullable' => true, 'is_unique' => false, 'is_indexed' => false],
                                                ]);
                                            } else {
                                                // Clear columns when switching to existing table mode
                                                $set('columns', []);
                                            }
                                        })
                                        ->required(),

                                    Forms\Components\Select::make('existing_table_name')
                                        ->label('Select Existing Table')
                                        ->options(function () {
                                            try {
                                                return collect(DB::select('SHOW TABLES'))
                                                    ->map(fn($table) => array_values((array) $table)[0])
                                                    ->filter(fn($table) => !empty($table))
                                                    ->reject(fn($table) => in_array($table, ['migrations', 'failed_jobs', 'password_reset_tokens', 'personal_access_tokens']))
                                                    ->mapWithKeys(fn($table) => [(string) $table => (string) $table]);
                                            } catch (\Exception $e) {
                                                return [];
                                            }
                                        })
                                        ->searchable()
                                        ->live()
                                        ->visible(fn(callable $get) => $get('table_creation_mode') === 'use_existing')
                                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                            if (!empty($state)) {
                                                // Clear columns first, then load new ones
                                                $set('columns', []);
                                                static::loadExistingTableColumns($state, $set);
                                            } else {
                                                // Clear columns if no table selected
                                                $set('columns', []);
                                            }
                                        }),

                                    Forms\Components\Repeater::make('columns')
                                        ->label('Database Columns')
                                        ->key(fn(callable $get) => 'columns-' . ($get('table_creation_mode') ?? 'create_new'))
                                        ->default(
                                            fn(callable $get) => ($get('table_creation_mode') ?? 'create_new') === 'create_new' ? [
                                                ['column_name' => ' ', 'column_type' => '', 'is_nullable' => false, 'is_unique' => false, 'is_indexed' => false],
                                            ] : []
                                        )
                                        ->live()
                                        ->schema([
                                            Forms\Components\TextInput::make('column_name')
                                                ->label('Column Name')
                                                ->required()
                                                ->disabled(fn(callable $get) => $get('../../table_creation_mode') === 'use_existing')
                                                ->placeholder('name'),

                                            Forms\Components\Select::make('column_type')
                                                ->label('Data Type')
                                                ->options([
                                                    'string' => 'String',
                                                    'integer' => 'Integer',
                                                    'bigInteger' => 'Big Integer',
                                                    'boolean' => 'Boolean',
                                                    'text' => 'Text',
                                                    'longText' => 'Long Text',
                                                    'json' => 'JSON',
                                                    'date' => 'Date',
                                                    'time' => 'Time',
                                                    'dateTime' => 'DateTime',
                                                    'timestamp' => 'Timestamp',
                                                    'decimal' => 'Decimal',
                                                    'float' => 'Float',
                                                    'double' => 'Double',
                                                    'enum' => 'Enum',
                                                    'foreignId' => 'Foreign Key',
                                                ])
                                                ->live()
                                                ->disabled(fn(callable $get) => $get('../../table_creation_mode') === 'use_existing')
                                                ->required(),

                                            Forms\Components\Toggle::make('is_nullable')
                                                ->label('Nullable')
                                                ->disabled(fn(callable $get) => $get('../../table_creation_mode') === 'use_existing')
                                                ->default(false),

                                            Forms\Components\Toggle::make('is_unique')
                                                ->label('Unique')
                                                ->disabled(fn(callable $get) => $get('../../table_creation_mode') === 'use_existing')
                                                ->default(false),

                                            Forms\Components\Toggle::make('is_indexed')
                                                ->label('Add Index')
                                                ->disabled(fn(callable $get) => $get('../../table_creation_mode') === 'use_existing')
                                                ->default(false),

                                            Forms\Components\TextInput::make('default_value')
                                                ->label('Default Value')
                                                ->disabled(fn(callable $get) => $get('../../table_creation_mode') === 'use_existing')
                                                ->placeholder('Optional default value'),

                                            // Conditional fields based on column_type
                                            Forms\Components\TextInput::make('string_length')
                                                ->label('String Length')
                                                ->numeric()
                                                ->disabled(fn(callable $get) => $get('../../table_creation_mode') === 'use_existing')
                                                ->default(255)
                                                ->visible(fn(callable $get) => $get('column_type') === 'string'),

                                            Forms\Components\TagsInput::make('enum_values')
                                                ->label('Enum Values')
                                                ->disabled(fn(callable $get) => $get('../../table_creation_mode') === 'use_existing')
                                                ->helperText('Press Enter after each value')
                                                ->visible(fn(callable $get) => $get('column_type') === 'enum'),

                                            Forms\Components\Select::make('foreign_table')
                                                ->label('Foreign Table')
                                                ->options(function () {
                                                    try {
                                                        return collect(DB::select('SHOW TABLES'))
                                                            ->map(fn($table) => array_values((array) $table)[0])
                                                            ->filter(fn($table) => !empty($table))
                                                            ->reject(fn($table) => in_array($table, ['migrations', 'failed_jobs', 'password_reset_tokens', 'personal_access_tokens']))
                                                            ->mapWithKeys(fn($table) => [(string) $table => (string) $table]);
                                                    } catch (\Exception $e) {
                                                        return [];
                                                    }
                                                })
                                                ->disabled(fn(callable $get) => $get('../../table_creation_mode') === 'use_existing')
                                                ->searchable()
                                                ->visible(fn(callable $get) => $get('column_type') === 'foreignId'),

                                            Forms\Components\Grid::make(2)
                                                ->schema([
                                                    Forms\Components\TextInput::make('decimal_precision')
                                                        ->label('Precision')
                                                        ->numeric()
                                                        ->disabled(fn(callable $get) => $get('../../../table_creation_mode') === 'use_existing')
                                                        ->default(8),
                                                    Forms\Components\TextInput::make('decimal_scale')
                                                        ->label('Scale')
                                                        ->numeric()
                                                        ->disabled(fn(callable $get) => $get('../../../table_creation_mode') === 'use_existing')
                                                        ->default(2),
                                                ])
                                                ->visible(fn(callable $get) => $get('column_type') === 'decimal'),
                                        ])
                                        ->collapsible()
                                        ->cloneable(fn(callable $get) => $get('table_creation_mode') === 'create_new')
                                        ->deletable(fn(callable $get) => $get('table_creation_mode') === 'create_new')
                                        ->addable(fn(callable $get) => $get('table_creation_mode') === 'create_new')
                                        ->deleteAction(
                                            fn(Forms\Components\Actions\Action $action) => $action->requiresConfirmation(),
                                        )
                                        ->columnSpanFull(),
                                ]),

                            Forms\Components\Wizard\Step::make('Filament Resource Configuration')
                                ->description('Configure the admin interface for your module')
                                ->schema([
                                    Forms\Components\Toggle::make('generate_filament_resource')
                                        ->label('Generate Filament Resource')
                                        ->live()
                                        ->default(true),

                                    Forms\Components\Group::make([
                                        // Navigation Configuration Section
                                        Forms\Components\Section::make('Navigation Settings')
                                            ->description('Configure how this resource appears in the admin navigation')
                                            ->schema([
                                                Forms\Components\Grid::make(3)
                                                    ->schema([
                                                        Forms\Components\TextInput::make('navigation_label')
                                                            ->label('Navigation Label')
                                                            ->placeholder('Products'),

                                                        Forms\Components\TextInput::make('navigation_group')
                                                            ->label('Navigation Group')
                                                            ->placeholder('Catalog'),

                                                        Forms\Components\TextInput::make('navigation_sort')
                                                            ->label('Navigation Sort')
                                                            ->numeric()
                                                            ->default(1),
                                                    ]),
                                            ]),

                                        // Resource Pages Section
                                        Forms\Components\Section::make('Resource Pages')
                                            ->description('Select which pages to generate for this resource')
                                            ->schema([
                                                Forms\Components\CheckboxList::make('resource_pages')
                                                    ->label('Available Pages')
                                                    ->options([
                                                        'list' => 'List Page',
                                                        'create' => 'Create Page',
                                                        'edit' => 'Edit Page',
                                                        'view' => 'View Page',
                                                    ])
                                                    ->default(['list', 'create', 'edit'])
                                                    ->columns(2),

                                                Forms\Components\CheckboxList::make('table_actions')
                                                    ->label('Table Actions')
                                                    ->options([
                                                        'edit' => 'Edit Action',
                                                        'view' => 'View Action',
                                                        'delete' => 'Delete Action',
                                                        'bulk_delete' => 'Bulk Delete',
                                                    ])
                                                    ->default(['edit', 'delete', 'bulk_delete'])
                                                    ->columns(2),
                                            ]),

                                        // Form Fields Configuration Section
                                        Forms\Components\Section::make('Form Fields Configuration')
                                            ->description('Define how fields appear in create/edit forms')
                                            ->schema([
                                                Forms\Components\Repeater::make('form_fields')
                                                    ->label('Form Fields')
                                                    ->schema([
                                                        Forms\Components\Select::make('column')
                                                            ->label('Column')
                                                            ->options(
                                                                function ($livewire) {
                                                                    // Try to get columns from the livewire form state
                                                                    try {
                                                                        $formData = $livewire->form->getRawState();
                                                                        $columns = $formData['columns'] ?? [];
                                                                        
                                                                        return collect($columns)
                                                                            ->filter(fn($col) => is_array($col) && !empty($col['column_name']))
                                                                            ->pluck('column_name', 'column_name')
                                                                            ->filter()
                                                                            ->toArray();
                                                                    } catch (\Exception $e) {
                                                                        // Fallback: return empty options
                                                                        return [];
                                                                    }
                                                                }
                                                            )
                                                            ->live()
                                                            ->afterStateUpdated(function ($state, callable $set, $livewire) {
                                                                // Auto-configure input type based on column type
                                                                if (!empty($state)) {
                                                                    try {
                                                                        $formData = $livewire->form->getRawState();
                                                                        $columns = $formData['columns'] ?? [];
                                                                        $selectedColumn = collect($columns)->firstWhere('column_name', $state);
                                                                        
                                                                        if ($selectedColumn && isset($selectedColumn['column_type'])) {
                                                                            $inputType = static::guessInputTypeFromColumn($selectedColumn);
                                                                            $set('input_type', $inputType);
                                                                            
                                                                            // Set as required if column is not nullable
                                                                            $isRequired = !($selectedColumn['is_nullable'] ?? false);
                                                                            $set('is_required', $isRequired);
                                                                        }
                                                                    } catch (\Exception $e) {
                                                                        // Ignore errors in auto-configuration
                                                                    }
                                                                }
                                                            })
                                                            ->required(),

                                                        Forms\Components\Select::make('input_type')
                                                            ->label('Input Type')
                                                            ->options([
                                                                'text' => 'Text Input',
                                                                'textarea' => 'Textarea',
                                                                'select' => 'Select',
                                                                'toggle' => 'Toggle',
                                                                'datepicker' => 'Date Picker',
                                                                'datetimepicker' => 'DateTime Picker',
                                                                'timepicker' => 'Time Picker',
                                                                'fileupload' => 'File Upload',
                                                                'richtexteditor' => 'Rich Text Editor',
                                                                'tagsinput' => 'Tags Input',
                                                                'colorpicker' => 'Color Picker',
                                                            ])
                                                            ->required(),

                                                        Forms\Components\Toggle::make('is_required')
                                                            ->label('Required'),

                                                        Forms\Components\TextInput::make('validation_rules')
                                                            ->label('Validation Rules')
                                                            ->placeholder('max:255|unique:table,column'),
                                                    ])
                                                    ->collapsible()
                                                    ->cloneable()
                                                    ->columnSpanFull(),
                                            ]),

                                        // Table Columns Configuration Section
                                        Forms\Components\Section::make('Table Columns Configuration')
                                            ->description('Define how columns appear in the data table')
                                            ->schema([
                                                Forms\Components\Repeater::make('table_columns')
                                                    ->label('Table Columns')
                                                    ->schema([
                                                        Forms\Components\Select::make('column')
                                                            ->label('Column')
                                                            ->options(
                                                                function ($livewire) {
                                                                    // Try to get columns from the livewire form state
                                                                    try {
                                                                        $formData = $livewire->form->getRawState();
                                                                        $columns = $formData['columns'] ?? [];
                                                                        
                                                                        return collect($columns)
                                                                            ->filter(fn($col) => is_array($col) && !empty($col['column_name']))
                                                                            ->pluck('column_name', 'column_name')
                                                                            ->filter()
                                                                            ->toArray();
                                                                    } catch (\Exception $e) {
                                                                        // Fallback: return empty options
                                                                        return [];
                                                                    }
                                                                }
                                                            )
                                                            ->live()
                                                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                                // Auto-detect if this is a foreign key column
                                                                if (!empty($state)) {
                                                                    try {
                                                                        // Try different paths to find columns data
                                                                        $columns = $get('../../../../columns') ?? 
                                                                                  $get('../../../columns') ?? 
                                                                                  $get('../../columns') ?? [];
                                                                        $selectedColumn = collect($columns)->firstWhere('column_name', $state);
                                                                        
                                                                        if ($selectedColumn && ($selectedColumn['column_type'] ?? '') === 'foreignId') {
                                                                            // Auto-populate relationship fields for foreign keys
                                                                            $relationName = Str::singular(str_replace('_id', '', $state));
                                                                            $modelName = Str::studly($relationName);
                                                                            
                                                                            $set('relation_name', $relationName);
                                                                            $set('relation_model', $modelName);
                                                                            $set('relation_attribute', 'name');
                                                                        } else {
                                                                            // Clear relation fields for non-foreign keys
                                                                            $set('relation_name', null);
                                                                            $set('relation_model', null);
                                                                            $set('relation_attribute', null);
                                                                        }
                                                                    } catch (\Exception $e) {
                                                                        // Ignore errors in auto-configuration
                                                                    }
                                                                }
                                                            })
                                                            ->required(),

                                                        Forms\Components\Select::make('column_type')
                                                            ->label('Column Type')
                                                            ->options([
                                                                'text' => 'Text Column',
                                                                'badge' => 'Badge Column',
                                                                'boolean' => 'Boolean Column',
                                                                'icon' => 'Icon Column',
                                                                'image' => 'Image Column',
                                                            ])
                                                            ->default('text'),

                                                        Forms\Components\Toggle::make('is_searchable')
                                                            ->label('Searchable'),

                                                        Forms\Components\Toggle::make('is_sortable')
                                                            ->label('Sortable'),

                                                        Forms\Components\Toggle::make('is_toggleable')
                                                            ->label('Toggleable'),

                                                        // Foreign Key Relationship Fields
                                                        Forms\Components\Select::make('relation_model')
                                                            ->label('Related Model')
                                                            ->helperText('Select the model this foreign key relates to')
                                                            ->options(function () {
                                                                try {
                                                                    // Get all model files from app/Models directory
                                                                    $modelPath = app_path('Models');
                                                                    if (!is_dir($modelPath)) return [];
                                                                    
                                                                    $models = collect(scandir($modelPath))
                                                                        ->filter(fn($file) => str_ends_with($file, '.php'))
                                                                        ->map(fn($file) => basename($file, '.php'))
                                                                        ->filter(fn($model) => $model !== '.' && $model !== '..')
                                                                        ->mapWithKeys(fn($model) => [$model => $model])
                                                                        ->toArray();
                                                                    
                                                                    return $models;
                                                                } catch (\Exception $e) {
                                                                    return [];
                                                                }
                                                            })
                                                            ->searchable()
                                                            ->live()
                                                            ->afterStateUpdated(function ($state, callable $set) {
                                                                // Auto-populate relation_name based on selected model
                                                                if (!empty($state)) {
                                                                    $relationName = Str::camel(Str::singular($state));
                                                                    $set('relation_name', $relationName);
                                                                }
                                                            })
                                                            ->visible(function (callable $get) {
                                                                try {
                                                                    $columnName = $get('column');
                                                                    if (empty($columnName)) return false;
                                                                    
                                                                    // Try different paths to find columns data
                                                                    $columns = $get('../../../../columns') ?? 
                                                                              $get('../../../columns') ?? 
                                                                              $get('../../columns') ?? [];
                                                                    
                                                                    if (empty($columns)) return false;
                                                                    
                                                                    $selectedColumn = collect($columns)->firstWhere('column_name', $columnName);
                                                                    
                                                                    // Check if we found the column and its type
                                                                    $isForeignId = $selectedColumn && ($selectedColumn['column_type'] ?? '') === 'foreignId';
                                                                    
                                                                    return $isForeignId;
                                                                } catch (\Exception $e) {
                                                                    return false;
                                                                }
                                                            }),

                                                        Forms\Components\Select::make('relation_attribute')
                                                            ->label('Display Column')
                                                            ->helperText('Select which column from the related model to display')
                                                            ->options(function (callable $get) {
                                                                try {
                                                                    $modelName = $get('relation_model');
                                                                    if (empty($modelName)) {
                                                                        return ['name' => 'name (default)', 'title' => 'title', 'email' => 'email'];
                                                                    }
                                                                    
                                                                    // Try to get columns from the related model's table
                                                                    $modelClass = "App\\Models\\{$modelName}";
                                                                    if (!class_exists($modelClass)) {
                                                                        return ['name' => 'name (default)', 'title' => 'title', 'email' => 'email'];
                                                                    }
                                                                    
                                                                    $model = new $modelClass();
                                                                    $tableName = $model->getTable();
                                                                    
                                                                    $columns = collect(DB::select("DESCRIBE {$tableName}"))
                                                                        ->pluck('Field')
                                                                        ->reject(fn($col) => in_array($col, ['id', 'created_at', 'updated_at', 'deleted_at']))
                                                                        ->mapWithKeys(fn($col) => [$col => $col])
                                                                        ->toArray();
                                                                    
                                                                    return empty($columns) ? ['name' => 'name (default)'] : $columns;
                                                                } catch (\Exception $e) {
                                                                    return ['name' => 'name (default)', 'title' => 'title', 'email' => 'email'];
                                                                }
                                                            })
                                                            ->default('name')
                                                            ->searchable()
                                                            ->live()
                                                            ->visible(function (callable $get) {
                                                                try {
                                                                    $columnName = $get('column');
                                                                    if (empty($columnName)) return false;
                                                                    
                                                                    // Try different paths to find columns data
                                                                    $columns = $get('../../../../columns') ?? 
                                                                              $get('../../../columns') ?? 
                                                                              $get('../../columns') ?? [];
                                                                    
                                                                    if (empty($columns)) return false;
                                                                    
                                                                    $selectedColumn = collect($columns)->firstWhere('column_name', $columnName);
                                                                    
                                                                    // Check if we found the column and its type
                                                                    $isForeignId = $selectedColumn && ($selectedColumn['column_type'] ?? '') === 'foreignId';
                                                                    
                                                                    return $isForeignId;
                                                                } catch (\Exception $e) {
                                                                    return false;
                                                                }
                                                            }),

                                                        Forms\Components\Hidden::make('relation_name')
                                                            ->default(function (callable $get) {
                                                                $columnName = $get('column');
                                                                return $columnName ? Str::singular(str_replace('_id', '', $columnName)) : '';
                                                            }),
                                                    ])
                                                    ->collapsible()
                                                    ->cloneable()
                                                    ->columnSpanFull(),
                                            ]),
                                    ])
                                        ->visible(fn(callable $get) => $get('generate_filament_resource')),
                                ]),

                            Forms\Components\Wizard\Step::make('Additional Options')
                                ->description('Extra features and configurations')
                                ->schema([
                                    Forms\Components\Toggle::make('run_migration')
                                        ->label('Run Migration After Generation')
                                        ->default(false)
                                        ->visible(fn(callable $get) => $get('table_creation_mode') === 'create_new')
                                        ->helperText('Automatically run the migration after creating files'),

                                    Forms\Components\Toggle::make('generate_factory')
                                        ->label('Generate Model Factory')
                                        ->default(false),

                                    Forms\Components\Toggle::make('generate_seeder')
                                        ->label('Generate Database Seeder')
                                        ->live()
                                        ->default(false),

                                    Forms\Components\Toggle::make('run_seeder')
                                        ->label('Run Seeder After Generation')
                                        ->default(false)
                                        ->visible(fn(callable $get) => $get('generate_seeder'))
                                        ->helperText('Automatically run the seeder after creating files'),

                                    // Forms\Components\Toggle::make('generate_tests')
                                    //     ->label('Generate Test Files')
                                    //     ->default(false),

                                    Forms\Components\Toggle::make('add_timestamps')
                                        ->label('Add Timestamps (created_at, updated_at)')
                                        ->visible(fn(callable $get) => $get('table_creation_mode') === 'create_new')
                                        ->default(true),

                                    Forms\Components\Toggle::make('add_soft_deletes')
                                        ->label('Add Soft Deletes')
                                        ->visible(fn(callable $get) => $get('table_creation_mode') === 'create_new')
                                        ->default(false),
                                ])
                                ->columns(2),
                        ])
                            ->columnSpanFull()
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        // Since this is a generator resource, we don't need a traditional table
        // Instead, we'll redirect to the create page
        return $table->columns([])->actions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\CreateModuleGenerator::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return true;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canView(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    private static function loadExistingTableColumns(string $tableName, callable $set): void
    {
        try {
            $systemColumns = ['id', 'created_at', 'updated_at', 'deleted_at'];

            $columns = collect(DB::select("DESCRIBE {$tableName}"))
                ->filter(function ($column) use ($systemColumns) {
                    $columnObj = (object) $column;
                    return !in_array($columnObj->Field, $systemColumns);
                })
                ->map(function ($column) {
                    $columnObj = (object) $column;

                    // Map MySQL types to our form types
                    $type = static::mapMySqlTypeToFormType($columnObj->Type);

                    return [
                        'column_name' => $columnObj->Field,
                        'column_type' => $type,
                        'is_nullable' => $columnObj->Null === 'YES',
                        'is_unique' => $columnObj->Key === 'UNI',
                        'is_indexed' => in_array($columnObj->Key, ['PRI', 'UNI', 'MUL']),
                        'default_value' => $columnObj->Default,
                    ];
                })
                ->toArray();

            $set('columns', $columns);
            $set('table_name', $tableName);
        } catch (\Exception $e) {
            // Handle error silently or log it
            $set('columns', []);
        }
    }

    private static function mapMySqlTypeToFormType(string $mysqlType): string
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

    private static function guessInputTypeFromColumn(array $column): string
    {
        $columnType = $column['column_type'] ?? '';
        
        return match ($columnType) {
            'text', 'longText' => 'textarea',
            'boolean' => 'toggle',
            'date' => 'datepicker',
            'dateTime', 'timestamp' => 'datetimepicker',
            'time' => 'timepicker',
            'json' => 'richtexteditor',
            'enum' => 'select',
            'foreignId' => 'select', // Foreign keys should be select dropdowns
            default => 'text'
        };
    }
}
