<?php

namespace Intcore\FilamentResourceGenerator\Filament\Pages;

use Intcore\FilamentResourceGenerator\Services\ModuleGeneratorService;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ModuleGenerator extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Development Tools';

    protected static ?string $navigationLabel = 'Module Generator';

    protected static string $view = 'filament-resource-generator::module-generator';

    protected static ?int $navigationSort = 1;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
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
                                    Forms\Components\Toggle::make('generate_filament_resource')
                                        ->label('Generate Filament Resource')
                                        ->default(true),

                                    Forms\Components\Toggle::make('add_timestamps')
                                        ->label('Add Timestamps (created_at, updated_at)')
                                        ->default(true),
                                ])
                                ->columns(2),
                        ])
                        ->columnSpanFull()
                    ])
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('generate')
                ->label('Generate Module')
                ->action('generate')
                ->color('success'),
        ];
    }

    public function generate(): void
    {
        try {
            $data = $this->form->getState();
            
            // Basic validation
            if (empty($data['module_name']) || empty($data['table_name']) || empty($data['model_name'])) {
                throw new \Exception('All required fields must be filled');
            }

            // Check if table already exists
            if (Schema::hasTable($data['table_name'])) {
                throw new \Exception("Table '{$data['table_name']}' already exists");
            }

            // Add basic column structure
            $data['columns'] = [
                ['column_name' => 'name', 'column_type' => 'string', 'is_nullable' => false],
                ['column_name' => 'description', 'column_type' => 'text', 'is_nullable' => true],
            ];

            $generator = new ModuleGeneratorService();
            $result = $generator->generateModule($data);

            // Clear caches
            Artisan::call('optimize:clear');
            
            Notification::make()
                ->title('Module Generated Successfully!')
                ->body("Generated module '{$data['module_name']}' with " . count($result['files']) . " files.")
                ->success()
                ->send();
                
            // Reset form for next generation
            $this->form->fill([]);
            
        } catch (\Exception $e) {
            Notification::make()
                ->title('Generation Failed')
                ->body('Error: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}