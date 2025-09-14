<?php

namespace Intcore\FilamentResourceGenerator\Services\Generators;

use Illuminate\Support\Str;

class FilamentResourceGenerator
{
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
    public function generate(array $config): array
    {
        $files = [];

        $files['Resource'] = $this->generateResource($config);
        $files['ListPage'] = $this->generateListPage($config);
        $files['CreatePage'] = $this->generateCreatePage($config);
        $files['EditPage'] = $this->generateEditPage($config);

        if (in_array('view', $config['resource_pages'] ?? [])) {
            $files['ViewPage'] = $this->generateViewPage($config);
        }

        return $files;
    }

    protected function generateResource(array $config): string
    {
        $modelName = $config['model_name'];
        $tableName = $config['table_name'];
        $navigationLabel = $config['navigation_label'] ?? Str::plural($modelName);
        $navigationGroup = $config['navigation_group'] ?? 'Resources';
        $navigationSort = $config['navigation_sort'] ?? 1;

        // Get target panel (default to 'admin' if not specified)
        $targetPanel = $config['target_panel'] ?? 'admin';
        
        // Generate form schema
        $formSchema = $this->generateFormSchema($config);

        // Generate table columns
        $tableColumns = $this->generateTableColumns($config);

        // Generate table actions
        $tableActions = $this->generateTableActions($config);

        // Generate bulk actions
        $bulkActions = $this->generateBulkActions($config);

        // Generate pages array
        $pages = $this->generatePagesArray($config);

        // For admin panel, use the configured path from AdminPanelProvider
        if ($targetPanel === 'admin') {
            $namespace = "App\\Filament\\Resources";
            $pagesNamespace = "App\\Filament\\Resources\\{$modelName}Resource\\Pages";
        } else {
            // For other panels, use panel-specific namespaces
            $panelNamespace = $this->getPanelNamespace($targetPanel);
            $namespace = "App\\Filament\\{$panelNamespace}\\Resources";
            $pagesNamespace = "App\\Filament\\{$panelNamespace}\\Resources\\{$modelName}Resource\\Pages";
        }

        return "<?php

namespace {$namespace};

use App\\Models\\{$modelName};
use Filament\\Forms;
use Filament\\Forms\\Form;
use Filament\\Resources\\Resource;
use Filament\\Tables;
use Filament\\Tables\\Table;
use {$pagesNamespace};
use Illuminate\\Database\\Eloquent\\Builder;
use Illuminate\\Database\\Eloquent\\SoftDeletingScope;

class {$modelName}Resource extends Resource
{
    protected static ?string \$model = {$modelName}::class;

    protected static ?string \$slug = '{$tableName}';

    protected static ?string \$navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string \$navigationLabel = '{$navigationLabel}';

    protected static ?string \$navigationGroup = '{$navigationGroup}';

    protected static ?int \$navigationSort = {$navigationSort};

    public static function form(Form \$form): Form
    {
        return \$form
            ->schema([
                Forms\\Components\\Section::make()->schema([
{$formSchema}
                ])
            ]);
    }

    public static function table(Table \$table): Table
    {
        return \$table
            ->columns([
{$tableColumns}
            ])
            ->filters([
                //
            ])
            ->actions([
{$tableActions}
            ])
            ->bulkActions([
{$bulkActions}
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
{$pages}
        ];
    }
}";
    }

    protected function generateFormSchema(array $config): string
    {
        $formFields = $config['form_fields'] ?? [];
        $columns = $config['columns'] ?? [];

        // If no form fields configured, use all columns
        if (empty($formFields)) {
            $formFields = collect($columns)
                ->filter(fn($col) => is_array($col) && isset($col['column_name']))
                ->reject(fn($col) => in_array($col['column_name'], ['id', 'created_at', 'updated_at', 'deleted_at']))
                ->map(fn($col) => [
                    'column' => $col['column_name'],
                    'input_type' => $this->guessInputType($col),
                    'is_required' => !($col['is_nullable'] ?? false),
                ])
                ->toArray();
        }

        $schema = [];

        foreach ($formFields as $field) {
            $schema[] = $this->generateFormField($field, $config);
        }

        return implode(",\n\n", $schema);
    }

    protected function generateFormField(array $field, array $config): string
    {
        $column = $field['column'];
        $inputType = $field['input_type'] ?? 'text';
        $isRequired = $field['is_required'] ?? false;
        $validationRules = $field['validation_rules'] ?? '';

        // Get the original column definition to check for foreign keys
        $originalColumn = $this->findOriginalColumn($column, $config);
        $isForeignKey = $originalColumn && ($originalColumn['column_type'] ?? '') === 'foreignId';
        $foreignTable = $originalColumn['foreign_table'] ?? null;

        $component = match ($inputType) {
            'text' => "Forms\\Components\\TextInput::make('{$column}')",
            'textarea' => "Forms\\Components\\Textarea::make('{$column}')",
            'select' => $this->generateSelectComponent($column, $isForeignKey, $foreignTable),
            'toggle' => "Forms\\Components\\Toggle::make('{$column}')",
            'datepicker' => "Forms\\Components\\DatePicker::make('{$column}')",
            'datetimepicker' => "Forms\\Components\\DateTimePicker::make('{$column}')",
            'timepicker' => "Forms\\Components\\TimePicker::make('{$column}')",
            'fileupload' => "Forms\\Components\\FileUpload::make('{$column}')",
            'richtexteditor' => "Forms\\Components\\RichEditor::make('{$column}')",
            'tagsinput' => "Forms\\Components\\TagsInput::make('{$column}')",
            'colorpicker' => "Forms\\Components\\ColorPicker::make('{$column}')",
            default => "Forms\\Components\\TextInput::make('{$column}')"
        };

        if ($isRequired) {
            $component .= "\n                            ->required()";
        }

        if ($validationRules) {
            $rules = explode('|', $validationRules);
            $rulesString = "'" . implode("', '", $rules) . "'";
            $component .= "\n                            ->rules([{$rulesString}])";
        }

        return "                        {$component}";
    }

    protected function generateTableColumns(array $config): string
    {
        $tableColumns = $config['table_columns'] ?? [];
        $columns = $config['columns'] ?? [];

        // If no table columns configured, use first few columns
        if (empty($tableColumns)) {
            $tableColumns = collect($columns)
                ->filter(fn($col) => is_array($col) && isset($col['column_name']))
                ->take(5)
                ->map(fn($col) => [
                    'column' => $col['column_name'],
                    'column_type' => $this->guessColumnType($col),
                    'is_searchable' => in_array($col['column_name'], ['name', 'title', 'email']),
                    'is_sortable' => true,
                ])
                ->toArray();
        }

        $schema = [];

        foreach ($tableColumns as $column) {
            $schema[] = $this->generateTableColumn($column);
        }

        return implode(",\n\n", $schema);
    }

    protected function generateTableColumn(array $column): string
    {
        $columnName = $column['column'];
        $columnType = $column['column_type'] ?? 'text';
        $isSearchable = $column['is_searchable'] ?? false;
        $isSortable = $column['is_sortable'] ?? false;
        $isToggleable = $column['is_toggleable'] ?? false;
        $relationName = $column['relation_name'] ?? null;
        $relationAttribute = $column['relation_attribute'] ?? 'name';

        // Check if this is a foreign key with relationship info
        $isForeignKeyWithRelation = !empty($relationName) && !empty($relationAttribute);

        if ($isForeignKeyWithRelation) {
            // Generate relationship column
            $component = "Tables\\Columns\\TextColumn::make('{$relationName}.{$relationAttribute}')
                    ->label('" . Str::title(str_replace('_', ' ', $columnName)) . "')";
        } else {
            $component = match ($columnType) {
                'text' => "Tables\\Columns\\TextColumn::make('{$columnName}')",
                'badge' => "Tables\\Columns\\BadgeColumn::make('{$columnName}')",
                'boolean' => "Tables\\Columns\\IconColumn::make('{$columnName}')\n                    ->boolean()",
                'icon' => "Tables\\Columns\\IconColumn::make('{$columnName}')",
                'image' => "Tables\\Columns\\ImageColumn::make('{$columnName}')",
                default => "Tables\\Columns\\TextColumn::make('{$columnName}')"
            };
        }

        if ($isSearchable) {
            $component .= "\n                    ->searchable()";
        }

        if ($isSortable) {
            $component .= "\n                    ->sortable()";
        }

        if ($isToggleable) {
            $component .= "\n                    ->toggleable(isToggledHiddenByDefault: true)";
        }

        return "                {$component}";
    }

    protected function generateTableActions(array $config): string
    {
        $actions = $config['table_actions'] ?? ['edit', 'delete'];

        $actionComponents = [];

        if (in_array('view', $actions)) {
            $actionComponents[] = "                Tables\\Actions\\ViewAction::make()";
        }

        if (in_array('edit', $actions)) {
            $actionComponents[] = "                Tables\\Actions\\EditAction::make()";
        }

        if (in_array('delete', $actions)) {
            $actionComponents[] = "                Tables\\Actions\\DeleteAction::make()";
        }

        return implode(",\n", $actionComponents);
    }

    protected function generateBulkActions(array $config): string
    {
        $actions = $config['table_actions'] ?? ['bulk_delete'];

        $bulkActions = [];

        if (in_array('bulk_delete', $actions)) {
            $bulkActions[] = "                    Tables\\Actions\\DeleteBulkAction::make()";
        }

        $bulkActionsString = implode(",\n", $bulkActions);

        return "                Tables\\Actions\\BulkActionGroup::make([
{$bulkActionsString}
                ])";
    }

    protected function generatePagesArray(array $config): string
    {
        $pages = $config['resource_pages'] ?? ['list', 'create', 'edit'];
        $modelName = $config['model_name'];

        $pageEntries = [];

        if (in_array('list', $pages)) {
            $pageEntries[] = "            'index' => Pages\\List{$modelName}s::route('/')";
        }

        if (in_array('create', $pages)) {
            $pageEntries[] = "            'create' => Pages\\Create{$modelName}::route('/create')";
        }

        if (in_array('edit', $pages)) {
            $pageEntries[] = "            'edit' => Pages\\Edit{$modelName}::route('/{record}/edit')";
        }

        if (in_array('view', $pages)) {
            $pageEntries[] = "            'view' => Pages\\View{$modelName}::route('/{record}')";
        }

        return implode(",\n", $pageEntries);
    }

    protected function generateListPage(array $config): string
    {
        $modelName = $config['model_name'];
        $targetPanel = $config['target_panel'] ?? 'admin';
        
        // For admin panel, use the configured path from AdminPanelProvider
        if ($targetPanel === 'admin') {
            $namespace = "App\\Filament\\Resources\\{$modelName}Resource\\Pages";
            $resourceNamespace = "App\\Filament\\Resources\\{$modelName}Resource";
        } else {
            // For other panels, use panel-specific namespaces
            $panelNamespace = $this->getPanelNamespace($targetPanel);
            $namespace = "App\\Filament\\{$panelNamespace}\\Resources\\{$modelName}Resource\\Pages";
            $resourceNamespace = "App\\Filament\\{$panelNamespace}\\Resources\\{$modelName}Resource";
        }

        return "<?php

namespace {$namespace};

use {$resourceNamespace};
use Filament\\Actions;
use Filament\\Resources\\Pages\\ListRecords;

class List{$modelName}s extends ListRecords
{
    protected static string \$resource = {$modelName}Resource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\\CreateAction::make(),
        ];
    }
}";
    }

    protected function generateCreatePage(array $config): string
    {
        $modelName = $config['model_name'];
        $targetPanel = $config['target_panel'] ?? 'admin';
        
        // For admin panel, use the configured path from AdminPanelProvider
        if ($targetPanel === 'admin') {
            $namespace = "App\\Filament\\Resources\\{$modelName}Resource\\Pages";
            $resourceNamespace = "App\\Filament\\Resources\\{$modelName}Resource";
        } else {
            // For other panels, use panel-specific namespaces
            $panelNamespace = $this->getPanelNamespace($targetPanel);
            $namespace = "App\\Filament\\{$panelNamespace}\\Resources\\{$modelName}Resource\\Pages";
            $resourceNamespace = "App\\Filament\\{$panelNamespace}\\Resources\\{$modelName}Resource";
        }

        return "<?php

namespace {$namespace};

use {$resourceNamespace};
use Filament\\Resources\\Pages\\CreateRecord;

class Create{$modelName} extends CreateRecord
{
    protected static string \$resource = {$modelName}Resource::class;
}";
    }

    protected function generateEditPage(array $config): string
    {
        $modelName = $config['model_name'];
        $targetPanel = $config['target_panel'] ?? 'admin';
        
        // For admin panel, use the configured path from AdminPanelProvider
        if ($targetPanel === 'admin') {
            $namespace = "App\\Filament\\Resources\\{$modelName}Resource\\Pages";
            $resourceNamespace = "App\\Filament\\Resources\\{$modelName}Resource";
        } else {
            // For other panels, use panel-specific namespaces
            $panelNamespace = $this->getPanelNamespace($targetPanel);
            $namespace = "App\\Filament\\{$panelNamespace}\\Resources\\{$modelName}Resource\\Pages";
            $resourceNamespace = "App\\Filament\\{$panelNamespace}\\Resources\\{$modelName}Resource";
        }

        return "<?php

namespace {$namespace};

use {$resourceNamespace};
use Filament\\Actions;
use Filament\\Resources\\Pages\\EditRecord;

class Edit{$modelName} extends EditRecord
{
    protected static string \$resource = {$modelName}Resource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\\DeleteAction::make(),
        ];
    }
}";
    }

    protected function generateViewPage(array $config): string
    {
        $modelName = $config['model_name'];
        $targetPanel = $config['target_panel'] ?? 'admin';
        
        // For admin panel, use the configured path from AdminPanelProvider
        if ($targetPanel === 'admin') {
            $namespace = "App\\Filament\\Resources\\{$modelName}Resource\\Pages";
            $resourceNamespace = "App\\Filament\\Resources\\{$modelName}Resource";
        } else {
            // For other panels, use panel-specific namespaces
            $panelNamespace = $this->getPanelNamespace($targetPanel);
            $namespace = "App\\Filament\\{$panelNamespace}\\Resources\\{$modelName}Resource\\Pages";
            $resourceNamespace = "App\\Filament\\{$panelNamespace}\\Resources\\{$modelName}Resource";
        }

        return "<?php

namespace {$namespace};

use {$resourceNamespace};
use Filament\\Actions;
use Filament\\Resources\\Pages\\ViewRecord;

class View{$modelName} extends ViewRecord
{
    protected static string \$resource = {$modelName}Resource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\\EditAction::make(),
        ];
    }
}";
    }

    protected function guessInputType(array $column): string
    {
        if (!isset($column['column_type'])) {
            return 'text';
        }

        return match ($column['column_type']) {
            'text', 'longText' => 'textarea',
            'boolean' => 'toggle',
            'date' => 'datepicker',
            'datetime', 'timestamp' => 'datetimepicker',
            'time' => 'timepicker',
            'json' => 'richtexteditor',
            'enum' => 'select',
            default => 'text'
        };
    }

    protected function guessColumnType(array $column): string
    {
        if (!isset($column['column_type'])) {
            return 'text';
        }

        return match ($column['column_type']) {
            'boolean' => 'boolean',
            'enum' => 'badge',
            default => 'text'
        };
    }

    protected function findOriginalColumn(string $columnName, array $config): ?array
    {
        $columns = $config['columns'] ?? [];
        return collect($columns)->firstWhere('column_name', $columnName);
    }

    protected function generateSelectComponent(string $column, bool $isForeignKey, ?string $foreignTable): string
    {
        if ($isForeignKey && $foreignTable) {
            // Generate a select with relationship
            $foreignModelName = Str::studly(Str::singular($foreignTable));

            return "Forms\\Components\\Select::make('{$column}')
                            ->relationship(name: '" . Str::singular(str_replace('_id', '', $column)) . "', titleAttribute: 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\\Components\\TextInput::make('name')->required(),
                            ])";
        }

        // Regular select with empty options
        return "Forms\\Components\\Select::make('{$column}')
                            ->options([])";
    }
}
