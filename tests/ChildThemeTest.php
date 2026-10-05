<?php

declare(strict_types=1);

namespace Vihzhuo\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionProperty;
use RuntimeException;
use SplFileInfo;
use Vihzhuo\Contracts\ThemeContract;
use Vihzhuo\Core\ThemeContext;
use Vihzhuo\Extensions;
use Vihzhuo\Modules\GrapesJS\Block\BlockRenderer;
use Vihzhuo\Modules\GrapesJS\PageRenderer;
use Vihzhuo\Modules\GrapesJS\ShortcodeParser;
use Vihzhuo\Page;
use Vihzhuo\Theme;
use Vihzhuo\ThemeBlock;
use Vihzhuo\ThemeLayout;
use Vihzhuo\Vihzhuo;

final class ChildThemeTest extends TestCase
{
    private string $root;

    /** @var array<string, mixed> */
    private array $config;

    /** @var array<string, mixed> */
    private array $savedGlobals = [];

    /** @var array<string, mixed> */
    private array $savedExtensions = [];

    protected function setUp(): void
    {
        foreach (['phpb_config', 'phpb_translations'] as $name) {
            $this->savedGlobals[$name] = $GLOBALS[$name] ?? null;
        }
        foreach (['blocks', 'layouts'] as $name) {
            $property = new ReflectionProperty(Extensions::class, $name);
            $this->savedExtensions[$name] = $property->getValue();
            $property->setValue(null, []);
        }
        $this->root = sys_get_temp_dir() . '/vihzhuo-themes-' . bin2hex(random_bytes(8));
        foreach (['parent', 'child', 'grandparent'] as $slug) {
            mkdir($this->root . '/' . $slug, 0700, true);
        }
        $this->config = [
            'folder' => $this->root,
            'folder_url' => '/themes',
            'active_theme' => 'child',
            'parents' => ['child' => 'parent'],
        ];
        $GLOBALS['phpb_config'] = [
            'general' => ['base_url' => 'https://example.test', 'language' => 'en'],
            'theme' => $this->config,
            'class_replacements' => [ShortcodeParser::class => ChildThemeShortcodeParser::class],
        ];
        $GLOBALS['phpb_translations'] = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->savedGlobals as $name => $value) {
            $GLOBALS[$name] = $value;
        }
        foreach ($this->savedExtensions as $name => $value) {
            new ReflectionProperty(Extensions::class, $name)->setValue(null, $value);
        }
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($entries as $entry) {
            if (!$entry instanceof SplFileInfo) {
                continue;
            }
            if ($entry->isLink() || !$entry->isDir()) {
                unlink($entry->getPathname());
            } else {
                rmdir($entry->getPathname());
            }
        }
        rmdir($this->root);
    }

    private function put(string $path, string $contents): void
    {
        $file = $this->root . '/' . $path;
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0700, true);
        }
        file_put_contents($file, $contents);
    }

    /** @param array<string, mixed> $config */
    private function putConfig(string $path, array $config): void
    {
        $this->put($path . '/config.php', '<?php return ' . var_export($config, true) . ';');
    }

    private function theme(): Theme
    {
        return new Theme($this->config, 'child');
    }

    private function page(): Page
    {
        $page = new Page();
        $page->setData(['layout' => 'main', 'data' => []]);
        return $page;
    }

    public function testEnumerationCombinesThemesAndLoadsEachEffectiveConfigOnce(): void
    {
        $this->put('parent/blocks/hero/view.html', 'Parent');
        $this->put('child/blocks/elements/hero/view.html', 'Child');
        $this->put('parent/blocks/php/card/view.php', '<?php echo "Card";');
        $this->put('child/blocks/new/view.html', 'New');
        $this->put('parent/layouts/main/view.php', 'Parent layout');
        $this->put('child/layouts/main/view.php', 'Child layout');
        $this->put('parent/layouts/other/view.php', 'Other');
        $GLOBALS['child_theme_config_loads'] = 0;
        $this->put('child/blocks/elements/hero/config.php', '<?php ++$GLOBALS["child_theme_config_loads"]; return [];');
        $theme = $this->theme();
        $blocks = $theme->getThemeBlocks();
        self::assertCount(3, $blocks);
        self::assertSame($this->root . '/child/blocks/elements/hero/view.html', $blocks['hero']->getViewFile());
        self::assertSame($this->root . '/parent/blocks/php/card/view.php', $blocks['card']->getViewFile());
        self::assertSame(1, $GLOBALS['child_theme_config_loads']);
        unset($GLOBALS['child_theme_config_loads']);
        self::assertCount(2, $theme->getThemeLayouts());
    }

    public function testPartialOverrideInheritsAndMergesConfigurationAndFiles(): void
    {
        $this->put('parent/blocks/hero/view.php', 'Parent view');
        $this->put('child/blocks/hero/view.php', 'Child view');
        $this->put('parent/blocks/hero/model.php', '<?php');
        $this->put('parent/blocks/hero/controller.php', '<?php');
        $this->put('parent/blocks/hero/script.php', 'Parent script');
        $this->put('parent/blocks/hero/builder-script.js', 'Builder script');
        $this->putConfig('parent/blocks/hero', [
            'namespace' => 'ParentHero', 'title' => 'Parent',
            'settings' => ['a' => 1, 'b' => 2], 'whitelist' => ['parent.test'], 'nullable' => 'value',
        ]);
        $this->putConfig('child/blocks/hero', [
            'namespace' => 'ChildHero', 'settings' => ['a' => 3], 'whitelist' => [], 'nullable' => null,
        ]);
        $block = new ThemeBlock($this->theme(), 'hero');
        self::assertSame('Parent', $block->get('title'));
        self::assertSame(['a' => 3, 'b' => 2], $block->get('settings'));
        self::assertSame([], $block->get('whitelist'));
        self::assertNull($block->get('nullable'));
        self::assertSame('ParentHero\\Model', $block->getModelClass());
        self::assertSame('ParentHero\\Controller', $block->getControllerClass());
        self::assertSame($this->root . '/parent/blocks/hero/script.php', $block->getScriptFile());
        self::assertSame($this->root . '/parent/blocks/hero/builder-script.js', $block->getBuilderScriptFile());
    }

    public function testPhpModelsAndControllersCanComeFromDifferentLayers(): void
    {
        $this->putConfig('parent/blocks/dynamic', ['namespace' => 'Vihzhuo\\Tests\\ParentDynamic']);
        $this->put('parent/blocks/dynamic/model.php', '<?php namespace Vihzhuo\\Tests\\ParentDynamic; class Model extends \\Vihzhuo\\Modules\\GrapesJS\\Block\\BaseModel {}');
        $this->put('parent/blocks/dynamic/controller.php', '<?php namespace Vihzhuo\\Tests\\ParentDynamic; class Controller extends \\Vihzhuo\\Modules\\GrapesJS\\Block\\BaseController {}');
        $this->put('parent/blocks/dynamic/view.php', '<?php echo "Parent";');
        $this->putConfig('child/blocks/dynamic', ['namespace' => 'Vihzhuo\\Tests\\ChildDynamic']);
        $this->put('child/blocks/dynamic/controller.php', '<?php namespace Vihzhuo\\Tests\\ChildDynamic; class Controller extends \\Vihzhuo\\Modules\\GrapesJS\\Block\\BaseController {}');
        $this->put('child/blocks/dynamic/view.php', '<?php echo "Child";');
        $html = new BlockRenderer($this->theme(), $this->page())->renderWithSlug('dynamic');
        self::assertSame('Child', $html);
    }

    public function testNearestLayerWinsAcrossFileTypes(): void
    {
        $this->put('parent/blocks/hero/view.php', 'PHP');
        $this->put('parent/blocks/hero/script.php', 'PHP script');
        $this->put('child/blocks/hero/view.html', 'HTML');
        $this->put('child/blocks/hero/script.js', 'JS script');
        $block = new ThemeBlock($this->theme(), 'hero');
        self::assertTrue($block->isHtmlBlock());
        self::assertSame($this->root . '/child/blocks/hero/script.js', $block->getScriptFile());
        self::assertSame($block->getScriptFile(), $block->getBuilderScriptFile());
    }

    public function testReplacementStopsFileAndConfigurationInheritance(): void
    {
        $this->put('parent/blocks/hero/view.php', 'Parent');
        $this->put('parent/blocks/hero/model.php', '<?php');
        $this->put('parent/blocks/hero/script.js', 'Parent script');
        $this->put('parent/blocks/hero/config.php', '<?php throw new \\RuntimeException("Must not execute");');
        $this->put('child/blocks/hero/view.html', 'Replacement');
        $this->putConfig('child/blocks/hero', ['inherit' => false, 'title' => 'Replacement']);
        $block = new ThemeBlock($this->theme(), 'hero');
        self::assertTrue($block->isHtmlBlock());
        self::assertNull($block->getModelFile());
        self::assertNull($block->getScriptFile());
        self::assertSame('Replacement', $block->get('title'));
        self::assertSame('Replacement', new BlockRenderer($this->theme(), $this->page())->renderWithSlug('hero'));
    }

    public function testLayoutsCanOverrideConfigWithoutCopyingTheView(): void
    {
        $this->put('parent/layouts/main/view.php', 'Parent layout');
        $this->putConfig('parent/layouts/main', ['title' => 'Parent', 'options' => ['one' => true, 'two' => true]]);
        $this->putConfig('child/layouts/main', ['title' => 'Child', 'options' => ['one' => false]]);
        $layout = new ThemeLayout($this->theme(), 'main');
        self::assertSame('Child', $layout->getTitle());
        self::assertFalse($layout->get('options.one'));
        self::assertTrue($layout->get('options.two'));
        self::assertSame($this->root . '/parent/layouts/main/view.php', $layout->getViewFile());
        self::assertSame($layout->getViewFile(), new PageRenderer($this->theme(), $this->page())->getPageLayoutPath());
    }

    public function testReplacementLayoutDoesNotFallBackToParentView(): void
    {
        $this->put('parent/layouts/main/view.php', 'Parent layout');
        $this->putConfig('child/layouts/main', ['inherit' => false]);
        self::assertNull(new PageRenderer($this->theme(), $this->page())->getPageLayoutPath());
    }

    public function testMultipleAncestorsUseNearestOverride(): void
    {
        $this->config['parents'] = ['child' => 'parent', 'parent' => 'grandparent'];
        $this->put('grandparent/blocks/hero/view.php', 'Old view');
        $this->put('parent/blocks/hero/view.html', 'Parent view');
        $this->put('grandparent/blocks/hero/script.js', 'Old script');
        $this->putConfig('grandparent/blocks/hero', ['title' => 'Old', 'value' => 'Old']);
        $this->putConfig('parent/blocks/hero', ['title' => 'Parent']);
        $this->putConfig('child/blocks/hero', ['value' => 'Child']);
        $block = new ThemeBlock($this->theme(), 'hero');
        self::assertSame(['child', 'parent', 'grandparent'], array_keys($this->theme()->getThemeFolders()));
        self::assertSame('Parent', $block->get('title'));
        self::assertSame('Child', $block->get('value'));
        self::assertTrue($block->isHtmlBlock());
        self::assertSame($this->root . '/grandparent/blocks/hero/script.js', $block->getScriptFile());
    }

    public function testAssetsFallBackPerFileAndRetainQueryAndFragment(): void
    {
        $this->put('parent/css/site.css', 'Parent CSS');
        $this->put('parent/images/logo.svg', 'Parent logo');
        $this->put('child/images/logo.svg', 'Child logo');
        self::assertSame('https://example.test/themes/parent/css/site.css?v=2#top', phpb_theme_asset('css/site.css?v=2#top'));
        self::assertSame('https://example.test/themes/child/images/logo.svg', phpb_theme_asset('images/logo.svg'));
        self::assertSame('https://example.test/themes/child/missing.css', phpb_theme_asset('missing.css'));
        self::assertSame($this->root . '/parent/css/site.css', $this->theme()->findFile('css/site.css'));
    }

    public function testLegacyThemeUrlShortcodesResolveAssetsIndividually(): void
    {
        $this->put('parent/css/site.css', 'Parent CSS');
        $this->put('child/images/logo.svg', 'Child logo');
        $renderer = new PageRenderer($this->theme(), $this->page());
        $parser = new ChildThemeShortcodeParser($renderer);
        self::assertSame(
            '<link href="/themes/parent/css/site.css?v=1&amp;x=2"><img src="/themes/child/images/logo.svg">/themes/child',
            $parser->doShortcodes('<link href="[theme-url]/css/site.css?v=1&amp;x=2"><img src="[theme-url]/images/logo.svg">[theme-url]')
        );
    }

    public function testTranslationsMergeParentFirstWithLocaleFallback(): void
    {
        $this->put('parent/translations/en.php', '<?php return ["inherited" => "Parent", "shared" => "Parent", "localized" => "English"];');
        $this->put('child/translations/en.php', '<?php return ["shared" => "Child"];');
        $this->put('parent/translations/fr.php', '<?php return ["localized" => "French", "shared" => "Parent French"];');
        $this->put('child/translations/fr.php', '<?php return ["shared" => "Child French"];');
        $application = new Vihzhuo();
        $application->setTheme($this->theme());
        $translations = $application->loadTranslations('fr');
        self::assertSame('Parent', $translations['inherited']);
        self::assertSame('French', $translations['localized']);
        self::assertSame('Child French', $translations['shared']);
    }

    public function testExtensionsKeepTheirExistingPrecedence(): void
    {
        $this->put('parent/blocks/hero/view.html', 'Parent');
        $this->put('child/blocks/hero/view.html', 'Child');
        $this->put('parent/layouts/main/view.php', 'Parent layout');
        $this->put('child/layouts/main/view.php', 'Child layout');
        $this->put('extension/block/view.html', 'Extension');
        $this->put('extension/layout/view.php', 'Extension layout');
        Extensions::registerBlock('hero', $this->root . '/extension/block');
        Extensions::registerLayout('main', $this->root . '/extension/layout');
        self::assertSame($this->root . '/extension/block', $this->theme()->getThemeBlocks()['hero']->getFolder());
        self::assertSame('Extension', new BlockRenderer($this->theme(), $this->page())->renderWithSlug('hero'));
        self::assertSame($this->root . '/extension/layout/view.php', new PageRenderer($this->theme(), $this->page())->getPageLayoutPath());
    }

    public function testStandaloneThemeAndOriginalContractStillWork(): void
    {
        $this->put('parent/blocks/hero/view.html', 'Standalone');
        $this->put('parent/layouts/main/view.php', 'Standalone layout');
        $theme = new Theme(['folder' => $this->root], 'parent');
        self::assertSame($this->root . '/parent/blocks/hero/view.html', $theme->getThemeBlocks()['hero']->getViewFile());
        $custom = new class ($this->root . '/parent') implements ThemeContract {
            public function __construct(private string $folder) {}
            public function getFolder(): string { return $this->folder; }
            public function getThemeBlocks(): array { return []; }
            public function getThemeLayouts(): array { return []; }
        };
        self::assertSame($this->root . '/parent/blocks/hero/view.html', new ThemeBlock($custom, 'hero')->getViewFile());
        self::assertSame($this->root . '/parent/layouts/main/view.php', new ThemeLayout($custom, 'main')->getViewFile());
    }

    public function testCustomResourceFoldersAndConfigScopeRemainCompatible(): void
    {
        $this->put('custom/block/view.html', 'Custom');
        $this->put('custom/block/config.php', '<?php return ["title" => $this instanceof \Vihzhuo\ThemeBlock ? "Block scope" : "Wrong scope"];');
        $this->put('custom/layout/view.php', 'Custom layout');
        $this->put('custom/layout/config.php', '<?php return ["title" => $this instanceof \Vihzhuo\ThemeLayout ? "Layout scope" : "Wrong scope"];');
        $block = new class ($this->theme(), 'custom') extends ThemeBlock {
            public function getFolder(): string { return dirname($this->theme->getFolder()) . '/custom/block'; }
        };
        $layout = new class ($this->theme(), 'custom') extends ThemeLayout {
            public function getFolder(): string { return dirname($this->theme->getFolder()) . '/custom/layout'; }
        };
        self::assertSame('Block scope', $block->get('title'));
        self::assertSame($this->root . '/custom/block/view.html', $block->getViewFile());
        self::assertSame('Layout scope', $layout->getTitle());
        self::assertSame($this->root . '/custom/layout/view.php', $layout->getViewFile());
    }

    public function testIntermediateReplacementIsABoundaryForItsDescendants(): void
    {
        $this->config['parents'] = ['child' => 'parent', 'parent' => 'grandparent'];
        $this->put('grandparent/blocks/hero/config.php', '<?php throw new \RuntimeException("Must not execute");');
        $this->put('grandparent/blocks/hero/script.js', 'Excluded script');
        $this->putConfig('parent/blocks/hero', ['inherit' => false, 'title' => 'Parent replacement']);
        $this->put('parent/blocks/hero/view.html', 'Parent replacement');
        $this->putConfig('child/blocks/hero', ['title' => 'Child']);
        $block = new ThemeBlock($this->theme(), 'hero');
        self::assertSame('Child', $block->get('title'));
        self::assertNull($block->getScriptFile());
        self::assertSame($this->root . '/parent/blocks/hero/view.html', $block->getViewFile());
    }

    public function testBundledChildThemeInheritsTheDemoAndOverridesItsGreeting(): void
    {
        $config = [
            'folder' => dirname(__DIR__) . '/examples/themes',
            'folder_url' => '/themes',
            'active_theme' => 'demo-child',
            'parents' => ['demo-child' => 'demo'],
        ];
        $globalConfig = $GLOBALS['phpb_config'];
        self::assertIsArray($globalConfig);
        $globalConfig['theme'] = $config;
        $GLOBALS['phpb_config'] = $globalConfig;
        $theme = new Theme($config, 'demo-child');
        $blocks = $theme->getThemeBlocks();
        self::assertArrayHasKey('notice', $blocks);
        self::assertSame('Child theme greeting', $blocks['hello-world']->get('title'));
        self::assertSame('Demo', $blocks['hello-world']->get('category'));
        self::assertStringContainsString('Hello from the child theme', new BlockRenderer($theme, $this->page())->renderWithSlug('hello-world'));
        self::assertSame(dirname(__DIR__) . '/examples/themes/demo/layouts/main/view.php', new PageRenderer($theme, $this->page())->getPageLayoutPath());
        self::assertSame('https://example.test/themes/demo/css/style.css', phpb_theme_asset('css/style.css'));
    }

    public function testPhpTemplatesUseTheSuppliedThemeAndRestoreContextOnErrors(): void
    {
        $this->put('parent/css/site.css', 'Parent CSS');
        $this->put('child/css/site.css', 'Child CSS');
        $this->put('parent/layouts/main/view.php', '<?php echo phpb_theme_asset("css/site.css");');
        $this->put('parent/blocks/asset/view.php', '<?php echo phpb_theme_asset("css/site.css");');
        $this->put('parent/blocks/asset/config.php', '<?php return ["asset" => phpb_theme_asset("css/site.css")];');
        $globalConfig = $GLOBALS['phpb_config'];
        self::assertIsArray($globalConfig);
        $globalConfig['theme'] = ['folder' => $this->root, 'folder_url' => '/themes', 'active_theme' => 'parent'];
        $GLOBALS['phpb_config'] = $globalConfig;
        $theme = $this->theme();
        self::assertSame('https://example.test/themes/child/css/site.css', new ThemeBlock($theme, 'asset')->get('asset'));
        self::assertSame('https://example.test/themes/child/css/site.css', new PageRenderer($theme, $this->page())->render());
        self::assertSame('https://example.test/themes/child/css/site.css', new BlockRenderer($theme, $this->page())->renderWithSlug('asset'));
        self::assertSame('https://example.test/themes/parent/css/site.css', phpb_theme_asset('css/site.css'));
        self::assertNull(ThemeContext::current());
        $this->put('parent/blocks/broken/view.php', '<?php throw new \RuntimeException("Template failed");');
        try {
            new BlockRenderer($theme, $this->page())->renderWithSlug('broken');
            self::fail('Expected template exception.');
        } catch (RuntimeException $exception) {
            self::assertSame('Template failed', $exception->getMessage());
        }
        self::assertNull(ThemeContext::current());
        self::assertSame('https://example.test/themes/parent/css/site.css', phpb_theme_asset('css/site.css'));
    }

    /** An adapter can use CMS metadata without a parents setting in the app config. */
    private function metadataTheme(): Theme
    {
        $config = $this->config;
        unset($config['parents']);
        return new class ($config, 'child') extends Theme {
            protected function getParentThemeSlug(string $themeSlug): ?string
            {
                return match ($themeSlug) {
                    'child' => 'parent',
                    'parent' => 'grandparent',
                    default => null,
                };
            }

            public function inheritsResourceFiles(): bool
            {
                return false;
            }
        };
    }

    public function testCmsMetadataAdapterNeedsNoParentMapOrResourceReplacementConfig(): void
    {
        $this->put('grandparent/blocks/notice/view.html', 'Inherited notice');
        $this->put('parent/blocks/navbar/config.php', '<?php throw new \RuntimeException("Parent config must not execute");');
        $this->put('parent/blocks/navbar/controller.php', '<?php');
        $this->put('parent/blocks/navbar/view.php', 'Parent navigation');
        $this->put('parent/blocks/navbar/script.js', 'Parent script');
        $this->put('child/blocks/navbar/view.html', 'Child navigation');
        $this->put('parent/layouts/main/view.php', 'Inherited main layout');
        $this->put('grandparent/css/site.css', 'Inherited CSS');
        $theme = $this->metadataTheme();
        self::assertSame(['child', 'parent', 'grandparent'], array_keys($theme->getThemeFolders()));
        $blocks = $theme->getThemeBlocks();
        self::assertCount(2, $blocks);
        self::assertSame([], $blocks['navbar']->get());
        self::assertNull($blocks['navbar']->getControllerFile());
        self::assertNull($blocks['navbar']->getScriptFile());
        self::assertSame('Child navigation', new BlockRenderer($theme, $this->page())->renderWithSlug('navbar'));
        self::assertSame('Inherited notice', new BlockRenderer($theme, $this->page())->renderWithSlug('notice'));
        self::assertSame($this->root . '/parent/layouts/main/view.php', new PageRenderer($theme, $this->page())->getPageLayoutPath());
        self::assertSame('https://example.test/themes/grandparent/css/site.css', $theme->getAssetUrl('css/site.css'));
        $globalConfig = $GLOBALS['phpb_config'];
        self::assertIsArray($globalConfig);
        $themeConfig = $this->config;
        unset($themeConfig['parents']);
        $themeConfig['class'] = $theme::class;
        $globalConfig['theme'] = $themeConfig;
        $GLOBALS['phpb_config'] = $globalConfig;
        // Header Footer Builder canvas assets resolve outside a page rendering context too.
        self::assertSame('https://example.test/themes/grandparent/css/site.css', phpb_theme_asset('css/site.css'));

    }

    public function testWholeDirectoryPolicyAlsoAppliesToLayouts(): void
    {
        $this->put('parent/layouts/main/view.php', 'Parent layout');
        $this->putConfig('parent/layouts/main', ['title' => 'Parent', 'options' => ['inherited' => true]]);
        $this->putConfig('child/layouts/main', ['title' => 'Child']);
        $theme = $this->metadataTheme();
        $layout = new ThemeLayout($theme, 'main');
        self::assertSame('Child', $layout->getTitle());
        self::assertNull($layout->get('options'));
        self::assertNull(new PageRenderer($theme, $this->page())->getPageLayoutPath());
        $this->put('child/layouts/main/view.php', 'Child layout');
        self::assertSame($this->root . '/child/layouts/main/view.php', new PageRenderer($theme, $this->page())->getPageLayoutPath());
    }

    public function testMetadataParentCyclesAreValidatedByTheSharedLoader(): void
    {
        $theme = new class ($this->config, 'child') extends Theme {
            protected function getParentThemeSlug(string $themeSlug): ?string
            {
                return match ($themeSlug) {
                    'child' => 'parent',
                    'parent' => 'child',
                    default => null,
                };
            }
        };
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Circular');
        $theme->getThemeFolders();
    }

    public function testChildThumbnailsBelongToTheActiveThemeAndTrackInheritedFiles(): void
    {
        $this->put('parent/blocks/hero/view.html', 'Unchanged parent view');
        $this->put('parent/blocks/hero/script.js', 'Parent script');
        $theme = $this->metadataTheme();
        $block = new ThemeBlock($theme, 'hero');
        $firstPath = $block->getThumbPath();
        self::assertStringStartsWith($this->root . '/child/block-thumbs/', $firstPath);
        self::assertSame('https://example.test/themes/child/' . substr($firstPath, strlen($this->root . '/child/')), $block->getThumbUrl());
        $this->put('parent/blocks/hero/script.js', 'Changed inherited script');
        self::assertNotSame($firstPath, new ThemeBlock($theme, 'hero')->getThumbPath());
        $secondPath = $block->getThumbPath();
        $this->putConfig('parent/blocks/hero', ['title' => 'Changed inherited configuration']);
        self::assertNotSame($secondPath, new ThemeBlock($theme, 'hero')->getThumbPath());
    }

    public function testStandaloneThumbnailNamingRemainsCompatible(): void
    {
        $this->put('parent/blocks/hero/view.html', 'Standalone view');
        $theme = new Theme(['folder' => $this->root, 'folder_url' => '/themes'], 'parent');
        $relative = md5('hero') . '/' . md5('Standalone view') . '.jpg';
        $block = new ThemeBlock($theme, 'hero');
        self::assertSame($this->root . '/parent/public/block-thumbs/' . $relative, $block->getThumbPath());
        self::assertSame('https://example.test/themes/parent/block-thumbs/' . $relative, $block->getThumbUrl());
    }

    public function testCyclesFailClearly(): void
    {
        $this->config['parents'] = ['child' => 'parent', 'parent' => 'child'];
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Circular');
        $this->theme()->getThemeFolders();
    }

    public function testMissingParentFailsClearly(): void
    {
        $this->config['parents'] = ['child' => 'missing'];
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not exist');
        $this->theme()->getThemeFolders();
    }

    /** @return list<array{string}> */
    public static function unsafePaths(): array
    {
        return [['../secret'], ['/etc/passwd'], ['..\\secret'], ['%2e%2e/secret'], ['css/%2e%2e/secret'], ["bad\0file"]];
    }

    #[DataProvider('unsafePaths')]
    public function testAssetPathsCannotTraverse(string $path): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->theme()->getAssetUrl($path);
    }

    public function testThemeSlugCannotTraverse(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Theme($this->config, '../parent');
    }

    public function testBlockSlugCannotTraverse(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ThemeBlock($this->theme(), '../hero');
    }

    public function testLayoutSlugCannotTraverse(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ThemeLayout($this->theme(), '../main');
    }

    public function testSymlinkedTemplateCannotEscapeTheme(): void
    {
        $this->put('outside/config.php', '<?php throw new \\RuntimeException("Executed outside theme");');
        mkdir($this->root . '/child/blocks/hero', 0700, true);
        symlink($this->root . '/outside/config.php', $this->root . '/child/blocks/hero/config.php');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('escapes');
        new ThemeBlock($this->theme(), 'hero');
    }

    public function testSymlinkedThemeCannotEscapeThemeRoot(): void
    {
        rmdir($this->root . '/parent');
        symlink(dirname(__DIR__), $this->root . '/parent');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('escapes');
        $this->theme()->getThemeFolders();
    }
}

/** Avoid database setup while exercising the real rendering and shortcode paths. */
final class ChildThemeShortcodeParser extends ShortcodeParser
{
    public function __construct(PageRenderer $pageRenderer)
    {
        $this->pageRenderer = $pageRenderer;
    }
}
