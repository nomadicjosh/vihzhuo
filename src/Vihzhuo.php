<?php

namespace Vihzhuo;

use Vihzhuo\Contracts\AuthContract;
use Vihzhuo\Contracts\PageContract;
use Vihzhuo\Contracts\PageTranslationContract;
use Vihzhuo\Contracts\WebsiteManagerContract;
use Vihzhuo\Contracts\PageBuilderContract;
use Vihzhuo\Contracts\RouterContract;
use Vihzhuo\Contracts\ThemeContract;
use Vihzhuo\Modules\GrapesJS\PageRenderer;
use Vihzhuo\Repositories\UploadRepository;
use Vihzhuo\Core\DB;

class Vihzhuo
{
    /**
     * @var ?AuthContract $auth
     */
    protected ?AuthContract $auth = null;

    /**
     * @var ?WebsiteManagerContract $websiteManager
     */
    protected ?WebsiteManagerContract $websiteManager = null;

    /**
     * @var ?PageBuilderContract $pageBuilder
     */
    protected ?PageBuilderContract $pageBuilder = null;

    /**
     * @var ?RouterContract $router
     */
    protected ?RouterContract $router = null;

    /**
     * @var ?ThemeContract $theme
     */
    protected ?ThemeContract $theme;

    /**
     * Vihzhuo constructor.
     *
     * @param array|null $config         configuration in the format defined in config/config.example.php
     */
    public function __construct(?array $config = [])
    {
        // do nothing if no config is provided (e.g. during composer install)
        if (empty($config)) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // if flash session data is set, set global session flash data and remove data
        if (isset($_SESSION['phpb_flash'])) {
            global $phpb_flash;
            $phpb_flash = $_SESSION['phpb_flash'];
            unset($_SESSION['phpb_flash']);
        }

        $this->setConfig($config);

        // create database connection, if enabled
        if (phpb_config('storage.use_database')) {
            $this->setDatabaseConnection(phpb_config('storage.database'));
        }

        // init the default authentication, if enabled
        if (phpb_config('auth.use_login')) {
            $this->auth = phpb_instance('auth');
        }

        // init the default website manager, if enabled
        if (phpb_config('website_manager.use_website_manager')) {
            $this->websiteManager = phpb_instance('website_manager');
        }

        // init the default page builder, active theme and page router
        $this->pageBuilder = phpb_instance('pagebuilder');

        $this->theme = phpb_instance('theme', [
            phpb_config('theme'), 
            phpb_config('theme.active_theme')
        ]);

        $this->router = phpb_instance('router');

        // load translations in the language that is currently active
        $this->loadTranslations(phpb_current_language());
    }

    /**
     * Load translations of the given language into a global variable.
     *
     * @param $language
     * @return array
     */
    public function loadTranslations($language): array
    {
        global $phpb_translations;

        $phpbLanguageFile = __DIR__ . '/../lang/' . $language . '.php';
        if (! file_exists($phpbLanguageFile)) {
            $phpbLanguageFile = __DIR__ . '/../lang/en.php';
        }
        $phpb_translations = require $phpbLanguageFile;

        // load default and current language translations of the current theme
        $themeTranslationsFolder = phpb_config('theme.folder') . '/' . phpb_config('theme.active_theme') . '/translations';
        if (file_exists($themeTranslationsFolder . '/en.php')) {
            $phpb_translations = array_merge($phpb_translations, require $themeTranslationsFolder . '/en.php');
        }
        if (file_exists($themeTranslationsFolder . '/' . $language . '.php')) {
            $phpb_translations = array_merge($phpb_translations, require $themeTranslationsFolder . '/' . $language . '.php');
        }

        $phpb_translations = phpb_instance(Translator::class)->customize($phpb_translations);
        return $phpb_translations;
    }


    /**
     * Set the Vihzhuo configuration to the given array.
     *
     * @param array $config
     */
    public function setConfig(array $config): void
    {
        global $phpb_config;
        $phpb_config = $config;
    }

    /**
     * Set the Vihzhuo database connection using the given array.
     *
     * @param array $config
     */
    public function setDatabaseConnection(array $config): void
    {
        global $phpb_db;
        $phpb_db = new DB($config);
    }

