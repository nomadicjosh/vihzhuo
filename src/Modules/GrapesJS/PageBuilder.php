<?php

declare(strict_types=1);

namespace Vihzhuo\Modules\GrapesJS;

use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Qubus\Http\Factories\HtmlResponseFactory;
use Qubus\Http\Factories\JsonResponseFactory;
use Qubus\Http\Factories\TextResponseFactory;
use ReflectionException;
use Vihzhuo\Contracts\PageBuilderContract;
use Vihzhuo\Contracts\PageContract;
use Vihzhuo\Contracts\ThemeContract;
use Vihzhuo\Modules\GrapesJS\Block\BlockAdapter;
use Vihzhuo\Modules\GrapesJS\Thumb\ThumbGenerator;
use Vihzhuo\Core\View;
use Vihzhuo\Repositories\PageRepository;
use Vihzhuo\Repositories\UploadRepository;
use Exception;

use function in_array;

use const DIRECTORY_SEPARATOR;
use const FILEINFO_MIME_TYPE;
use const UPLOAD_ERR_OK;

class PageBuilder implements PageBuilderContract
{
    protected ?ThemeContract $theme = null;

    /**
     * @var array<string, string>
     */
    protected array $scripts = [];

    /**
     * @var list<array{string, string}>
     */
    protected array $pages = [];

    protected ?string $css = null;

    /**
     * PageBuilder constructor.
     */
    public function __construct()
    {
        $themeConfig = phpb_config('theme');
        $themeSlug = phpb_config('theme.active_theme');
        $theme = phpb_instance(
            'theme',
            [is_array($themeConfig) ? $themeConfig : [], is_string($themeSlug) ? $themeSlug : '']
        );
        $this->theme = $theme instanceof ThemeContract ? $theme : null;
    }

    /**
     * Set the theme used while rendering pages in the page builder.
     *
     * @param ThemeContract $theme
     */
    public function setTheme(ThemeContract $theme): void
    {
        $this->theme = $theme;
    }

    /**
     * Process the current GET or POST request and redirect or render the requested page.
     *
     * @param ServerRequestInterface $request
     * @param string|null $route
     * @param string|null $action
     * @param PageContract|null $page
     * @return ResponseInterface|null
     * @throws JsonException
     * @throws Exception
     */
    public function handleRequest(
        ServerRequestInterface $request,
        ?string $route = null,
        ?string $action = null,
        ?PageContract $page = null
    ): ?ResponseInterface {
        phpb_set_in_editmode();

        if ($route === 'thumb_generator') {
            $thumbGenerator = new ThumbGenerator($this->requireTheme());
            return $thumbGenerator->handleThumbRequest($request, $action);
        }

        if ($page === null) {
            $pageId = $request->getQueryParams()['page'] ?? null;
            $pageRepository = new PageRepository();
            $page = (is_int($pageId) || is_string($pageId)) ? $pageRepository->findWithId($pageId) : null;
        }
        if (! ($page instanceof PageContract)) {
            return null;
        }

        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];

        switch ($action) {
            case null:
            case 'edit':
                return $this->renderPageBuilder($page);
            case 'store':
                if (is_string($body['data'] ?? null)) {
                    $data = json_decode($body['data'], true, 512, JSON_THROW_ON_ERROR);
                    if (!is_array($data)) {
                        return TextResponseFactory::create('Invalid page data.', 422);
                    }
                    $this->updatePage($page, $this->stringKeyedArray($data));
                    return JsonResponseFactory::create(['success' => true]);
                }
                break;
            case 'upload':
                return $this->handleFileUpload($request);
            case 'upload_delete':
                if (is_string($body['id'] ?? null)) {
                    return $this->handleFileDelete($body['id']);
                }
                break;
            case 'renderBlock':
                if (
                    is_string($body['language'] ?? null) && is_string($body['data'] ?? null)
                    && isset(phpb_active_languages()[$body['language']])
                ) {
                    $data = json_decode($body['data'], true, 512, JSON_THROW_ON_ERROR);
                    return $this->renderPageBuilderBlock(
                        $page,
                        $body['language'],
                        is_array($data) ? $this->stringKeyedArray($data) : []
                    );
                }
                break;
            case 'renderLanguageVariant':
                if (
                    is_string($body['language'] ?? null) && is_string($body['data'] ?? null)
                    && isset(phpb_active_languages()[$body['language']])
                ) {
                    $data = json_decode($body['data'], true, 512, JSON_THROW_ON_ERROR);
                    return $this->renderLanguageVariant(
                        $page,
                        $body['language'],
                        is_array($data) ? $this->stringKeyedArray($data) : []
                    );
                }
                break;
        }

