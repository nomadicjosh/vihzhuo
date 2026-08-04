<?php

declare(strict_types=1);

namespace Vihzhuo;

use Exception;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qubus\Exception\Data\TypeException;
use Qubus\Http\Factories\HtmlResponseFactory;
use Qubus\Http\Factories\Psr17Factory;
use Qubus\Http\Factories\RedirectResponseFactory;
use Qubus\Http\Factories\TextResponseFactory;
use Qubus\Http\Response;
use Vihzhuo\Contracts\AuthContract;
use Vihzhuo\Contracts\CacheContract;
use Vihzhuo\Contracts\PageBuilderContract;
use Vihzhuo\Contracts\PageContract;
use Vihzhuo\Contracts\PageTranslationContract;
use Vihzhuo\Contracts\RouterContract;
use Vihzhuo\Contracts\ThemeContract;
use Vihzhuo\Contracts\WebsiteManagerContract;
use Vihzhuo\Core\DB;
use Vihzhuo\Core\HttpContext;
use Vihzhuo\Modules\GrapesJS\PageRenderer;
use Vihzhuo\Repositories\UploadRepository;

class Vihzhuo
{
    private ?AuthContract $auth = null;

    private ?WebsiteManagerContract $websiteManager = null;

    private ?PageBuilderContract $pageBuilder = null;

    private ?RouterContract $router = null;

    private ?ThemeContract $theme = null;