    /**
     * Set a custom auth.
     *
     * @param AuthContract $auth
     */
    public function setAuth(AuthContract $auth): void
    {
        $this->auth = $auth;
    }

    /**
     * Set a custom website manager.
     *
     * @param WebsiteManagerContract $websiteManager
     */
    public function setWebsiteManager(WebsiteManagerContract $websiteManager): void
    {
        $this->websiteManager = $websiteManager;
    }

    /**
     * Set a custom PageBuilder.
     *
     * @param PageBuilderContract $pageBuilder
     */
    public function setPageBuilder(PageBuilderContract $pageBuilder): void
    {
        $this->pageBuilder = $pageBuilder;
    }

    /**
     * Set a custom router.
     *
     * @param RouterContract $router
     */
    public function setRouter(RouterContract $router): void
    {
        $this->router = $router;
    }

    /**
     * Set a custom theme.
     *
     * @param ThemeContract $theme
     */
    public function setTheme(ThemeContract $theme): void
    {
        $this->theme = $theme;
        $this->pageBuilder?->setTheme($theme);
    }


    /**
     * Return the Auth instance of this Vihzhuo.
     *
     * @return AuthContract|null
     */
    public function getAuth(): ?AuthContract
    {
        return $this->auth;
    }

    /**
     * Return the WebsiteManager instance of this Vihzhuo.
     *
     * @return WebsiteManagerContract|null
     */
    public function getWebsiteManager(): ?WebsiteManagerContract
    {
        return $this->websiteManager;
    }

    /**
     * Return the PageBuilder instance of this Vihzhuo.
     *
     * @return PageBuilderContract|null
     */
    public function getPageBuilder(): ?PageBuilderContract
    {
        return $this->pageBuilder;
    }

    /**
     * Return the Router instance of this Vihzhuo.
     *
     * @return RouterContract|null
     */
    public function getRouter(): ?RouterContract
    {
        return $this->router;
    }

    /**
     * Return the Theme instance of this Vihzhuo.
     *
     * @return ThemeContract|null
     */
    public function getTheme(): ?ThemeContract
    {
        return $this->theme;
    }


    /**
     * Process the current GET or POST request and redirect or render the requested page.
     *
     * @param string|null $action
     * @return bool
     */
    public function handleRequest(?string $action = null): bool
    {
        $route = $route ?? $_GET['route'] ?? null;
        $action = $action ?? $_GET['action'] ?? null;

        if (! phpb_config('auth.use_login') || ! phpb_config('website_manager.use_website_manager')) {
            die('The Vihzhuo Authentication module is disabled, but no alternative has been implemented (you are still calling the standard handleRequest() method).<br>'
                . 'Implement a piece of code that checks whether the user is logged in. If logged in, call handleAuthenticatedRequest() or else call handlePublicRequest().');
        }

        // handle login and logout requests
        $this->auth->handleRequest($action);

        // handle website manager requests
        if (phpb_in_module('website_manager')) {
            $this->auth->requireAuth();
            $this->websiteManager->handleRequest($route, $action);
            header("HTTP/1.1 404 Not Found");
            die('Vihzhuo WebsiteManager page not found');
        }

        // handle page builder requests
        if (phpb_in_module('pagebuilder')) {
            $this->auth->requireAuth();
            phpb_set_in_editmode();
            $this->pageBuilder->handleRequest($route, $action);
            header("HTTP/1.1 404 Not Found");
            die('Vihzhuo PageBuilder page not found');
        }

        // handle all requests that do not need authentication
        if ($this->handlePublicRequest() !== null) {
            return true;
        }

        if (phpb_current_relative_url() === '/') {
            $this->websiteManager->renderWelcomePage();
            return true;
        }

        header("HTTP/1.1 404 Not Found");
        die('Vihzhuo page not found. Check your URL: <b>' . phpb_e(phpb_full_url(phpb_current_relative_url())) . '</b>');
    }

