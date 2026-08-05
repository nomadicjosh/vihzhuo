# Vihzhuo

Vihzhuo is a framework-agnostic drag-and-drop page builder for PHP applications. It is a fork of [PHPageBuilder](https://github.com/HansSchouten/PHPageBuilder), modernized for PHP 8.4, strict types, PSR-7 HTTP messages through `qubus/http`, GrapesJS 0.23, and PHPStan level `max`.

Vihzhuo provides:

- a website manager for creating pages, routes, layouts, and translations;
- a GrapesJS page builder with blocks, block settings, responsive previews, an asset manager, a Style Manager, and a built-in rich-text editor;
- convention-based themes, layouts, HTML blocks, and server-rendered PHP blocks;
- PSR-7 entry points that can run standalone or behind an existing framework;
- replaceable authentication, routing, storage records, repositories, caching, themes, and page-builder services;
- extension APIs for blocks, layouts, CSS, and JavaScript supplied by application code or Composer packages.

## Table of contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Running Vihzhuo](#running-vihzhuo)
- [Integrating with an existing project or framework](#integrating-with-an-existing-project-or-framework)
- [Creating a theme](#creating-a-theme)
- [Creating a layout](#creating-a-layout)
- [Creating blocks](#creating-blocks)
- [Block settings and field types](#block-settings-and-field-types)
- [Nested blocks and shortcodes](#nested-blocks-and-shortcodes)
- [Custom block models and controllers](#custom-block-models-and-controllers)
- [Block JavaScript](#block-javascript)
- [Adding CSS and JavaScript assets](#adding-css-and-javascript-assets)
- [Registering blocks and layouts from application code](#registering-blocks-and-layouts-from-application-code)
- [Customizing GrapesJS](#customizing-grapesjs)
- [Routing and multilingual pages](#routing-and-multilingual-pages)
- [Uploads and the Asset Manager](#uploads-and-the-asset-manager)
- [Caching](#caching)
- [Security notes](#security-notes)
- [Development and quality checks](#development-and-quality-checks)

## Requirements

- PHP 8.4 or newer;
- PDO and the PDO driver for your database;
- JSON and fileinfo PHP extensions;
- Composer 2;
- a writable uploads directory;
- Node.js 20 or newer only when rebuilding the browser assets.

The checked-in `dist/` directory contains the browser assets needed by an application. Application users do not need Node.js unless they modify Vihzhuo's JavaScript or Sass sources.

## Installation

Install the package:

```bash
composer require nomadicjosh/vihzhuo
```

When working from this repository instead of consuming a release:

```bash
composer install
npm install
npm run production
```

Copy the example configuration and adjust it for the application:

```bash
cp config/config.example.php config/config.php
```

Create the `pages`, `page_translations`, `uploads`, and `settings` tables. [`config/create-tables.sql`](config/create-tables.sql) contains the MySQL/MariaDB schema. When using SQLite or another PDO database, create equivalent tables and preserve the column names and unique constraints used by that schema.

The application must make these locations writable by the PHP process when enabled:

```text
storage.uploads_folder
cache.folder
```

## Configuration

The complete annotated example is in [`config/config.example.php`](config/config.example.php). The most important options are:

| Key                                      | Purpose                                                                                                         |
|------------------------------------------|-----------------------------------------------------------------------------------------------------------------|
| `general.base_url`                       | Absolute application URL, including a subdirectory when applicable.                                             |
| `general.language`                       | Default locale, such as `en`.                                                                                   |
| `general.assets_url`                     | Public URL prefix through which Vihzhuo serves its `dist/` files.                                               |
| `general.uploads_url`                    | Public URL prefix used for uploaded files.                                                                      |
| `storage.use_database`                   | Enables the built-in PDO-backed repositories. The default manager requires it.                                  |
| `storage.database.driver`                | Database driver name. Use `sqlite` to enable SQLite foreign keys.                                               |
| `storage.database.dsn`                   | PDO DSN, for example `sqlite:/absolute/path/site.sqlite` or `mysql:host=127.0.0.1;dbname=site;charset=utf8mb4`. |
| `storage.database.username` / `password` | PDO credentials; these may be `null` for SQLite.                                                                |
| `storage.database.options`               | Optional PDO options.                                                                                           |
| `storage.database.prefix`                | Optional prefix prepended to Vihzhuo table names.                                                               |
| `storage.uploads_folder`                 | Absolute directory used by the image Asset Manager.                                                             |
| `auth.use_login`                         | Enables the bundled session login flow.                                                                         |
| `auth.class`                             | Class implementing `AuthContract`.                                                                              |
| `auth.url`                               | URL of the login endpoint.                                                                                      |
| `website_manager.use_website_manager`    | Enables the bundled page-management interface.                                                                  |
| `website_manager.class`                  | Class implementing `WebsiteManagerContract`.                                                                    |
| `website_manager.url`                    | Base URL of the manager.                                                                                        |
| `pagebuilder.class`                      | Class implementing `PageBuilderContract`.                                                                       |
| `pagebuilder.url`                        | URL of the GrapesJS editor.                                                                                     |
| `pagebuilder.actions.back`               | Destination of the editor's Back button.                                                                        |
| `page.class`                             | Page record implementing `PageContract`.                                                                        |
| `page.table`                             | Pages table name without the configured prefix.                                                                 |
| `page.translation.*`                     | Translation class, table, and page foreign key.                                                                 |
| `cache.enabled`                          | Enables rendered-page caching.                                                                                  |
| `cache.folder`                           | Absolute cache directory.                                                                                       |
| `cache.class`                            | Class implementing `CacheContract`.                                                                             |
| `theme.folder`                           | Absolute parent directory containing all themes.                                                                |
| `theme.folder_url`                       | Public URL corresponding to `theme.folder`.                                                                     |
| `theme.active_theme`                     | Folder name of the active theme.                                                                                |
| `router.class`                           | Class implementing `RouterContract`.                                                                            |
| `class_replacements`                     | Map of concrete Vihzhuo classes to compatible application replacements.                                         |

A typical PDO configuration is:

```php
<?php

use PDO;

return [
    'storage' => [
        'use_database' => true,
        'database' => [
            'driver' => 'mysql',
            'dsn' => 'mysql:host=127.0.0.1;dbname=website;charset=utf8mb4',
            'username' => 'website',
            'password' => $_ENV['DB_PASSWORD'],
            'prefix' => 'cms_',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        ],
        'uploads_folder' => dirname(__DIR__) . '/storage/uploads',
    ],

    // Include the remaining sections from config.example.php.
];
```

Do not store production credentials directly in a committed configuration file. Populate them from the host application's secret or environment configuration.

## Running Vihzhuo

### Standalone PSR-7 front controller

Vihzhuo consumes a `Psr\Http\Message\ServerRequestInterface` and returns a `Psr\Http\Message\ResponseInterface`. A standalone SAPI entry point can use the factories and emitter supplied by `qubus/http`:

```php
<?php

declare(strict_types=1);

use Qubus\Http\Emitter\SapiEmitter;
use Qubus\Http\ServerRequestFactory;
use Vihzhuo\Vihzhuo;

require dirname(__DIR__) . '/vendor/autoload.php';

$config = require dirname(__DIR__) . '/config/config.php';
if (!is_array($config)) {
    throw new RuntimeException('Vihzhuo configuration must return an array.');
}

$vihzhuo = new Vihzhuo($config);
$request = ServerRequestFactory::fromGlobals();
$response = $vihzhuo->handleRequest($request);

(new SapiEmitter())->emit($response);
```

A complete example is available in [`examples/index.php`](examples/index.php).

All manager, page-builder, public-page, upload, and Vihzhuo asset requests must reach this front controller. With PHP's development server:

```bash
php -S 127.0.0.1:8080 -t public
```

Using the example `/admin` URLs, open:

```text
http://127.0.0.1:8080/admin
```

In Apache, Nginx, Caddy, or another web server, configure the usual front-controller fallback so URLs such as `/admin/pagebuilder`, `/assets/pagebuilder/app.js`, and public page routes are sent to `public/index.php` when they are not physical files.

## Integrating with an existing project or framework

### Frameworks that expose PSR-7 requests

Pass the framework request directly to Vihzhuo and return its response:

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Vihzhuo\Vihzhuo;

final class PageBuilderRoute
{
    public function __construct(private readonly Vihzhuo $vihzhuo)
    {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return $this->vihzhuo->handleRequest($request);
    }
}
```

For frameworks using a different request/response implementation, use that framework's PSR-7 bridge at the boundary. Keep Vihzhuo itself on PSR-7 messages.

### Letting the host application own authentication and routing

Set `auth.use_login` to `false`, protect the manager routes with the host application's authorization middleware, and use the split handlers instead of `handleRequest()`:

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Vihzhuo\Vihzhuo;

/** @return ResponseInterface|null */
function handleCmsRequest(
    Vihzhuo $vihzhuo,
    ServerRequestInterface $request,
    bool $userCanManagePages
): ?ResponseInterface {
    // Public pages, Vihzhuo assets, and uploaded images.
    $publicResponse = $vihzhuo->handlePublicRequest($request);
    if ($publicResponse !== null) {
        return $publicResponse;
    }

    // Apply the host application's authentication/authorization before this.
    if ($userCanManagePages) {
        $managerResponse = $vihzhuo->handleAuthenticatedRequest($request);
        if ($managerResponse !== null) {
            return $managerResponse;
        }
    }

    return null; // Let the next application route or middleware run.
}
```

`handleAuthenticatedRequest()` recognizes the configured `website_manager.url` and `pagebuilder.url`. It does not perform an authorization check; the host application must do that before calling it.

If the host already resolved a page and only needs the editor response:

```php
use Vihzhuo\Contracts\PageContract;

/** @var PageContract $page */
$response = $vihzhuo->renderPageBuilder($page);
```

To render an already resolved page without using the database router:

```php
$html = $vihzhuo->getPageBuilder()?->renderPage($page, 'en') ?? '';
```

### Replacing services

Services can be configured by interface-compatible class:

```php
return [
    'auth' => [
        'use_login' => true,
        'class' => App\PageBuilder\CurrentUserAuth::class,
        'url' => '/cms/login',
    ],
    'router' => [
        'class' => App\PageBuilder\ApplicationRouter::class,
    ],
    // ...
];
```

Vihzhuo also exposes explicit setters for dependency injection:

```php
$vihzhuo->setAuth($auth);
$vihzhuo->setRouter($router);
$vihzhuo->setTheme($theme);
$vihzhuo->setPageBuilder($pageBuilder);
$vihzhuo->setWebsiteManager($websiteManager);
```

Concrete internal classes can be replaced where Vihzhuo uses `phpb_instance()`:

```php
use Vihzhuo\Modules\GrapesJS\Block\BlockAdapter;

'class_replacements' => [
    BlockAdapter::class => App\PageBuilder\CustomBlockAdapter::class,
],
```

Replacement classes should extend the original concrete class unless the configuration point explicitly requires an interface.

See [`UPGRADE.md`](UPGRADE.md) for the typed contracts and other breaking changes from the original library.

## Creating a theme

`theme.folder` points to the directory containing theme folders, and `theme.active_theme` chooses one of them:

```php
'theme' => [
    'class' => Vihzhuo\Theme::class,
    'folder' => dirname(__DIR__) . '/public/themes',
    'folder_url' => '/themes',
    'active_theme' => 'acme',
],
```

A full theme can contain:

```text
public/themes/acme/
├── blocks/
│   ├── hero/
│   │   ├── config.php
│   │   └── view.html
│   ├── product-grid/
│   │   ├── config.php
│   │   ├── view.php
│   │   ├── model.php             # optional
│   │   ├── controller.php        # optional
│   │   ├── script.js             # optional public block script
│   │   └── builder-script.js     # optional editor-specific script
│   ├── elements/                 # optional organizational folder
│   ├── php/                      # optional organizational folder
│   └── archived/                 # optional organizational folder
├── layouts/
│   └── main/
│       ├── config.php            # optional
│       └── view.php
├── translations/
│   ├── en.php                    # optional
│   └── es.php                    # optional
├── css/
│   └── theme.css
├── js/
│   └── theme.js
└── images/
    └── logo.svg
```

Blocks are discovered directly under `blocks/`, `blocks/elements/`, `blocks/php/`, and `blocks/archived/`. Layouts are discovered directly under `layouts/`.

Theme translation files return arrays and are merged over the built-in language file:

```php
<?php

return [
    'pagebuilder.default-category' => 'Acme blocks',
];
```

Use `phpb_theme_asset()` whenever a layout or PHP block needs the public URL of a theme file:

```php
<img src="<?= phpb_theme_asset('images/logo.svg') ?>" alt="Acme">
```

Static HTML can use the equivalent shortcode:

```html
<img src="[theme-url]/images/logo.svg" alt="Acme">
```

## Creating a layout

A layout is a folder under `layouts/` containing `view.php` and, optionally, `config.php`.

`public/themes/acme/layouts/main/config.php`:

```php
<?php

return [
    'title' => 'Main layout',
];
```

The title appears in the page form. Without it, Vihzhuo derives a title from the layout slug.

`public/themes/acme/layouts/main/view.php`:

```php
<!doctype html>
<html lang="<?= phpb_e(phpb_current_language()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= phpb_e((string) $page->getTranslation('meta_title')) ?></title>
    <meta
        name="description"
        content="<?= phpb_e((string) $page->getTranslation('meta_description')) ?>"
    >

    <link rel="stylesheet" href="<?= phpb_theme_asset('css/theme.css') ?>">
    <?php phpb_registered_assets('header'); ?>
</head>
<body>
    <header class="site-header">
        <a href="<?= phpb_full_url('/') ?>">Acme</a>
    </header>

    <main id="content">
        <?= $body ?>
    </main>

    <script src="<?= phpb_theme_asset('js/theme.js') ?>" defer></script>
    <?php phpb_registered_assets('footer'); ?>
</body>
</html>
```

The layout receives these variables:

| Variable    | Type           | Purpose                                                                             |
|-------------|----------------|-------------------------------------------------------------------------------------|
| `$page`     | `PageContract` | Current page record and translated metadata.                                        |
| `$renderer` | `PageRenderer` | Advanced block/body rendering and shortcode parsing.                                |
| `$body`     | `string`       | Editable page content, or its rendered frontend HTML. Output this without escaping. |

Layout CSS links remain available inside the GrapesJS canvas. External layout scripts are transferred into the canvas script configuration and loaded in their original order.

## Creating blocks

There are two block kinds, selected by the view filename.

| View        | Behavior                                                                                         | PHP block settings                                      |
|-------------|--------------------------------------------------------------------------------------------------|---------------------------------------------------------|
| `view.html` | Editable HTML. GrapesJS stores the edited markup with the page.                                  | No; settings are intentionally omitted for HTML blocks. |
| `view.php`  | Dynamic/server-rendered block. Vihzhuo stores settings and rerenders it after a setting changes. | Yes.                                                    |

### Static HTML block

Create `public/themes/acme/blocks/hero/config.php`:

```php
<?php

return [
    'category' => 'Marketing',
    'title' => 'Hero',
    'icon' => 'fa fa-star',
];
```

Create `public/themes/acme/blocks/hero/view.html`:

```html
<section class="hero">
    <p class="eyebrow">New release</p>
    <h1>Build the next great thing</h1>
    <p>Double-click this text to edit it.</p>
    <a class="button" href="/contact">Talk to us</a>
</section>
```

The block is discovered automatically. Common text, link, and image components receive the editor controls appropriate to their element type.

### Dynamic PHP block with settings

Create `public/themes/acme/blocks/callout/config.php`:

```php
<?php

return [
    'category' => 'Marketing',
    'title' => 'Callout',
    'icon' => 'fa fa-bullhorn',
    'wrapper' => 'section',
    'cache' => true,
    'cache_lifetime' => 60,
    'settings' => [
        'heading' => [
            'type' => 'text',
            'label' => 'Heading',
            'value' => 'Ready to get started?',
            'placeholder' => 'Callout heading',
        ],
        'tone' => [
            'type' => 'select',
            'label' => 'Tone',
            'options' => [
                ['value' => 'callout-info', 'label' => 'Information'],
                ['value' => 'callout-success', 'label' => 'Success'],
                ['value' => 'callout-warning', 'label' => 'Warning'],
            ],
            'value' => 'callout-info',
        ],
        'show_link' => [
            'type' => 'yes_no',
            'label' => 'Show link',
            'value' => '1',
        ],
        'link_url' => [
            'type' => 'text',
            'label' => 'Link URL',
            'value' => '/contact',
        ],
    ],
];
```

Create `public/themes/acme/blocks/callout/view.php`:

```php
<?php
$showLink = $block->setting('show_link') === '1';
?>
<aside class="callout <?= $block->setting('tone') ?>">
    <h2><?= $block->setting('heading') ?></h2>

    <?php if ($showLink): ?>
        <a href="<?= $block->setting('link_url') ?>">Contact us</a>
    <?php endif; ?>
</aside>
```

`BaseModel::setting()` escapes values by default, making it appropriate for normal HTML text and attributes. Only use `$block->setting('name', true)` for intentionally trusted HTML that has been sanitized by the application.

Dynamic block views receive:

| Variable              | Type                        | Purpose                                                |
|-----------------------|-----------------------------|--------------------------------------------------------|
| `$block`              | `BaseModel` or custom model | Settings and application data for this block instance. |
| `$page`               | `PageContract`              | Current page.                                          |
| `$renderer`           | `BlockRenderer`             | Render another block programmatically.                 |
| `$hasSkeleton`        | `bool`                      | Whether the model enabled skeleton rendering.          |
| `$hasDynamicSkeleton` | `bool`                      | Whether the skeleton contains dynamic preview content. |

### Block configuration reference

| Key              | Type           | Meaning                                                                                                                                                                                                          |
|------------------|----------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `title`          | `string`       | Sidebar label. Defaults to a title derived from the slug.                                                                                                                                                        |
| `category`       | `string`       | Sidebar category. Defaults to the translated default category.                                                                                                                                                   |
| `icon`           | `string`       | Font Awesome class used when no generated thumbnail exists.                                                                                                                                                      |
| `hidden`         | `bool`         | When `true`, the block can be rendered by another block but is hidden from the block picker.                                                                                                                     |
| `settings`       | `array`        | Settings shown for dynamic `view.php` blocks.                                                                                                                                                                    |
| `wrapper`        | `string`       | Trusted HTML tag used to wrap a styled dynamic block; defaults to `div`.                                                                                                                                         |
| `cache`          | `bool`         | Set to `false` when this block makes a rendered page unsafe to cache.                                                                                                                                            |
| `cache_lifetime` | positive `int` | Maximum cache lifetime, in minutes, for a rendered page containing this block. When multiple cached blocks specify a lifetime, the shortest lifetime wins. The default upper limit is one week (10,080 minutes). |
| `whitelist`      | `list<string>` | Only register the block when the current full URL contains one of these trusted domain/string values.                                                                                                            |
| `namespace`      | `string`       | Namespace containing this block's optional `Model` and `Controller` classes.                                                                                                                                     |

`cache_lifetime` only affects rendered-page caching when global caching is enabled and the block permits caching. Omit it to retain the current page lifetime. Use a positive whole number to shorten the lifetime; numeric strings are accepted for compatibility. To prevent a page containing the block from being cached at all, set `'cache' => false` instead of setting the lifetime to zero.

For example, if a page contains one block with a 60-minute lifetime and another with a 15-minute lifetime, the complete rendered page expires after 15 minutes:

```php
return [
    'cache' => true,
    'cache_lifetime' => 15,
];
```

Example of a block available only on selected installations:

```php
return [
    'title' => 'Customer portal',
    'whitelist' => ['customer.example.com', 'portal.test'],
];
```

## Block settings and field types

Settings belong to dynamic `view.php` blocks. Each key becomes the setting name stored for that block instance.

### Common setting options

| Key           | Required     | Purpose                                                                   |
|---------------|--------------|---------------------------------------------------------------------------|
| `type`        | No           | Control type; defaults to `text`.                                         |
| `label`       | Yes          | Label displayed in the Settings tab. Entries without a label are ignored. |
| `value`       | No           | Initial/default value.                                                    |
| `placeholder` | No           | Placeholder for controls that support one.                                |
| `options`     | For `select` | List of selectable values.                                                |

The PHP block adapter supports these useful field types:

| Type       | Result                 | Notes                                                                                      |
|------------|------------------------|--------------------------------------------------------------------------------------------|
| `text`     | Single-line text input | Default type.                                                                              |
| `number`   | Numeric input          | Stored as a string when rendered by the block model.                                       |
| `select`   | Select menu            | Requires `options`.                                                                        |
| `checkbox` | Boolean checkbox       | Checked state is represented by the setting attribute; use an empty default for unchecked. |
| `color`    | Color picker           | Produces a CSS color value such as `#2563eb`.                                              |
| `yes_no`   | Vihzhuo Yes/No select  | Normalized to values `0` and `1`.                                                          |

GrapesJS also supports button and custom trait types. A button requires a JavaScript command and therefore should be registered by a GrapesJS plugin rather than serialized in a PHP block configuration. Applications that need additional PHP configuration keys can replace `BlockAdapter` or register a custom trait type during `vihzhuo:grapesjs:before-init`.

### Complete field example

```php
<?php

return [
    'title' => 'Field examples',
    'settings' => [
        'headline' => [
            'type' => 'text',
            'label' => 'Headline',
            'value' => 'Hello world',
            'placeholder' => 'Enter a headline',
        ],
        'columns' => [
            'type' => 'number',
            'label' => 'Columns',
            'value' => '3',
        ],
        'variant' => [
            'type' => 'select',
            'label' => 'Variant',
            'options' => [
                ['value' => 'light', 'label' => 'Light'],
                ['value' => 'dark', 'label' => 'Dark'],
            ],
            'value' => 'light',
        ],
        'featured' => [
            'type' => 'checkbox',
            'label' => 'Featured',
            'value' => '',
        ],
        'show_border' => [
            'type' => 'yes_no',
            'label' => 'Show border',
            'value' => '1',
        ],
        'accent_color' => [
            'type' => 'color',
            'label' => 'Accent color',
            'value' => '#2563eb',
        ],
    ],
];
```

Select options may use the demo-friendly `value`/`label` form shown above or GrapesJS's `id`/`name` form:

```php
'options' => [
    ['id' => 'sm', 'name' => 'Small'],
    ['id' => 'lg', 'name' => 'Large'],
],
```

Changing a setting updates the block attributes, posts the block data to the configured page-builder endpoint, rerenders the PHP view, and reselects the replacement block in the canvas.

Application code may replace the complete settings definition at runtime before rendering the editor:

```php
use Vihzhuo\ThemeBlock;

ThemeBlock::set('callout', 'settings', [
    'heading' => [
        'type' => 'text',
        'label' => 'Heading',
        'value' => 'Application default',
    ],
]);
```

When overriding `settings` dynamically, supply the complete settings array; it replaces the file-based settings definition rather than merging individual fields.

## Nested blocks and shortcodes

Dynamic blocks and layouts may render other blocks using shortcodes.

### Render a child block

```php
<section class="alert">
    [block slug="alert-text" id="message"]
</section>
```

Use a stable, unique `id` for repeated child instances:

```php
<?php for ($index = 0; $index < 3; $index++): ?>
    [block slug="card" id="card-<?= $index ?>"]
<?php endfor; ?>
```

Additional shortcode attributes become setting values for that block render:

```html
[block slug="badge" id="new-badge" tone="success" label="New"]
```

Blocks intended only as nested implementation details can set `'hidden' => true` in `config.php`.

### Add a nested drop zone

Use `[blocks-container]` where editors should be able to drop arbitrary child blocks inside a dynamic block:

```php
<section class="columns">
    <div class="column">[blocks-container]</div>
    <div class="column">[blocks-container]</div>
</section>
```

It is converted to `<div phpb-blocks-container></div>` and receives the appropriate GrapesJS drop behavior.

### Other shortcodes

| Shortcode                            | Result                                  |
|--------------------------------------|-----------------------------------------|
| `[block slug="hero" id="home-hero"]` | Renders a theme or extension block.     |
| `[blocks-container]`                 | Creates a nested block drop zone.       |
| `[theme-url]`                        | Active theme's public URL.              |
| `[page id="12"]`                     | Route of page ID `12` on public output. |

Shortcode nesting is limited to 25 levels to catch accidental circular block references.

## Custom block models and controllers

Dynamic blocks use `BaseModel` and `BaseController` by default. Add `model.php` or `controller.php` only when the block needs application logic.

Set an explicit namespace in the block's `config.php`:

```php
return [
    'title' => 'Latest posts',
    'namespace' => 'App\\PageBuilder\\Blocks\\LatestPosts',
];
```

`model.php` must contain a `Model` class extending `BaseModel`:

```php
<?php

declare(strict_types=1);

namespace App\PageBuilder\Blocks\LatestPosts;

use Vihzhuo\Modules\GrapesJS\Block\BaseModel;

final class Model extends BaseModel
{
    /** @var list<array{title: string, url: string}> */
    private array $posts = [];

    protected function init(): void
    {
        $this->posts = [
            ['title' => 'First post', 'url' => '/blog/first-post'],
        ];
    }

    // Optional editor-only initialization for a fast or deterministic preview.
    protected function initEdit(): void
    {
        $this->posts = [
            ['title' => 'Example post', 'url' => '#'],
        ];
    }

    /** @return list<array{title: string, url: string}> */
    public function posts(): array
    {
        return $this->posts;
    }
}
```

The view can call the custom method:

```php
<ul class="latest-posts">
    <?php foreach ($block->posts() as $post): ?>
        <li>
            <a href="<?= phpb_e($post['url']) ?>">
                <?= phpb_e($post['title']) ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
```

A model may set the inherited `$doNotRender`, `$hasSkeleton`, or `$hasDynamicSkeleton` properties during initialization when the corresponding behavior is required.

`controller.php` must contain a `Controller` class extending `BaseController`:

```php
<?php

declare(strict_types=1);

namespace App\PageBuilder\Blocks\LatestPosts;

use Vihzhuo\Modules\GrapesJS\Block\BaseController;

final class Controller extends BaseController
{
    public function handleRequest(): void
    {
        // Perform block-specific request coordination here.
        // The initialized model, page, and editor flag are protected properties.
    }
}
```

Custom models and controllers are trusted server-side code. Vihzhuo validates that they extend the corresponding base class before using them.

## Block JavaScript

A block may provide one of these public script files:

```text
script.js
script.html
script.php
```

It may also provide an editor-specific version:

```text
builder-script.js
builder-script.html
builder-script.php
```

If there is no builder script, Vihzhuo uses the public script in the editor. PHP script views receive `$renderer`.

Block scripts run with instance-specific variables:

```js
// The block's root DOM element.
block.classList.add('is-initialized');

// A unique selector for this block instance.
document.querySelector(blockSelector)?.setAttribute('data-ready', 'true');

// true in GrapesJS, false on public output.
if (inPageBuilder) {
  block.classList.add('is-preview');
}
```

The editor executes builder scripts as components are mounted or updated. On public output, the renderer scopes each script behind a `run-script` event. If the host theme does not already provide a block-script bootstrap, add one near the end of the layout:

```html
<script>
document.addEventListener('DOMContentLoaded', () => {
  document
    .querySelectorAll('script[type="text/javascript"][class^="script"]')
    .forEach(script => script.dispatchEvent(new Event('run-script')));
});
</script>
```

Prefer layout assets for shared libraries. Use block scripts only for behavior tied to a particular rendered block instance.

## Adding CSS and JavaScript assets

### Theme assets

Put normal frontend assets inside the active theme and reference them from the layout:

```php
<link rel="stylesheet" href="<?= phpb_theme_asset('css/theme.css') ?>">
<script src="<?= phpb_theme_asset('js/theme.js') ?>" defer></script>
```

These assets are part of both the public layout and the GrapesJS canvas preview.

### Assets supplied by application code or a package

Register trusted asset URLs before handling the request:

```php
use Vihzhuo\Extensions;

Extensions::registerAsset(
    '/build/page-blocks.css',
    'style',
    'header',
    ['media' => 'screen']
);

Extensions::registerAsset(
    '/build/page-blocks.js',
    'script',
    'footer',
    ['defer' => 'defer']
);
```

Render the registrations in the theme layout:

```php
<head>
    <?php phpb_registered_assets('header'); ?>
</head>
<body>
    <?= $body ?>
    <?php phpb_registered_assets('footer'); ?>
</body>
```

Registered URLs and attributes are trusted developer configuration; do not construct them from user input.

### Page-builder UI assets and hooks

`PageBuilder::customStyle()` and `customScripts()` customize the editor shell rather than the public page:

```php
$pageBuilder = $vihzhuo->getPageBuilder();

$pageBuilder?->customStyle(<<<'HTML'
<style>
  .gjs-pn-panels { --brand-accent: #7c3aed; }
</style>
HTML);

$pageBuilder?->customScripts('head', <<<'HTML'
<script>
  window.addEventListener('vihzhuo:grapesjs:ready', event => {
    console.log('Editor ready', event.detail);
  });
</script>
HTML);
```

Call these methods before rendering or handling a page-builder request.

## Registering blocks and layouts from application code

Composer packages and application modules can provide blocks and layouts without copying them into the active theme:

```php
use Vihzhuo\Extensions;

Extensions::registerBlock(
    'pricing-table',
    __DIR__ . '/PageBuilder/Blocks/PricingTable'
);

Extensions::registerLayout(
    'campaign',
    __DIR__ . '/PageBuilder/Layouts/Campaign'
);
```

Register several at once:

```php
Extensions::addBlocks([
    'pricing-table' => __DIR__ . '/Blocks/PricingTable',
    'testimonial' => __DIR__ . '/Blocks/Testimonial',
]);

Extensions::addLayouts([
    'campaign' => __DIR__ . '/Layouts/Campaign',
]);
```

An extension block/layout folder follows the same file conventions as a theme folder. Give custom PHP block classes an explicit `namespace` in their block configuration.

Registrations must happen before the theme is enumerated—normally before calling `handleRequest()`.

## Customizing GrapesJS

The default editor uses GrapesJS 0.23, `grapesjs-touch`, and Vihzhuo's built-in RTE. The RTE includes formatting, links, font color, font size, and HTML source editing. The CKEditor plugin remains bundled for opt-in compatibility but is not active by default.

### Static configuration overrides

Define `window.customConfig` before Vihzhuo initializes the editor:

```html
<script>
window.customConfig = {
  deviceManager: {
    devices: [
      { name: 'Desktop', width: '' },
      { name: 'Tablet', width: '900px' },
      { name: 'Phone', width: '390px' }
    ]
  },
  canvas: {
    styles: ['/build/canvas-preview.css'],
    scripts: ['/build/canvas-preview.js']
  }
};
</script>
```

The custom configuration is deeply merged over the server defaults.

### Before and after initialization hooks

Use `vihzhuo:grapesjs:before-init` to mutate final configuration and register plugin functions:

```html
<script>
window.addEventListener('vihzhuo:grapesjs:before-init', event => {
  const config = event.detail;
  config.storageManager = false;
  config.plugins = config.plugins || [];
  config.plugins.push(editor => {
    editor.Commands.add('application:hello', {
      run() {
        window.alert('Hello from the application plugin');
      }
    });
  });
});
</script>
```

Use `vihzhuo:grapesjs:ready` when the initialized editor API is required:

```html
<script>
window.addEventListener('vihzhuo:grapesjs:ready', event => {
  const editor = event.detail;

  editor.BlockManager.add('application-notice', {
    label: 'Application notice',
    category: 'Application',
    content: '<aside class="notice"><p>Edit this notice</p></aside>'
  });

  editor.on('component:add', component => {
    console.debug('Added component', component.getId());
  });
});
</script>
```

Install these listeners through `PageBuilder::customScripts('head', ...)` or another script loaded before the inline page-builder initialization code.

### Custom traits

Register a GrapesJS trait type in a plugin:

```js
window.addEventListener('vihzhuo:grapesjs:before-init', event => {
  event.detail.plugins.push(editor => {
    editor.TraitManager.addType('readonly-note', {
      noLabel: true,
      createInput({ trait }) {
        const note = document.createElement('p');
        note.className = 'trait-note';
        note.textContent = trait.get('text') || '';
        return note;
      }
    });
  });
});
```

To expose extra trait configuration from PHP `config.php`, extend and replace `Vihzhuo\Modules\GrapesJS\Block\BlockAdapter`.

## Routing and multilingual pages

The bundled `DatabasePageRouter` resolves routes stored in `page_translations`.

Supported route forms include:

```text
/about
/blog/{slug}
/documentation/*
```

Exact routes are checked before named parameters, and named parameters are checked before wildcards. Read named values in PHP with:

```php
$slug = phpb_route_parameter('slug');
```

Or retrieve all values:

```php
$parameters = phpb_route_parameters();
```

Active languages are read from the `languages` website setting. If it is absent, `general.language` is used. The Website Manager lets administrators enable languages and stores title, metadata, and route translations for each page.

Useful helpers include:

```php
phpb_current_language();
phpb_active_languages();
phpb_current_relative_url();
phpb_full_url('/contact');
phpb_url('pagebuilder', ['page' => $page->getId()]);
phpb_pages();
```

Custom routing is supported by implementing `RouterContract` and configuring `router.class`, or by resolving a `PageContract` in the host framework and calling the page builder/renderer directly.

## Uploads and the Asset Manager

The bundled Asset Manager accepts PNG, JPEG, GIF, and WebP images. Upload handling:

- reads a PSR-7 `UploadedFileInterface`;
- verifies the detected MIME type with fileinfo;
- creates a random public identifier;
- stores metadata in the `uploads` table;
- stores files below `storage.uploads_folder`;
- serves files through `general.uploads_url` with path-containment checks.

Make sure the uploads route reaches `handlePublicRequest()` or the complete `handleRequest()` handler.

To add a theme image that is not user-uploaded, place it in the theme and use `phpb_theme_asset()` instead of the Asset Manager.

## Caching

Enable file-backed rendered-page caching with:

```php
'cache' => [
    'enabled' => true,
    'folder' => dirname(__DIR__) . '/storage/page-cache',
    'class' => Vihzhuo\Cache::class,
],
```

The default maximum lifetime is one week. A dynamic block can shorten it:

```php
return [
    'cache' => true,
    'cache_lifetime' => 15, // minutes
];
```

Disable caching for pages containing request-specific content:

```php
return [
    'cache' => false,
];
```

Saving or updating a page invalidates its cached route variants. During development, `?ignore_cache` bypasses reads and writes, while `?refresh_cache` bypasses the current cached response so it can be regenerated.

## Security notes

- Protect the manager and page-builder routes with authentication and authorization.
- Replace the example password and keep credentials outside version control.
- Point `theme.folder`, `storage.uploads_folder`, and `cache.folder` at intentional, application-owned directories.
- Use `phpb_e()` for HTML text and attributes.
- Use `phpb_json()` when embedding PHP data in a `<script>` element.
- `BaseModel::setting()` escapes by default; only allow raw HTML after application-level sanitization.
- Treat theme templates, registered assets, block scripts, configuration files, and GrapesJS plugins as trusted developer code.
- Validate custom block model data before rendering it.
- Custom record/repository implementations must honor the typed contracts and their security boundaries.

## Development and quality checks

Rebuild authored JavaScript and Sass:

```bash
npm run dev
npm run watch
npm run production
```

Run PHP quality checks:

```bash
composer analyse   # PHPStan level max, no baseline
composer test      # PHPUnit regression suite
composer check     # Both commands
```

The browser integration smoke test is [`tests/Browser/grapesjs-smoke.html`](tests/Browser/grapesjs-smoke.html). Serve the repository through HTTP before opening it so its relative assets load correctly.

Runtime templates are syntax-checked separately because their variables are injected at the view boundary. The typed application, front-controller example, and PHP tests are covered by PHPStan level `max`.

For migration details, see [`UPGRADE.md`](UPGRADE.md).

## License

Vihzhuo is licensed under the MIT License.