        return null;
    }

    /**
     * Handle uploading of the posted file.
     *
     * @throws Exception
     */
    public function handleFileUpload(ServerRequestInterface $request): ResponseInterface
    {
        $uploaded = $request->getUploadedFiles()['files'] ?? null;
        if (is_array($uploaded)) {
            $uploaded = reset($uploaded);
        }
        if (!$uploaded instanceof UploadedFileInterface || $uploaded->getError() !== UPLOAD_ERR_OK) {
            return JsonResponseFactory::create(['error' => 'No valid upload was provided.'], 422);
        }
        $originalName = basename($uploaded->getClientFilename() ?? 'upload');
        $temporaryPath = $uploaded->getStream()->getMetadata('uri');
        $mime = is_string($temporaryPath) ? new \finfo(FILEINFO_MIME_TYPE)->file($temporaryPath) : false;
        $allowedFileTypes = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
        if (!is_string($mime) || !in_array($mime, $allowedFileTypes, true)) {
            return JsonResponseFactory::create(['error' => 'Upload MIME type is not allowed.'], 415);
        }
        $uploadsFolder = phpb_config('storage.uploads_folder');
        if (!is_string($uploadsFolder)) {
            return JsonResponseFactory::create(['error' => 'Upload storage is not configured.'], 500);
        }
        $publicId = bin2hex(random_bytes(20));
        $originalFile = str_replace(' ', '-', $originalName);
        $relativeFile = $publicId . '/' . $originalFile;
        $targetDirectory = rtrim($uploadsFolder, '/') . '/' . $publicId;
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            return JsonResponseFactory::create(['error' => 'Upload directory could not be created.'], 500);
        }
        $uploaded->moveTo($targetDirectory . '/' . $originalFile);

        $uploadRepository = new UploadRepository();
        $uploadedFile = $uploadRepository->create([
            'public_id' => $publicId,
            'original_file' => $originalFile,
            'mime_type' => $mime,
            'server_file' => $relativeFile
        ]);
        if (!$uploadedFile instanceof \Vihzhuo\UploadedFile) {
            return JsonResponseFactory::create(['error' => 'Upload metadata could not be stored.'], 500);
        }
        return JsonResponseFactory::create([
            'data' => [
                'public_id' => $publicId,
                'src' => $uploadedFile->getUrl(),
                'type' => 'image'
            ]
        ]);
    }

    /**
     * Handle deleting of the posted previously uploaded file.
     *
     * @throws Exception
     */
    public function handleFileDelete(string $publicId): ResponseInterface
    {
        $uploadRepository = new UploadRepository();
        $uploadedFileResult = $uploadRepository->findWhere('public_id', $publicId);
        if (empty($uploadedFileResult)) {
            return JsonResponseFactory::create([
                'success' => false,
                'message' => 'File not found'
            ], 404);
        }

        $uploadedFile = $uploadedFileResult[0];
        $uploadRepository->destroy($uploadedFile->id);

        $uploadsFolder = phpb_config('storage.uploads_folder');
        if (!is_string($uploadsFolder)) {
            return JsonResponseFactory::create(['error' => 'Upload storage is not configured.'], 500);
        }
        $uploadRoot = realpath($uploadsFolder);
        $serverFilePath = realpath($uploadsFolder . '/' . $uploadedFile->server_file);
        if (
            $uploadRoot !== false && $serverFilePath !== false
            && str_starts_with($serverFilePath, $uploadRoot . DIRECTORY_SEPARATOR)
        ) {
            unlink($serverFilePath);
        }

        $parentDirectory = realpath($uploadsFolder . '/' . dirname($uploadedFile->server_file));
        if (
            $uploadRoot !== false && $parentDirectory !== false
            && str_starts_with($parentDirectory, $uploadRoot . DIRECTORY_SEPARATOR)
            && dirname($uploadedFile->server_file) !== '.'
        ) {
            rmdir($parentDirectory);
        }

        return JsonResponseFactory::create([
            'success' => true
        ]);
    }

    /**
     * Render the PageBuilder for the given page.
     *
     * @param PageContract $page
     * @return ResponseInterface
     * @throws Exception
     */
    public function renderPageBuilder(PageContract $page): ResponseInterface
    {
        phpb_set_in_editmode();

        // init variables that should be accessible in the view
        $pageBuilder = $this;
        $pageRenderer = $this->pageRenderer($page, true);
        if (is_string($_SESSION['phpagebuilder_language'] ?? null)) {
            $pageRenderer->setLanguage($_SESSION['phpagebuilder_language']);
        }

        // create an array of theme blocks and theme block settings for in the page builder sidebar
        $blocks = [];
        $blockSettings = [];
        foreach ($this->requireTheme()->getThemeBlocks() as $themeBlock) {
            $slug = phpb_e($themeBlock->getSlug());
            $customAdapter = phpb_instance(BlockAdapter::class, [$pageRenderer, $themeBlock]);

            $adapter = $customAdapter instanceof BlockAdapter
            ? $customAdapter
            : new BlockAdapter($pageRenderer, $themeBlock);

            if ($themeBlock->get('hidden') !== true) {
                $blocks[$slug] = $adapter->getBlockManagerArray();
            }
            $blockSettings[$slug] = $adapter->getBlockSettingsArray();
        }

        // create an array of all uploaded assets
        $assets = [];
        foreach (new UploadRepository()->getAll() as $file) {
            $assets[] = [
                'src' => $file->getUrl(),
                'public_id' => $file->public_id
            ];
        }

        return HtmlResponseFactory::create(View::render(__DIR__ . '/resources/views/layout.php', compact(
            'pageBuilder',
            'pageRenderer',
            'blocks',
            'blockSettings',
            'assets',
            'page'
        )));
    }

    /**
     * Render the given page.
     *
     * @param PageContract $page
     * @param string|null $language
     * @return string
     * @throws Exception
     */
    public function renderPage(PageContract $page, ?string $language = null): string
    {
        $pageRenderer = $this->pageRenderer($page);
        if ($language !== null) {
            $pageRenderer->setLanguage($language);
        }
        return $pageRenderer->render();
    }

    /**
     * Render in context of the given page, the given block with the passed settings, for updating the page builder.
     *
     * @param PageContract $page
     * @param string $language
     * @param array<string, mixed> $blockData
     * @return ResponseInterface
     * @throws Exception
     */
    public function renderPageBuilderBlock(
        PageContract $page,
        string $language,
        array $blockData = []
    ): ResponseInterface {
        phpb_set_in_editmode();

        $page->setData(['data' => $blockData], false);

        $pageRenderer = $this->pageRenderer($page, true);
        $pageRenderer->setLanguage($language);
        $html = is_string($blockData['html'] ?? null) ? $blockData['html'] : '';
        $storedBlocks = $blockData['blocks'] ?? null;
        $blocks = is_array($storedBlocks) ? $this->stringKeyedArray($storedBlocks) : [];
        return HtmlResponseFactory::create($pageRenderer->parseShortcodes($html, $blocks));
    }

    /**
     * Render the given page in the given language using the given block data.
     *
     * @param PageContract $page
     * @param string $language
     * @param array<string, mixed> $blockData
     * @return ResponseInterface
     * @throws Exception
     */
    public function renderLanguageVariant(
        PageContract $page,
        string $language,
        array $blockData = []
    ): ResponseInterface {
        phpb_set_in_editmode();
        $_SESSION['phpagebuilder_language'] = $language;

        $page->setData(['data' => $blockData], false);

        $pageRenderer = $this->pageRenderer($page, true);
        $pageRenderer->setLanguage($language);
        return JsonResponseFactory::create([
            'dynamicBlocks' => $pageRenderer->getPageBlocksData()[$language] ?? null
        ]);
    }

    /**
     * Update the given page with the given data (an array of html blocks).
     *
     * @param PageContract $page
     * @param array<string, mixed> $data
     * @return bool
     * @throws JsonException
     */
    public function updatePage(PageContract $page, array $data): bool
    {
        $pageRepository = new PageRepository();
        return $pageRepository->updatePageData($page, $data);
    }

    /**
     * Set the list of all pages.
     *
     * @param list<array{string, string}> $pages
     */
    public function setPages(array $pages): void
    {
        $this->pages = $pages;
    }

    /**
     * Return the list of all pages, used in CKEditor link editor.
     *
     * @return list<array{string, string}>
     * @throws ReflectionException
     */
    public function getPages(): array
    {
        if (! empty($this->pages)) {
            return $this->pages;
        }

        $pages = [];
        $pageRepository = new PageRepository();
        foreach ($pageRepository->getAll() as $page) {
            $pages[] = [
                phpb_e($page->getName()),
                phpb_e($page->getId())
            ];
        }
        $this->pages = $pages;
        return $pages;
    }

    /**
     * Return this page's components in the format passed to GrapesJS.
     *
     * @param PageContract $page
     * @return array<int, mixed>
     */
    public function getPageComponents(PageContract $page): array
    {
        $data = $page->getBuilderData();
        $storedComponents = $data['components'] ?? [0 => []];
        $components = is_array($storedComponents) ? array_values($storedComponents) : [0 => []];
        // backwards compatibility, components are now stored for each main container
        if (isset($components[0]) && is_array($components[0]) && $components[0] !== [] && !isset($components[0][0])) {
            return [0 => $components];
        }
        return $components;
    }

    /**
     * Return this page's style in the format passed to GrapesJS.
     *
     * @param PageContract $page
     * @return array<int|string, mixed>
     */
    public function getPageStyleComponents(PageContract $page): array
    {
        $data = $page->getBuilderData();
        if (is_array($data['style'] ?? null)) {
            return $data['style'];
        }
        return [];
    }

    /**
     * Return this page's css in the format passed to GrapesJS.
     *
     * @param PageContract $page
     * @return string
     */
    public function getPageStyleCss(PageContract $page): string
    {
        $data = $page->getBuilderData();
        if (is_string($data['css'] ?? null)) {
            return $data['css'];
        }
        return '';
    }

    /**
     * Get or set custom css for customizing layout of the page builder.
     *
     * @param string|null $css
     * @return string|null
     */
    public function customStyle(?string $css = null): ?string
    {
        if ($css !== null) {
            $this->css = $css;
        }
        return $this->css;
    }

    /**
     * Get or set custom scripts for customizing behaviour of the page builder.
     *
     * @param string $location head|body
     * @param string|null $scripts
     * @return string
     */
    public function customScripts(string $location, ?string $scripts = null): string
    {
        if ($scripts !== null) {
            $this->scripts[$location] = $scripts;
        }
        return $this->scripts[$location] ?? '';
    }

    private function requireTheme(): ThemeContract
    {
        return $this->theme ?? throw new \LogicException('Page builder theme is not configured.');
    }

    /**
     * @throws ReflectionException
     */
    private function pageRenderer(PageContract $page, bool $forPageBuilder = false): PageRenderer
    {
        $renderer = phpb_instance(PageRenderer::class, [$this->requireTheme(), $page, $forPageBuilder]);
        return $renderer instanceof PageRenderer
        ? $renderer :
        new PageRenderer($this->requireTheme(), $page, $forPageBuilder);
    }

    /**
     * @param array<mixed> $data
     * @return array<string, mixed>
     */
    private function stringKeyedArray(array $data): array
    {
        return array_filter($data, 'is_string', ARRAY_FILTER_USE_KEY);
    }
}