    /** @param array<string, mixed>|null $config */
    public function __construct(?array $config = [])
    {
        if (empty($config)) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['phpb_flash']) && is_array($_SESSION['phpb_flash'])) {
            global $phpb_flash;
            $phpb_flash = $_SESSION['phpb_flash'];
            unset($_SESSION['phpb_flash']);
        }

        $this->setConfig($config);
        if ($this->configBool('storage.use_database')) {
            $this->setDatabaseConnection($this->configArray('storage.database'));
        }
        if ($this->configBool('auth.use_login')) {
            $auth = phpb_instance('auth');
            $this->auth = $auth instanceof AuthContract ? $auth : null;
        }
        if ($this->configBool('website_manager.use_website_manager')) {
            $manager = phpb_instance('website_manager');
            $this->websiteManager = $manager instanceof WebsiteManagerContract ? $manager : null;
        }

        $pageBuilder = phpb_instance('pagebuilder');
        $theme = phpb_instance('theme', [$this->configArray('theme'), $this->configString('theme.active_theme')]);
        $router = phpb_instance('router');
        $this->pageBuilder = $pageBuilder instanceof PageBuilderContract ? $pageBuilder : null;
        $this->theme = $theme instanceof ThemeContract ? $theme : null;
        $this->router = $router instanceof RouterContract ? $router : null;

        $this->loadTranslations($this->configString('general.language', 'en'));
    }

    /** @return array<string, mixed> */
    public function loadTranslations(string $language): array
    {
        global $phpb_translations;
        $language = preg_replace('/[^a-zA-Z_-]/', '', $language) ?: 'en';
        $languageFile = __DIR__ . '/../lang/' . $language . '.php';
        if (!is_file($languageFile)) {
            $languageFile = __DIR__ . '/../lang/en.php';
        }
        $translations = require $languageFile;
        $phpb_translations = is_array($translations) ? $this->stringKeyedArray($translations) : [];

        $themeFolder = $this->configString('theme.folder') . '/' . $this->configString('theme.active_theme') . '/translations';
        foreach (array_unique(['en', $language]) as $locale) {
            $file = $themeFolder . '/' . $locale . '.php';
            if (is_file($file)) {
                $themeTranslations = require $file;
                if (is_array($themeTranslations)) {
                    $phpb_translations = array_merge($phpb_translations, $this->stringKeyedArray($themeTranslations));
                }
            }
        }

        $translator = phpb_instance(Translator::class);
        if ($translator instanceof Translator) {
            $phpb_translations = $translator->customize($phpb_translations);
        }
        return $this->stringKeyedArray($phpb_translations);
    }

    /** @param array<string, mixed> $config */
    public function setConfig(array $config): void
    {
        global $phpb_config;
        $phpb_config = $config;
    }

    /** @param array<string, mixed> $config */
    public function setDatabaseConnection(array $config): void
    {
        global $phpb_db;
        $phpb_db = new DB($config);
    }

    public function setAuth(AuthContract $auth): void
    {
        $this->auth = $auth;
    }

    public function setWebsiteManager(WebsiteManagerContract $websiteManager): void
    {
        $this->websiteManager = $websiteManager;
    }

    public function setPageBuilder(PageBuilderContract $pageBuilder): void
    {
        $this->pageBuilder = $pageBuilder;
    }

    public function setRouter(RouterContract $router): void
    {
        $this->router = $router;
    }

    public function setTheme(ThemeContract $theme): void
    {
        $this->theme = $theme;
        $this->pageBuilder?->setTheme($theme);
    }

    public function getAuth(): ?AuthContract
    {
        return $this->auth;
    }

    public function getWebsiteManager(): ?WebsiteManagerContract
    {
        return $this->websiteManager;
    }

    public function getPageBuilder(): ?PageBuilderContract
    {
        return $this->pageBuilder;
    }

    public function getRouter(): ?RouterContract
    {
        return $this->router;
    }

    public function getTheme(): ?ThemeContract
    {
        return $this->theme;
    }

    /**
     * @throws Exception
     */
    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        HttpContext::setRequest($request);
        $route = $this->queryString($request, 'route');
        $action = $this->queryString($request, 'action');

        if (!$this->configBool('auth.use_login') || !$this->configBool('website_manager.use_website_manager')) {
            return TextResponseFactory::create(
                'The default request handler requires both authentication and the website manager. '
                . 'Use handleAuthenticatedRequest() or handlePublicRequest() for a custom integration.',
                500
            );
        }

        $auth = $this->requireAuthService();
        $authResponse = $auth->handleRequest($request, $action);
        if ($authResponse !== null) {
            return $authResponse;
        }

        if (phpb_in_module('website_manager')) {
            $loginResponse = $auth->requireAuth();
            return $loginResponse ?? $this->requireWebsiteManager()->handleRequest($request, $route, $action);
        }
        if (phpb_in_module('pagebuilder')) {
            $loginResponse = $auth->requireAuth();
            if ($loginResponse !== null) {
                return $loginResponse;
            }
            phpb_set_in_editmode();
            return $this->requirePageBuilder()->handleRequest($request, $route, $action)
            ?? TextResponseFactory::create('Page builder page not found.', 404);
        }

        $publicResponse = $this->handlePublicRequest($request);
        if ($publicResponse !== null) {
            return $publicResponse;
        }
        if (phpb_current_relative_url() === '/') {
            return $this->requireWebsiteManager()->renderWelcomePage();
        }
        return HtmlResponseFactory::create(
            'Vihzhuo page not found. Check your URL: <b>' . phpb_e(phpb_full_url(phpb_current_relative_url())) . '</b>',
            404
        );
    }

    /**
     * @throws Exception
     */
    public function handlePublicRequest(ServerRequestInterface $request): ?ResponseInterface
    {
        HttpContext::setRequest($request);
        $relativeUrl = phpb_current_relative_url();
        $uploadsUrl = $this->configString('general.uploads_url');
        if ($uploadsUrl !== '' && str_starts_with($relativeUrl, $uploadsUrl . '/')) {
            return $this->handleUploadedFileRequest();
        }
        $assetsUrl = $this->configString('general.assets_url');
        if ($assetsUrl !== '' && str_starts_with($relativeUrl, $assetsUrl . '/')) {
            return $this->handlePageBuilderAssetRequest();
        }

        $query = $request->getQueryParams();
        if (
            $this->configBool('cache.enabled') && !isset($query['ignore_cache']) && !isset($query['refresh_cache'])
            && !isset($request->getCookieParams()['ignore_cache']) && PageRenderer::canBeCached()
        ) {
            $cached = $this->requireCache()->getForUrl($relativeUrl);
            if ($cached !== null) {
                return HtmlResponseFactory::create($cached);
            }
        }

        $translation = $this->resolvePageLanguageVariantFromUrl($relativeUrl);
        if (
            $translation === null && $relativeUrl !== strtolower($relativeUrl)
            && $this->resolvePageLanguageVariantFromUrl(strtolower($relativeUrl)) !== null
        ) {
            return RedirectResponseFactory::create(strtolower($relativeUrl), 301);
        }

        if ($translation === null) {
            return null;
        }
        $page = $translation->getPage();
        if (!$page instanceof PageContract) {
            return null;
        }
        $rendered = $this->requirePageBuilder()->renderPage($page, $translation->getLocale());
        if (!str_contains($translation->getRoute(), '/*')) {
            $this->cacheRenderedPage($rendered, $translation->getLocale());
        }
        return HtmlResponseFactory::create($rendered);
    }

    /**
     * Resolve the language-specific page route,
     * with an override point for host applications.
     */
    protected function resolvePageLanguageVariantFromUrl(string $url): ?PageTranslationContract
    {
        return $this->requireRouter()->resolve($url);
    }

    public function cacheRenderedPage(string $renderedContent, ?string $language = null): void
    {
        $query = HttpContext::request()->getQueryParams();
        if (!$this->configBool('cache.enabled') || !PageRenderer::canBeCached() || isset($query['ignore_cache'])) {
            return;
        }
        $cache = $this->requireCache();
        $url = str_replace(
            ['?refresh_cache&', '?refresh_cache', '&refresh_cache'],
            ['?', '', ''],
            phpb_current_relative_url()
        );
        if ($language !== null && !str_starts_with($url, '/' . $language . '/')) {
            $cache->invalidate($url);
            $url = '/' . $language . $url;
        }
        if (PageRenderer::$skeletonCacheUrl !== '') {
            $url = PageRenderer::$skeletonCacheUrl;
        }
        $cache->storeForUrl($url, $renderedContent, PageRenderer::getCacheLifetime());
    }

    public function handleAuthenticatedRequest(
        ServerRequestInterface $request,
        ?string $route = null,
        ?string $action = null
    ): ?ResponseInterface {
        HttpContext::setRequest($request);
        $route ??= $this->queryString($request, 'route');
        $action ??= $this->queryString($request, 'action');
        if ($this->configBool('website_manager.use_website_manager') && phpb_in_module('website_manager')) {
            return $this->requireWebsiteManager()->handleRequest($request, $route, $action);
        }
        if (phpb_in_module('pagebuilder')) {
            phpb_set_in_editmode();
            return $this->requirePageBuilder()->handleRequest($request, $route, $action);
        }
        return null;
    }

    /**
     * @throws Exception
     */
    public function handleUploadedFileRequest(): ResponseInterface
    {
        $prefix = $this->configString('general.uploads_url');
        $file = substr(phpb_current_relative_url(), strlen($prefix) + 1);
        $fileId = explode('/', $file)[0];
        $record = new UploadRepository()->findWhere('public_id', $fileId)[0] ?? null;
        if (!$record instanceof UploadedFile) {
            return TextResponseFactory::create('File not found.', 404);
        }
        $uploadsFolder = $this->configString('storage.uploads_folder');
        $serverFile = realpath($uploadsFolder . '/' . $record->server_file);
        $root = realpath($uploadsFolder);
        if ($serverFile === false || $root === false || !str_starts_with($serverFile, $root . DIRECTORY_SEPARATOR)) {
            return TextResponseFactory::create('File not found.', 404);
        }
        return $this->fileResponse($serverFile, $record->mime_type, $record->original_file);
    }

    /**
     * @throws Exception
     */
    public function handlePageBuilderAssetRequest(): ResponseInterface
    {
        $prefix = $this->configString('general.assets_url');
        $asset = explode('?', substr(phpb_current_relative_url(), strlen($prefix) + 1), 2)[0];
        $distPath = realpath(__DIR__ . '/../dist');
        $requestedFile = $distPath !== false ? realpath($distPath . '/' . $asset) : false;
        if (
            $distPath === false || $requestedFile === false
            || !str_starts_with($requestedFile, $distPath . DIRECTORY_SEPARATOR)
        ) {
            return TextResponseFactory::create('Asset not found.', 404);
        }
        $contentTypes = [
            'js' => 'application/javascript; charset=utf-8', 'css' => 'text/css; charset=utf-8',
            'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
            'webp' => 'image/webp', 'svg' => 'image/svg+xml', 'woff' => 'font/woff',
            'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'eot' => 'application/vnd.ms-fontobject',
        ];
        $extension = strtolower(pathinfo($requestedFile, PATHINFO_EXTENSION));
        if (!isset($contentTypes[$extension])) {
            return TextResponseFactory::create('Asset type not allowed.', 404);
        }
        return $this->fileResponse($requestedFile, $contentTypes[$extension], basename($requestedFile));
    }

    public function renderPageBuilder(PageContract $page): ResponseInterface
    {
        phpb_set_in_editmode();
        return $this->requirePageBuilder()->renderPageBuilder($page);
    }

    /**
     * @throws TypeException
     */
    private function fileResponse(string $path, string $contentType, string $filename): ResponseInterface
    {
        $stream = new Psr17Factory()->createStreamFromFile($path);
        return new Response(status: 200, headers: [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline; filename="' . basename($filename) . '"',
            'Cache-Control' => 'public, max-age=0, must-revalidate',
            'Content-Length' => (string) (filesize($path) ?: 0),
        ])->withBody($stream);
    }

    private function queryString(ServerRequestInterface $request, string $key): ?string
    {
        $value = $request->getQueryParams()[$key] ?? null;
        return is_string($value) ? $value : null;
    }

    private function configBool(string $key): bool
    {
        return phpb_config($key) === true;
    }

    private function configString(string $key, string $default = ''): string
    {
        $value = phpb_config($key);
        return is_string($value) ? $value : $default;
    }

    /** @return array<string, mixed> */
    private function configArray(string $key): array
    {
        $value = phpb_config($key);
        return is_array($value) ? $this->stringKeyedArray($value) : [];
    }

    /**
     * @param array<mixed> $data
     * @return array<string, mixed>
     */
    private function stringKeyedArray(array $data): array
    {
        return array_filter($data, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    private function requireAuthService(): AuthContract
    {
        return $this->auth ?? throw new LogicException('Authentication service is not configured.');
    }

    private function requireWebsiteManager(): WebsiteManagerContract
    {
        return $this->websiteManager ?? throw new LogicException('Website manager is not configured.');
    }

    private function requirePageBuilder(): PageBuilderContract
    {
        return $this->pageBuilder ?? throw new LogicException('Page builder is not configured.');
    }

    private function requireRouter(): RouterContract
    {
        return $this->router ?? throw new LogicException('Router is not configured.');
    }

    private function requireCache(): CacheContract
    {
        $cache = phpb_instance('cache');
        return $cache instanceof CacheContract ? $cache : throw new LogicException('Cache service is not configured.');
    }
}
