# Filament Resource Generator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/intcore/filament-resource-generator.svg?style=flat-square)](https://packagist.org/packages/intcore/filament-resource-generator)
[![Total Downloads](https://img.shields.io/packagist/dt/intcore/filament-resource-generator.svg?style=flat-square)](https://packagist.org/packages/intcore/filament-resource-generator)

A powerful Laravel Filament package for generating complete modules with models, migrations, factories, seeders, and admin resources through an intuitive web interface.

## Features

-   🚀 **Complete Module Generation**: Generate models, migrations, factories, seeders, and Filament resources
-   🎯 **Intuitive Web Interface**: User-friendly wizard-based form in Filament admin
-   🔗 **Smart Relationships**: Automatic foreign key detection and relationship configuration
-   📊 **Table Introspection**: Intelligent dropdown selection for existing models and columns
-   🎨 **Customizable Forms**: Multiple input types (text, textarea, select, toggle, date pickers, etc.)
-   📝 **Auto-population**: Smart defaults based on column types and naming conventions
-   🏃‍♂️ **Auto Execution**: Optional automatic migration and seeder execution
-   🧪 **Factory Integration**: Automatic factory generation with seeder dependencies

## Installation

You can install the package via Composer:

```bash
composer require intcore/filament-resource-generator:^1.0
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="filament-resource-generator-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="filament-resource-generator-config"
```

Or install everything at once:

```bash
php artisan module-generator:install
```

## Usage

1. **Access the Module Generator**: Navigate to your Filament admin panel and look for "Module Generator" in the navigation menu.

2. **Create a New Module**:

    - Fill in the module information (name, description, etc.)
    - Define your database schema (create new tables or use existing ones)
    - Configure your Filament resource (forms, tables, pages, actions)

3. **Generate**: Click generate and watch as your complete module is created with all necessary files.

### Example: Creating a Blog Module

1. **Module Information**:

    - Module Name: `Post`
    - Table Name: `posts`
    - Description: `Blog post management`

2. **Database Schema**:

    - `title` (string, required)
    - `content` (longText)
    - `category_id` (foreignId → categories table)
    - `is_published` (boolean)
    - `published_at` (datetime, nullable)

3. **Filament Configuration**:
    - Form Fields: Auto-configured based on column types
    - Table Columns: Smart relationship display (category.name instead of category_id)
    - Pages: List, Create, Edit
    - Actions: Edit, Delete, Bulk Delete

## Generated Files

The package generates:

-   **Model**: `app/Models/Post.php` with relationships and fillable fields
-   **Migration**: `database/migrations/create_posts_table.php`
-   **Factory**: `database/factories/PostFactory.php`
-   **Seeder**: `database/seeders/PostSeeder.php`
-   **Filament Resource**: `app/Filament/Resources/PostResource.php`
-   **Resource Pages**: Create, Edit, List pages

## Configuration

You can customize the package behavior by editing `config/filament-resource-generator.php`:

```php
return [
    'model_path' => 'app/Models',
    'migration_path' => 'database/migrations',
    'factory_path' => 'database/factories',
    'seeder_path' => 'database/seeders',
    'resource_path' => 'app/Filament/Resources',
    'namespace_prefix' => 'App',
    'auto_run_migrations' => false,
    'auto_run_seeders' => false,
];
```

## Advanced Features

### Foreign Key Relationships

The package intelligently handles foreign key relationships:

-   **Auto-detection**: `category_id` column automatically suggests `Category` model
-   **Dynamic Dropdowns**: Select from actual models and their database columns
-   **Smart Display**: Table columns show `category.name` instead of raw IDs
-   **Relationship Forms**: Foreign key fields become searchable select dropdowns with inline creation

### Existing Table Support

You can build Filament resources for existing tables:

1. Select "Use Existing Table" in Database Schema
2. Choose from available tables
3. Automatic column detection and type mapping
4. Configure forms and tables based on actual database structure

### Custom Input Types

Support for various Filament input types:

-   Text Input, Textarea, Rich Text Editor
-   Select, Toggle, Tags Input
-   Date Picker, DateTime Picker, Time Picker
-   File Upload, Color Picker
-   And more...

## Requirements

-   PHP 8.1+
-   Laravel 10.0+
-   Filament 3.0+

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

-   [Hazem Hamaad](http://github.com/hazem-hammad)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
