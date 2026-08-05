# Example extension blocks

This directory contains two blocks registered outside the active theme:

- `editable-card` is a static `view.html` block. Its HTML is editable directly in Vihzhuo, and its link uses the built-in link settings.
- `page-banner` is a dynamic `view.php` block. It demonstrates block settings, a custom model, a custom controller, safe URL handling, wrapper selection, and cache configuration.

[`register-blocks.php`](register-blocks.php) maps public block slugs to their directories with `Extensions::addBlocks()`. [`index.php`](index.php) loads that registration file after Composer's autoloader and before constructing `Vihzhuo`:

```php
require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/register-blocks.php';

$application = new Vihzhuo($config);
```

Once the example front controller is running, both blocks appear in the page builder under the **Examples** category. Extension block directories use the same `config.php`, `view.html`/`view.php`, `model.php`, `controller.php`, and script conventions as theme blocks.
