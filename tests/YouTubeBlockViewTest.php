<?php

declare(strict_types=1);

namespace Vihzhuo\Tests;

use PHPUnit\Framework\TestCase;

final class YouTubeBlockViewTest extends TestCase
{
    public function testConfiguredDimensionsControlTheRenderedVideoContainer(): void
    {
        $block = new class {
            public function setting(string $name): string
            {
                return match ($name) {
                    'youtube_video_width' => '640',
                    'youtube_video_height' => '360',
                    'youtube_embed_url' => 'https://www.youtube.com/embed/example',
                    default => '',
                };
            }
        };

        ob_start();
        require dirname(__DIR__) . '/public/themes/demo/blocks/youtube/view.php';
        $html = (string) ob_get_clean();

        self::assertStringContainsString('display: inline-block;', $html);
        self::assertStringContainsString('width: 640px;', $html);
        self::assertStringContainsString('aspect-ratio: 640 / 360;', $html);
        self::assertStringContainsString('width="640"', $html);
        self::assertStringContainsString('height="360"', $html);
    }

    public function testInvalidDimensionsFallBackToTheVideoDefaults(): void
    {
        $block = new class {
            public function setting(string $name): string
            {
                return match ($name) {
                    'youtube_video_width' => 'not-a-number',
                    'youtube_video_height' => '0',
                    'youtube_embed_url' => 'https://www.youtube.com/embed/example',
                    default => '',
                };
            }
        };

        ob_start();
        require dirname(__DIR__) . '/public/themes/demo/blocks/youtube/view.php';
        $html = (string) ob_get_clean();

        self::assertStringContainsString('width: 560px;', $html);
        self::assertStringContainsString('aspect-ratio: 560 / 315;', $html);
    }
}
