<?php

declare(strict_types=1);

namespace Vihzhuo\Modules\GrapesJS;

use ReflectionException;
use Vihzhuo\Contracts\PageContract;
use Vihzhuo\Contracts\ThemeContract;
use Vihzhuo\Modules\GrapesJS\Block\BlockRenderer;
use Vihzhuo\ThemeBlock;
use Exception;
use Vihzhuo\Extensions;
use Vihzhuo\Core\View;

class PageRenderer
{
    protected ?ThemeContract $theme = null;

    protected ?PageContract $page = null;

    /**
     * @var array<string, mixed>  $pageData
     */
    protected array $pageData = [];

    /**
     * @var array<string, mixed>  $pageBlocksData
     */
    protected array $pageBlocksData = [];

    protected ?ShortcodeParser $shortcodeParser = null;

    protected bool $forPageBuilder;

    protected string $language;

    public static bool $canBeCached;

    public static string $skeletonCacheUrl;

    /**
     * The maximum number of minutes this page should be cached, one week by default.
     *
     * @var int $cacheLifetime
     */
    public static int $cacheLifetime = 7 * 24 * 60;

    /**
     * PageRenderer constructor.
     *
     * @param ThemeContract $theme
     * @param PageContract $page
     * @param bool $forPageBuilder
     * @throws ReflectionException
     */
    public function __construct(ThemeContract $theme, PageContract $page, bool $forPageBuilder = false)
    {
        $this->theme = $theme;
        $this->page = $page;
        $this->pageData = $page->getBuilderData();
        $parser = phpb_instance(ShortcodeParser::class, [$this]);
        $this->shortcodeParser = $parser instanceof ShortcodeParser ? $parser : new ShortcodeParser($this);
        $this->setLanguage(phpb_current_language());
        $this->forPageBuilder = $forPageBuilder;
    }

    /**
     * Set which page language variant to use while rendering.
     *
     * @param string $language
     */
    public function setLanguage(string $language): void
    {
        // if the given language is unknown, default the set language to the first available language
        $blockKeysAreLanguages = true;
        $storedBlocks = $this->pageData['blocks'] ?? [];
        $storedBlockLanguages = is_array($storedBlocks) ? array_map('strval', array_keys($storedBlocks)) : [];
        // check whether keys are valid languages (renderPageBuilderBlock uses pageData without language data)
        foreach ($storedBlockLanguages as $supportedLanguage) {
            if (strlen($supportedLanguage) > 5) {
                $blockKeysAreLanguages = false;
                break;
            }
        }
        if ($blockKeysAreLanguages && $storedBlockLanguages !== [] && !in_array($language, $storedBlockLanguages, true)) {
            if (! isset(phpb_active_languages()[$language])) {
                $language = $storedBlockLanguages[0];
            } else {
                $blocks = is_array($this->pageData['blocks'] ?? null) ? $this->pageData['blocks'] : [];
                $blocks[$language] = $blocks[$storedBlockLanguages[0]] ?? [];
                $this->pageData['blocks'] = $blocks;
            }
        }

        $this->language = $language;
        $this->pageBlocksData = $this->getStoredPageBlocksData();
        $this->shortcodeParser->setLanguage($language);
    }

    /**
     * Return the absolute path to the layout view of this page.
     *
     * @return string|null
     */
    public function getPageLayoutPath(): ?string
    {
        $layout = basename($this->page->getLayout());
        $layoutPath = $this->theme->getFolder() . '/layouts/' . $layout . '/view.php';

        if ($path = Extensions::getLayout($layout)) {
            $layoutPath = $path . '/view.php';
        }

        return file_exists($layoutPath) ? $layoutPath : null;
    }

    /**
     * Set whether the currently rendered page can be cached.
     *
     * @param bool $canBeCached
     * @param string|null $cacheLifetime
     */
    public static function setCanBeCached(bool $canBeCached, ?string $cacheLifetime = null): void
    {
        if (! $canBeCached || ($cacheLifetime && (int) $cacheLifetime <= 0)) {
            static::$canBeCached = false;
        } elseif ($cacheLifetime) {
            static::$cacheLifetime = min(static::$cacheLifetime, (int) $cacheLifetime);
        }
    }

    /**
     * Return whether the rendered page can be cached.
     * I.e. no blocks were encountered with content that varies per page load.
     *
     * @return bool
     */
    public static function canBeCached(): bool
    {
        return static::$canBeCached ?? true;
    }

    /**
     * Return the maximum number of minutes the rendered page should be cached.
     *
     * @return int
     */
    public static function getCacheLifetime(): int
    {
        if (! static::canBeCached()) {
            return 0;
        }
        return static::$cacheLifetime;
    }

    /**
     * Return an array with for each block of this page the stored html and settings data.
     *
     * @return array<string, mixed>
     */
    public function getStoredPageBlocksData(): array
    {
        $blocks = $this->pageData['blocks'] ?? [];
        if (!is_array($blocks)) {
            return [];
        }
        $languageBlocks = $blocks[$this->language] ?? $blocks;
        return is_array($languageBlocks) ? array_filter($languageBlocks, 'is_string', ARRAY_FILTER_USE_KEY) : [];
    }

