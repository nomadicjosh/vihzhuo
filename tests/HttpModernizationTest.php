<?php

declare(strict_types=1);

namespace Vihzhuo\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;
use Qubus\Http\ServerRequest;
use Vihzhuo\Core\HttpContext;
use Vihzhuo\Core\View;
use Vihzhuo\Modules\Auth\Auth;
use Vihzhuo\Vihzhuo;

#[CoversClass(HttpContext::class)]
#[CoversClass(View::class)]
#[CoversClass(Vihzhuo::class)]
#[CoversFunction('phpb_json')]
final class HttpModernizationTest extends TestCase
{
    protected function setUp(): void
    {
        global $phpb_config;
        $phpb_config = [
            'general' => [
                'base_url' => 'https://example.test/base',
                'assets_url' => '/assets',
                'uploads_url' => '/uploads',
            ],
            'cache' => ['enabled' => false],
            'auth' => ['url' => '/manager/auth'],
        ];
    }

    public function testHttpContextUsesPsr7RequestData(): void
    {
        $request = new ServerRequest(
            uri: 'https://example.test/base/page?tab=settings',
            method: 'POST',
            body: 'php://temp'
        )
            ->withQueryParams(['tab' => 'settings'])
            ->withParsedBody(['title' => 'Modern']);

        HttpContext::setRequest($request);

        self::assertSame('settings', HttpContext::query('tab'));
        self::assertSame('Modern', HttpContext::body('title'));
        self::assertSame('/page?tab=settings', phpb_current_relative_url());
    }

    public function testJsonHelperPreventsScriptElementBreakout(): void
    {
        $json = phpb_json('</script><script>alert("x")</script>');

        self::assertStringNotContainsString('</script>', $json);
        self::assertStringContainsString('\\u003C', $json);
    }

    public function testTopLevelTranslationGroupsRemainArrays(): void
    {
        global $phpb_translations;
        $phpb_translations = [
            'languages' => ['en' => 'English', 'es' => 'Spanish'],
        ];

        self::assertSame(
            ['en' => 'English', 'es' => 'Spanish'],
            phpb_trans('languages')
        );
    }

    public function testModuleRoutesIgnoreATrailingSlash(): void
    {
        $request = new ServerRequest(
            uri: 'https://example.test/base/manager/auth/?action=login',
            method: 'GET',
            body: 'php://temp'
        )->withQueryParams(['action' => 'login']);

        HttpContext::setRequest($request);

        self::assertSame('/manager/auth?action=login', phpb_current_relative_url());
        self::assertTrue(phpb_in_module('auth'));
    }

    public function testAuthRouteRendersTheLoginForm(): void
    {
        $request = new ServerRequest(
            uri: 'https://example.test/base/manager/auth/',
            method: 'GET',
            body: 'php://temp'
        );
        HttpContext::setRequest($request);

        $response = new Auth()->handleRequest($request);

        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('type="password"', (string) $response->getBody());
    }

    public function testViewRenderingCapturesAndEscapesOutput(): void
    {
        $html = View::render(__DIR__ . '/Fixtures/view.php', ['name' => '<Editor>']);

        self::assertSame('Hello, &lt;Editor&gt;!' . PHP_EOL, $html);
    }

    public function testAssetRequestReturnsPsr7Response(): void
    {
        $request = new ServerRequest(
            uri: 'https://example.test/assets/pagebuilder/app.css',
            method: 'GET',
            body: 'php://temp'
        );
        $application = new Vihzhuo();

        $response = $application->handlePublicRequest($request);

        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/css; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertGreaterThan(0, $response->getBody()->getSize() ?? 0);
    }

    public function testAssetRequestCannotEscapeDistributionDirectory(): void
    {
        $request = new ServerRequest(
            uri: 'https://example.test/assets/../composer.json',
            method: 'GET',
            body: 'php://temp'
        );
        $application = new Vihzhuo();

        $response = $application->handlePublicRequest($request);

        self::assertNotNull($response);
        self::assertSame(404, $response->getStatusCode());
    }
}
