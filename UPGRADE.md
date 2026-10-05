# Upgrade guide

## Child theme update

This update keeps existing theme APIs, `ThemeContract` implementations, extension priority, and saved block/layout slugs. Standalone themes need no migration. To enable inheritance, add `theme.parents` as a child => parent map and set `theme.active_theme` to the child. See [Child themes](README.md#child-themes) for partial overrides, complete replacements, asset fallback, and PHP namespaces.

Theme identifiers and file paths now reject traversal and symlinks escaping their configured directories. Keep all themes in an inheritance chain inside `theme.folder`. Clear page caches when switching themes.

For CMS integrations, a theme adapter can override `getParentThemeSlug()` to use existing parent metadata and `inheritsResourceFiles()` to choose whole-directory replacement. This requires no extra CMS configuration keys. Child thumbnails now use the active child's public storage and include inherited resource files in their fingerprint. Standalone theme APIs, defaults, and thumbnail conventions are preserved. Full-page cache invalidation remains the host application's responsibility.

## Earlier runtime modernization

The earlier runtime modernization intentionally contains breaking changes; the instructions below apply to that migration.

## Runtime and dependencies

- PHP 8.4 is the minimum version and all application PHP files use strict types.
- Install `qubus/http:^4.2`; requests and responses use the PSR-7 interfaces.
- Development checks now use PHPStan 2 at level `max` and PHPUnit 12.
- Browser assets are built with esbuild and Dart Sass. The obsolete webpack-mix build has been removed.

Run:

```bash
composer update
npm install
npm run production
```

## Request handling

`Vihzhuo::handleRequest()` now requires a `ServerRequestInterface` and returns a `ResponseInterface`. It no longer writes headers, streams files, or terminates the process itself. The host application must emit or return the response.

The same applies to `AuthContract`, `PageBuilderContract`, and `WebsiteManagerContract`. Custom implementations must update their method signatures. Request input is read from PSR-7 query parameters, parsed bodies, cookies, and uploaded files rather than PHP request superglobals.

Redirect helpers return a response:

```php
return phpb_redirect('/manager', ['message' => 'Saved']);
```

## Typed records and repositories

- Repository hydration now requires records to implement `PageContract` or `DataRecordContract`.
- `BaseRepository` is generic and returns hydrated record objects with explicit types.
- Custom repositories should use the typed `createRecord()` and `updateRecord()` protected methods; the legacy untyped protected helpers were removed.
- `PageRepository::create()` returns the created `PageContract` on success instead of a boolean.
- Page, translation, setting, upload, theme, block, renderer, router, cache, and service contracts now have native parameter and return types.
- Dynamic configured classes are validated before construction.

The legacy `Modules/GrapesJS/Upload/Uploader` and `ResizeImage` classes were removed. Upload handling now accepts a PSR-7 `UploadedFileInterface`, verifies the MIME type with fileinfo, uses `moveTo()`, and constrains storage paths.

## GrapesJS and assets

- GrapesJS was upgraded from 0.15.9 to 0.23.4.
- `grapesjs-plugin-ckeditor` was upgraded from 0.0.10 to 1.0.1 and is bundled with `grapesjs-touch`.
- Replace references to `grapesjs-v0.15.9.min.{js,css}` with `grapesjs-v0.23.4.min.{js,css}`.
- Remove separate script tags for the CKEditor and touch plugins; they are registered by the main GrapesJS bundle.
- The old `gjs-plugin-ckeditor` alias remains accepted, but new configuration should use `grapesjs-plugin-ckeditor`.

`window.customConfig` is now merged after the server defaults, so application values correctly take precedence. Use `vihzhuo:grapesjs:before-init` to mutate final configuration and `vihzhuo:grapesjs:ready` for editor extensions that require an initialized editor.

## Templates and security boundaries

- Views are captured through `Vihzhuo\Core\View` and returned as response bodies.
- Use `phpb_json()` for data embedded in script elements and `phpb_e()` for HTML text/attributes.
- Use `Vihzhuo\Core\HttpContext` if a custom template needs access to the active PSR-7 request.
- Asset, upload, cache, theme, and layout paths now enforce containment and normalized identifiers.

Review custom blocks carefully under strict types. In particular, block `config.php` files must return arrays and custom model/controller classes must extend the corresponding Vihzhuo base classes.
