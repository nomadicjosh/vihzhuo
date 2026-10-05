<?php

declare(strict_types=1);

namespace Vihzhuo\Tests;

use PHPUnit\Framework\TestCase;
use Vihzhuo\Extensions;
use Vihzhuo\Modules\GrapesJS\Block\BlockRenderer;
use Vihzhuo\Page;
use Vihzhuo\Theme;

final class ExampleBlocksTest extends TestCase
{
    protected function setUp(): void
    {
        global $phpb_config, $phpb_translations;

        $phpb_config = [
            'general' => [
                'base_url' => 'https://example.test',
                'language' => 'en',
            ],
            'theme' => [
                'folder' => dirname(__DIR__) . '/public/themes',
                'folder_url' => '/themes',
                'active_theme' => 'demo',
            ],
        ];
        $phpb_translations = [];

        require dirname(__DIR__) . '/examples/register-blocks.php';
    }

    public function testExampleRegistrationProvidesBothBlocks(): void
    {
        self::assertSame(
            dirname(__DIR__) . '/examples/blocks/editable-card',
            Extensions::getBlock('example-editable-card')
        );
        self::assertSame(
            dirname(__DIR__) . '/examples/blocks/page-banner',
            Extensions::getBlock('example-page-banner')
        );
    }

    public function testEditableCardUsesStoredHtml(): void
    {
        $html = $this->renderer()->renderWithSlug('example-editable-card', [
            'html' => '<article>Edited card</article>',
        ]);

        self::assertSame('<article>Edited card</article>', $html);
    }

    public function testDynamicBannerUsesSettingsModelAndController(): void
    {
        $html = $this->renderer()->renderWithSlug('example-page-banner', [
            'settings' => [
                'attributes' => [
                    'eyebrow' => 'Registered extension',
                    'heading' => 'Rendered heading',
                    'message' => 'Rendered message',
                    'tone' => 'success',
                    'show_context' => '1',
                    'link_label' => 'Safe link',
                    'link_url' => 'javascript:alert(1)',
                ],
            ],
        ]);

        self::assertStringContainsString('alert-success', $html);
        self::assertStringContainsString('Rendered heading', $html);
        self::assertStringContainsString('Page: Example page', $html);
        self::assertStringContainsString('Public route: /examples', $html);
        self::assertStringContainsString('href="#"', $html);
        self::assertStringNotContainsString('javascript:', $html);
    }

    private function renderer(): BlockRenderer
    {
        $page = new Page();
        $page->setData([
            'id' => '1',
            'name' => 'Example page',
            'layout' => 'main',
            'data' => [],
        ]);
        $page->setTranslations([
            'en' => [
                'route' => '/examples',
                'title' => 'Example page',
            ],
        ]);

        $theme = new Theme([
            'folder' => dirname(__DIR__) . '/public/themes',
            'folder_url' => '/themes',
        ], 'demo');

        return new BlockRenderer($theme, $page);
    }
}