    /**
     * Handle public requests, allowed without any authentication.
     *
     * @return string|null
     */
    public function handlePublicRequest(): string|null
    {
        // if we are on the URL of an upload, return uploaded file
        // (note: this is a fallback option used if .htaccess does not whitelist direct access to the /uploads folder.
        // allowing direct /uploads access via .htaccess is preferred since it gives faster loading time)
        if (str_starts_with(phpb_current_relative_url(), phpb_config('general.uploads_url') . '/')) {
            $this->handleUploadedFileRequest();
            header("HTTP/1.1 404 Not Found");
            exit();
        }
        // if we are on the URL of a Vihzhuo asset, return the asset
        if (str_starts_with(phpb_current_relative_url(), phpb_config('general.assets_url') . '/')) {
            $this->handlePageBuilderAssetRequest();
            header("HTTP/1.1 404 Not Found");
            exit();
        }

        // try to find page in cache
        $cache = phpb_instance('cache');
        if (phpb_config('cache.enabled') &&
            ! isset($_GET['ignore_cache']) &&
            ! isset($_GET['refresh_cache']) &&
            ! isset($_COOKIE['ignore_cache']) &&
            PageRenderer::canBeCached()
        ) {
            $cachedContent = $cache->getForUrl(phpb_current_relative_url());
            if ($cachedContent) {
                return $cachedContent;
            }
        }

        // let the page router resolve the current URL
        $page = null;
        $pageTranslation = $this->resolvePageLanguageVariantFromUrl(phpb_current_relative_url());
        if ($pageTranslation !== null) {
            $page = $pageTranslation->getPage();
        }
        // if the URL cannot be resolved, but the lowercase version of the URL can be resolved, redirect to the lowercase URL
        if (($page->logic ?? '') === 'page-not-found' && phpb_current_relative_url() !== strtolower(phpb_current_relative_url())) {
            $pageLowerCaseUrlTranslation = $this->resolvePageLanguageVariantFromUrl(strtolower(phpb_current_relative_url()));
            if ($pageLowerCaseUrlTranslation !== null) {
                $pageLowerCaseUrl = $pageLowerCaseUrlTranslation->getPage();
                if (($pageLowerCaseUrl->logic ?? '') !== 'page-not-found') {
                    header("HTTP/1.1 301 Moved Permanently");
                    header("Location: " . strtolower(phpb_current_relative_url()));
                    exit();
                }
            }
        }
        // render page if resolved
        if ($page !== null) {
            $renderedContent = $this->pageBuilder->renderPage($page, $pageTranslation->locale);
            if (!str_contains($pageTranslation->route, '/*')) {
                $this->cacheRenderedPage($renderedContent);
            }
            return $renderedContent;
        }
        return null;
    }

    /**
     * Resolve a PageTranslation from the given URL.
     *
     * @param $url
     * @return PageTranslationContract|null
     */
    protected function resolvePageLanguageVariantFromUrl($url): ?PageTranslationContract
    {
        return $this->router->resolve($url);
    }

    /**
     * Cache the rendered page contents, if caching is enabled and the current page does not contain non-cacheable blocks.
     *
     * @param string $renderedContent
     * @param $language
     * @return void
     */
    public function cacheRenderedPage(string $renderedContent, $language = null): void
    {
        if (! phpb_config('cache.enabled') || ! PageRenderer::canBeCached() || isset($_GET['ignore_cache'])) {
            return;
        }
        $cache = phpb_instance('cache');

        // allow a forced cached page refresh, stored for the current URL but without the refresh parameter
        $url = phpb_current_relative_url();
        $url = str_replace('?refresh_cache&', '?', $url);
        $url = str_replace('?refresh_cache', '', $url);
        $url = str_replace('&refresh_cache', '', $url);
        if ($language && !str_starts_with($url, '/' . $language . '/')) {
            $cache->invalidate($url);
            $url = '/' . $language . $url;
        }

        if (! empty(PageRenderer::$skeletonCacheUrl)) {
            $url = PageRenderer::$skeletonCacheUrl;
        }
        $cache->storeForUrl($url, $renderedContent, phpb_static(PageRenderer::class)::getCacheLifetime());
    }

