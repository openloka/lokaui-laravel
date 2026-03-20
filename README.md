# LokaUI CLI for Laravel

Add beautiful, accessible LokaUI Blade components to your Laravel project via Artisan commands.

## Installation

```bash
composer require lokaui/cli --dev
```

## Usage

### Initialize

```bash
php artisan lokaui:init
```

Sets up `lokaui.json` config and optionally adds LokaUI CSS tokens to your stylesheet.

### Add components

```bash
# Add a single component
php artisan lokaui:add button

# Add multiple components
php artisan lokaui:add button input badge

# Interactive picker
php artisan lokaui:add

# Add all available components
php artisan lokaui:add --all
```

Components are copied to `resources/views/components/ui/` by default (configurable).

### Use in Blade

```blade
<x-ui.button variant="primary" size="md">
    Click me
</x-ui.button>
```

### List available components

```bash
php artisan lokaui:list
```

## Options

| Flag | Description |
|------|-------------|
| `--all` | Install all available components |
| `--overwrite` | Overwrite existing components |
| `--dry-run` | Preview what would be installed |

## Requirements

- PHP >= 8.1
- Laravel 10, 11, or 12

## Links

- [LokaUI Documentation](https://lokaui.dev)
- [Component Library](https://github.com/openloka/LokaUI)

## License

MIT
