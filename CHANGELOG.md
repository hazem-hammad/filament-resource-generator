# Changelog

All notable changes to `filament-resource-generator` will be documented in this file.

## 1.0.0 - 2024-09-13

### Added
- Initial release of Filament Resource Generator
- Complete module generation with models, migrations, factories, seeders, and Filament resources
- Intuitive wizard-based web interface in Filament admin
- Smart foreign key relationship detection and configuration
- Dynamic model and column selection dropdowns
- Support for existing table introspection
- Multiple input types for form generation
- Auto-population of relationship fields
- Optional automatic migration and seeder execution
- Comprehensive form and table column configuration
- Smart defaults based on column types and naming conventions

### Features
- **Module Information Step**: Configure basic module details
- **Database Schema Step**: Create new tables or use existing ones with column introspection
- **Filament Resource Configuration Step**: 
  - Navigation settings
  - Resource pages selection
  - Form fields configuration with auto-detection
  - Table columns configuration with relationship support

### Generated Files
- Eloquent Models with relationships and fillable fields
- Laravel Migrations with proper column types and constraints
- Model Factories with realistic fake data
- Database Seeders with factory integration
- Filament Resources with forms, tables, and pages
- Resource Pages (List, Create, Edit, View)

### Smart Features
- Foreign key auto-detection (e.g., `admin_id` → `Admin` model)
- Relationship column display (e.g., `admin.name` instead of `admin_id`)
- Dynamic dropdown population based on actual database structure
- Intelligent input type mapping based on column types
- Form field auto-configuration with validation rules