    /**
     * Handle authenticated requests, this method assumes you have checked that the user is currently logged in.
     *
     * @param string|null $route
     * @param string|null $action
     */
    public function handleAuthenticatedRequest(?string $route = null, ?string $action = null): void
    {
        $route = $route ?? $_GET['route'] ?? null;
        $action = $action ?? $_GET['action'] ?? null;

        // handle website manager requests
        if (phpb_config('website_manager.use_website_manager') && phpb_in_module('website_manager')) {
            $this->websiteManager->handleRequest($route, $action);
            header("HTTP/1.1 404 Not Found");
            exit();
        }

        // handle page builder requests
        if (phpb_in_module('pagebuilder')) {
            phpb_set_in_editmode();
            $this->pageBuilder->handleRequest($route, $action);
            header("HTTP/1.1 404 Not Found");
            exit();
        }
    }

    /**
     * Handle uploaded file requests.
     */
    public function handleUploadedFileRequest(): void
    {
        // get the requested file by stripping the configured uploads_url prefix from the current request URI
        $file = substr(phpb_current_relative_url(), strlen(phpb_config('general.uploads_url')) + 1);
        // $file is in the format {file id}/{file name}.{file extension}, so get file id as the part before /
        $fileId = explode('/', $file)[0];
        if (empty($fileId)) {
            header("HTTP/1.1 404 Not Found");
            exit();
        }

        $uploadRepository = new UploadRepository;
        $uploadedFile = $uploadRepository->findWhere('public_id', $fileId);
        if (empty($uploadedFile)) {
            header("HTTP/1.1 404 Not Found");
            exit();
        }

        $uploadedFile = $uploadedFile[0];
        $serverFile = realpath(phpb_config('storage.uploads_folder') . '/' . $uploadedFile->server_file);
        // add backwards compatibility for files uploaded with Vihzhuo <= v0.12.0, stored as /uploads/{id}.{extension}
        if (! $serverFile) $serverFile = realpath(phpb_config('storage.uploads_folder') . '/' . basename($uploadedFile->server_file));
        if (! $serverFile) {
            header("HTTP/1.1 404 Not Found");
            exit();
        }

        header('Content-Type: ' . $uploadedFile->mime_type);
        header('Content-Disposition: inline; filename="' . basename($uploadedFile->original_file) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Content-Length: ' . filesize($serverFile));

        readfile($serverFile);
        exit();
    }

    /**
     * Handle page builder asset requests.
     */
    public function handlePageBuilderAssetRequest(): void
    {
        // get asset file path by stripping the configured assets_url prefix from the current request URI
        $asset = substr(phpb_current_relative_url(), strlen(phpb_config('general.assets_url')) + 1);
        $asset = explode('?', $asset)[0];

        $distPath = realpath(__DIR__ . '/../dist/');
        $requestedFile = realpath($distPath . '/' . $asset);
        if (! $requestedFile) {
            header("HTTP/1.1 404 Not Found");
            exit();
        }

        // prevent path traversal by ensuring the requested file is inside the dist folder
        if (!str_starts_with($requestedFile, $distPath)) {
            header("HTTP/1.1 404 Not Found");
            exit();
        }

        // only allow specific extensions
        $ext = pathinfo($requestedFile, PATHINFO_EXTENSION);
        if (! in_array($ext, ['js', 'css', 'jpg', 'png', 'svg'])) {
            header("HTTP/1.1 404 Not Found");
            exit();
        }

        $contentTypes = [
            'js' => 'application/javascript; charset=utf-8',
            'css' => 'text/css; charset=utf-8',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'svg' => 'image/svg+xml'
        ];
        header('Content-Type: ' . $contentTypes[$ext]);
        header('Content-Disposition: inline; filename="' . basename($requestedFile) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Content-Length: ' . filesize($requestedFile));

        readfile($requestedFile);
        exit();
    }


    /**
     * Render the PageBuilder.
     *
     * @param PageContract $page
     */
    public function renderPageBuilder(PageContract $page): void
    {
        phpb_set_in_editmode();
        $this->pageBuilder->renderPageBuilder($page);
    }
}