    /**
     * Return the rendered version of the page.
     *
     * @return string
     * @throws Exception
     */
    public function render(): string
    {
        // init variables that should be accessible in the view
        $renderer = $this;
        $page = $this->page;
        $body = $this->forPageBuilder ? '<div phpb-content-container="true"></div>' : $this->renderBody();

        $layoutPath = $this->getPageLayoutPath();
        if ($layoutPath) {
            $pageHtml = View::render($layoutPath, compact('renderer', 'page', 'body'));
        } else {
            $pageHtml = $body;
        }

        // parse any shortcodes present in the page layout
        return $this->parseShortcodes($pageHtml);
    }

    /**
     * Return the page body for display on the website.
     * The body contains all blocks which are put into the selected layout.
     *
     * @param int $mainContainerIndex
     * @return string
     * @throws Exception
     */
    public function renderBody(int $mainContainerIndex = 0): string
    {
        $html = '';
        $data = $this->pageData;

        if (isset($data['html']) && is_array($data['html'])) {
            $containerHtml = $data['html'][$mainContainerIndex] ?? '';
            $html = $this->parseShortcodes(is_string($containerHtml) ? $containerHtml : '');
            // render html for each content container, to ensure all rendered blocks are accessible in the pagebuilder
            if (phpb_in_editmode()) {
                foreach ($data['html'] as $contentContainerHtml) {
                    if (is_string($contentContainerHtml)) {
                        $this->parseShortcodes($contentContainerHtml);
                    }
                }
            }
        }
        // backwards compatibility, html stored for only one layout container
        if (isset($data['html']) && is_string($data['html'])) {
            $html = $this->parseShortcodes($data['html']);
        }

        // include any style changes made via the page builder
        if (is_string($data['css'] ?? null)) {
            return '<style>' . $data['css'] . '</style>' . $html;
        }

        return $html;
    }

    /**
     * Return a fully rendered theme block (including children blocks) with
     * the given slug, data instance id and data context.
     * This method is called while parsing shortcodes.
     *
     * @param string $slug
     * @param string|null $id the id with which data for this block is stored
     * @param array<string, mixed>|null $context
     * @param int $maxDepth
     * @return string
     * @throws Exception
     */
    public function renderBlock(string $slug, ?string $id = null, ?array $context = null, int $maxDepth = 25): string
    {
        $themeBlock = ($blockPath = Extensions::getBlock($slug))
        ? new ThemeBlock($this->theme, $blockPath, true, $slug)
        : new ThemeBlock($this->theme, $slug);

        $id ??= $themeBlock->getSlug();
        $contextData = $context[$id] ?? $this->pageBlocksData[$id] ?? [];
        $contextData = is_array($contextData) ? $contextData : [];

        $blockRenderer = new BlockRenderer($this->theme, $this->page, $this->forPageBuilder);
        $renderedBlock = $blockRenderer->render($themeBlock, $this->stringKeyedArray($contextData), $id);

        // determine the context for rendering nested blocks
        // if the current block is a html block, the context starts again at full page data
        // if the current block is a dynamic block, use the nested block data inside the current block's context
        $nestedContext = $contextData['blocks'] ?? [];
        $nestedContext = is_array($nestedContext) ? $nestedContext : [];
        if ($themeBlock->isHtmlBlock()) {
            $nestedContext = $this->pageBlocksData;
        }

        return $this->shortcodeParser->doShortcodes(
            $renderedBlock,
            $this->stringKeyedArray($nestedContext),
            $maxDepth - 1
        );
    }

    /**
     * Parse the given html with shortcodes to fully rendered html.
     *
     * @param string $htmlWithShortcodes
     * @param array<string, mixed>|null $context The data for each block to be used while parsing the shortcodes.
     * @return string
     * @throws Exception
     */
    public function parseShortcodes(string $htmlWithShortcodes, ?array $context = null): string
    {
        $context = $context ?? $this->pageBlocksData;
        return $this->shortcodeParser->doShortcodes($htmlWithShortcodes, $context);
    }

    /**
     * Return this page's blocks data to be loaded into the page edited inside GrapesJS.
     *
     * @return array<string, array<string, mixed>|null>
     * @throws Exception
     */
    public function getPageBlocksData(): array
    {
        $initialLanguage = $this->language;

        // remove the already rendered blocks
        $this->shortcodeParser->resetRenderedBlocks();

        // create the structure of page blocks data for each language
        $pageBlocks = [];
        foreach (phpb_active_languages() as $languageCode => $languageTranslation) {
            $this->setLanguage($languageCode);

            // for the current language build up a structure of rendered
            // versions and use the stored data for the other languages
            if ($languageCode === $initialLanguage) {
                $this->renderBody();
                $pageBlocks[$languageCode] = $this->shortcodeParser->getRenderedBlocks()[$languageCode] ?? [];
            } else {
                $pageBlocks[$languageCode] = $this->pageBlocksData;
            }

            if (empty($pageBlocks[$languageCode])) {
                $pageBlocks[$languageCode] = null;
            }
        }

        // revert to initial language
        $this->setLanguage($initialLanguage);

        // return the rendered html and settings for each block
        return $pageBlocks;
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
