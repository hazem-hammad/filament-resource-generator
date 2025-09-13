<?php

namespace Intcore\FilamentResourceGenerator\Services\Generators;

use Illuminate\Support\Str;

class MigrationGenerator
{
    public function generate(array $config): string
    {
        $tableName = $config['table_name'];
        $className = 'Create' . Str::studly($tableName) . 'Table';
        $columns = $config['columns'] ?? [];

        // Generate column definitions
        $columnDefinitions = $this->generateColumnDefinitions($columns);
        
        // Generate indexes
        $indexes = $this->generateIndexes($columns);

        return "<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$tableName}', function (Blueprint \$table) {
            \$table->id();
{$columnDefinitions}
{$indexes}
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$tableName}');
    }
};";
    }

    protected function generateColumnDefinitions(array $columns): string
    {
        $definitions = [];

        foreach ($columns as $column) {
            // Skip auto-generated columns
            if (in_array($column['column_name'], ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }

            $definition = $this->generateColumnDefinition($column);
            if ($definition) {
                $definitions[] = "            {$definition}";
            }
        }

        return implode("\n", $definitions);
    }

    protected function generateColumnDefinition(array $column): string
    {
        $name = $column['column_name'];
        $type = $column['column_type'];
        $nullable = $column['is_nullable'] ?? false;
        $default = $column['default_value'] ?? null;
        $unique = $column['is_unique'] ?? false;

        $definition = '';

        switch ($type) {
            case 'string':
                $length = $column['string_length'] ?? 255;
                $definition = "\$table->string('{$name}', {$length})";
                break;

            case 'text':
                $definition = "\$table->text('{$name}')";
                break;

            case 'longText':
                $definition = "\$table->longText('{$name}')";
                break;

            case 'integer':
                $definition = "\$table->integer('{$name}')";
                break;

            case 'bigInteger':
                $definition = "\$table->bigInteger('{$name}')";
                break;

            case 'boolean':
                $definition = "\$table->boolean('{$name}')";
                break;

            case 'json':
                $definition = "\$table->json('{$name}')";
                break;

            case 'date':
                $definition = "\$table->date('{$name}')";
                break;

            case 'time':
                $definition = "\$table->time('{$name}')";
                break;

            case 'dateTime':
                $definition = "\$table->dateTime('{$name}')";
                break;

            case 'timestamp':
                $definition = "\$table->timestamp('{$name}')";
                break;

            case 'decimal':
                $precision = $column['decimal_precision'] ?? 8;
                $scale = $column['decimal_scale'] ?? 2;
                $definition = "\$table->decimal('{$name}', {$precision}, {$scale})";
                break;

            case 'float':
                $definition = "\$table->float('{$name}')";
                break;

            case 'double':
                $definition = "\$table->double('{$name}')";
                break;

            case 'enum':
                $values = $column['enum_values'] ?? [];
                if (is_array($values) && !empty($values)) {
                    $enumValues = "'" . implode("', '", $values) . "'";
                    $definition = "\$table->enum('{$name}', [{$enumValues}])";
                }
                break;

            case 'foreignId':
                $foreignTable = $column['foreign_table'] ?? null;
                if ($foreignTable) {
                    $definition = "\$table->foreignId('{$name}')->constrained('{$foreignTable}')";
                }
                break;

            default:
                $definition = "\$table->string('{$name}')";
                break;
        }

        if (!$definition) {
            return '';
        }

        // Add modifiers
        if ($nullable) {
            $definition .= "->nullable()";
        }

        if ($default !== null && $default !== '') {
            if (is_string($default)) {
                $definition .= "->default('{$default}')";
            } else {
                $definition .= "->default({$default})";
            }
        }

        if ($unique) {
            $definition .= "->unique()";
        }

        return $definition . ';';
    }

    protected function generateIndexes(array $columns): string
    {
        $indexes = [];

        foreach ($columns as $column) {
            if ($column['is_indexed'] ?? false) {
                $name = $column['column_name'];
                $indexes[] = "            \$table->index('{$name}');";
            }
        }

        // Add timestamps if requested
        if ($this->hasTimestamps($columns)) {
            $indexes[] = "            \$table->timestamps();";
        }

        // Add soft deletes if requested
        if ($this->hasSoftDeletes($columns)) {
            $indexes[] = "            \$table->softDeletes();";
        }

        return implode("\n", $indexes);
    }

    protected function hasTimestamps(array $columns): bool
    {
        return collect($columns)->contains(fn($col) => $col['column_name'] === 'created_at');
    }

    protected function hasSoftDeletes(array $columns): bool
    {
        return collect($columns)->contains(fn($col) => $col['column_name'] === 'deleted_at');
    }
}