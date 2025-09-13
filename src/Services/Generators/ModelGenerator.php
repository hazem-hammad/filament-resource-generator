<?php

namespace Intcore\FilamentResourceGenerator\Services\Generators;

use Illuminate\Support\Str;

class ModelGenerator
{
    public function generate(array $config): string
    {
        $modelName = $config['model_name'];
        $tableName = $config['table_name'];
        $columns = $config['columns'] ?? [];

        // Extract fillable fields
        $fillable = collect($columns)
            ->filter(fn($col) => is_array($col) && isset($col['column_name']))
            ->reject(fn($col) => in_array($col['column_name'], ['id', 'created_at', 'updated_at', 'deleted_at']))
            ->pluck('column_name')
            ->map(fn($name) => "        '{$name}'")
            ->join(",\n");

        // Extract casts
        $casts = $this->generateCasts($columns);
        
        // Generate relationships
        $relationships = $this->generateRelationships($columns);

        // Check if soft deletes should be used
        $usesSoftDeletes = collect($columns)->contains(fn($col) => $col['column_name'] === 'deleted_at');

        $softDeletesImport = $usesSoftDeletes ? "\nuse Illuminate\\Database\\Eloquent\\SoftDeletes;" : '';
        $softDeletesTrait = $usesSoftDeletes ? "\n    use SoftDeletes;" : '';

        return "<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;
use Illuminate\\Database\\Eloquent\\Model;{$softDeletesImport}

class {$modelName} extends Model
{
    use HasFactory;{$softDeletesTrait}

    protected \$table = '{$tableName}';

    protected \$fillable = [
{$fillable}
    ];
{$casts}
{$relationships}
}";
    }

    protected function generateCasts(array $columns): string
    {
        $casts = collect($columns)
            ->filter(fn($col) => is_array($col) && isset($col['column_name']) && isset($col['column_type']))
            ->filter(fn($col) => $this->shouldCast($col))
            ->map(fn($col) => "        '{$col['column_name']}' => '{$this->getCastType($col)}'")
            ->join(",\n");

        return $casts ? "\n    protected \$casts = [\n{$casts}\n    ];" : '';
    }

    protected function shouldCast(array $column): bool
    {
        if (!isset($column['column_type'])) {
            return false;
        }
        
        return in_array($column['column_type'], [
            'boolean', 'json', 'date', 'datetime', 'timestamp', 'decimal', 'float', 'double'
        ]);
    }

    protected function getCastType(array $column): string
    {
        if (!isset($column['column_type'])) {
            return 'string';
        }
        
        return match ($column['column_type']) {
            'boolean' => 'boolean',
            'json' => 'json',
            'date' => 'date',
            'datetime', 'timestamp' => 'datetime',
            'decimal' => 'decimal:2',
            'float', 'double' => 'float',
            default => 'string'
        };
    }

    protected function generateRelationships(array $columns): string
    {
        $relationships = '';
        
        foreach ($columns as $column) {
            if (!is_array($column) || !isset($column['column_type']) || !isset($column['column_name'])) {
                continue;
            }
            
            if ($column['column_type'] === 'foreignId' && isset($column['foreign_table'])) {
                $foreignTable = $column['foreign_table'];
                $relationName = Str::singular(str_replace('_id', '', $column['column_name']));
                $foreignModel = Str::studly(Str::singular($foreignTable));
                
                $relationships .= "\n    public function {$relationName}()
    {
        return \$this->belongsTo({$foreignModel}::class);
    }\n";
            }
        }

        return $relationships;
    }
}