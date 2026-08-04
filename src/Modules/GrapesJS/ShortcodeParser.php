<?php

declare(strict_types=1);

namespace Vihzhuo\Modules\GrapesJS;

use ReflectionException;
use Vihzhuo\Repositories\PageTranslationRepository;
use Exception;

class ShortcodeParser
{
    protected ?PageRenderer $pageRenderer = null;

    /**
     * @var array<string, array<string, array<string, mixed>>> $renderedBlocks
     */
    protected array $renderedBlocks = [];

    /**
     * @var array<string, string> $pages
     */
    protected array $pages = [];

    protected string $language;

    /**
     * ShortcodeParser constructor.
     *
     * @param PageRenderer $pageRenderer
     * @throws ReflectionException
     */
    public function __construct(PageRenderer $pageRenderer)
    {
        $this->pageRenderer = $pageRenderer;

        $pageTranslations = new PageTranslationRepository('page_translations')
            ->findWhere('locale', phpb_current_language());
        foreach ($pageTranslations as $pageTranslation) {
            $routeTranslation = $pageTranslation->getRoute();
            foreach (phpb_route_parameters() as $routeParameter => $value) {
                $routeTranslation = str_replace('{' . $routeParameter . '}', $value, $routeTranslation);
            }
            $this->pages[$pageTranslation->getPageId()] = $routeTranslation;
        }
    }

    /**
     * Set the current language.
     *
     * @param string $language
     */
    public function setLanguage(string $language): void
    {
        $this->language = $language;
    }

    /**
     * Perform the tasks for all shortcodes in the given html string.
     *
     * @param mixed $html
     * @param array<string, mixed> $context
     * @param int $maxDepth
     * @return string
     * @throws Exception
     */
    public function doShortcodes(string $html, array $context = [], int $maxDepth = 25): string
    {
        if ($maxDepth === 0) {
            throw new Exception("Maximum doShortcodes depth has been reached, "
                . "probably due to a circular shortcode reference in one of the theme blocks.");
        }

        $html = $this->doBlockShortcodes($html, $context, $maxDepth);
        $html = $this->doPageShortcodes($html);
        $html = $this->doThemeUrlShortcodes($html);
        return $this->doBlocksContainerShortcodes($html);
    }

    /**
     * Render all blocks defined with shortcodes in the given html string.
     *
     * @param string $html
     * @param array<string, mixed> $context
     * @param int $maxDepth
     * @return string
     * @throws Exception
     */
    protected function doBlockShortcodes(string $html, array $context, int $maxDepth): string
    {
        $matches = self::findMatches('block', $html);
        if (empty($matches)) {
            return $html;
        }

        foreach ($matches as $match) {
            if (! isset($match['attributes']['slug'])) {
                continue;
            }
            $slug = $match['attributes']['slug'];
            $id = $match['attributes']['id'] ?? $slug;
            $blockContext = $context[$id] ?? [];
            $blockContext = is_array($blockContext) ? $blockContext : [];
            $settings = $blockContext['settings'] ?? [];
            $settings = is_array($settings) ? $settings : [];
            $attributes = $settings['attributes'] ?? [];
            if (is_array($attributes)) {
                foreach ($match['attributes'] as $attribute => $value) {
                    if (in_array($attribute, ['id', 'slug'], true)) {
                        continue;
                    }
                    $attributes[$attribute] = $value;
                }
                $settings['attributes'] = $attributes;
                $blockContext['settings'] = $settings;
                $context[$id] = $blockContext;
            }
            $blockHtml = $this->pageRenderer->renderBlock($slug, $id, $context, $maxDepth);

            // store rendered block in a structure used for outputting all blocks to the pagebuilder
            if (phpb_in_editmode() && str_starts_with($id, 'ID')) {
                $this->renderedBlocks[$this->language][$id] = array_filter(
                    $blockContext,
                    'is_string',
                    ARRAY_FILTER_USE_KEY
                );
                $this->renderedBlocks[$this->language][$id]['html'] = $blockHtml;
            }

            // replace shortcode match with the $blockHtml (note: this replaces only the first match)
            $pos = strpos($html, $match['shortcode']);
            if ($pos !== false) {
                $html = substr_replace($html, $blockHtml, $pos, strlen($match['shortcode']));
            }
        }

        return $html;
    }

    /**
     * Replace all page shortcodes for the corresponding absolute page url.
     *
     * @param string $html
     * @return string
     */
    protected function doPageShortcodes(string $html): string
    {
        if (phpb_in_editmode()) {
            return $html;
        }

        $matches = self::findMatches('page', $html);
        if (empty($matches)) {
            return $html;
        }

        foreach ($matches as $match) {
            if (! isset($match['attributes']['id'])) {
                continue;
            }
            $pageId = $match['attributes']['id'];

            $route = '';
            if (isset($this->pages[$pageId])) {
                $route = $this->pages[$pageId];
            }
            $html = str_replace($match['shortcode'], $route, $html);
        }

        return $html;
    }

    /**
     * Replace all [theme-url] shortcodes for the absolute URL to the theme's public folder.
     *
     * @param string $html
     * @return string
     */
    protected function doThemeUrlShortcodes(string $html): string
    {
        $matches = self::findMatches('theme-url', $html);

        if (empty($matches)) {
            return $html;
        }

        foreach ($matches as $match) {
            $folderUrl = phpb_config('theme.folder_url');
            $activeTheme = phpb_config('theme.active_theme');
            $themeUrl = (is_string($folderUrl) ? $folderUrl : '/themes') . '/' . phpb_e($activeTheme);
            $html = str_replace($match['shortcode'], $themeUrl, $html);
        }

        return $html;
    }

    /**
     * Replace all [blocks-container] shortcodes for a <div phpb-blocks-container></div>
     *
     * @param string $html
     * @return string
     */
    protected function doBlocksContainerShortcodes(string $html): string
    {
        $matches = self::findMatches('blocks-container', $html);

        if (empty($matches)) {
            return $html;
        }

        foreach ($matches as $match) {
            $replacement = '<div phpb-blocks-container></div>';
            $html = str_replace($match['shortcode'], $replacement, $html);
        }

        return $html;
    }

    /**
     * Return all matches of the given shortcode in the given html string.
     *
     * @param string $shortcode
     * @param string $html
     * @return list<array{shortcode: string, attributes: array<string, string>}> An array with for each $shortcode
     *                                                                           occurrence an array of attributes.
     */
    public static function findMatches(string $shortcode, string $html): array
    {
        // RegEx: https://www.regextester.com/104625
        $quotedShortcode = preg_quote($shortcode, '/');
        $regex = '/\[' . $quotedShortcode . '(\s.*?)?\](?:([^\[]+)?\[\/' . $quotedShortcode . '\])?/';
        preg_match_all($regex, $html, $pregMatchAll);
        $fullMatches = $pregMatchAll[0] ?? [];
        $matchAttributeStrings = $pregMatchAll[1] ?? [];

        // loop through the attribute strings of each $shortcode instance and add the parsed variants to $matches
        $matches = [];
        foreach ($matchAttributeStrings as $i => $matchAttributeString) {
            $matchAttributeString = trim((string) $matchAttributeString);

            // as long as there are attributes in the attributes string, add them to $attributes
            $attributes = [];
            while (str_contains($matchAttributeString, '=')) {
                [$attribute, $remainingString] = explode('=', $matchAttributeString, 2);
                $attribute = trim($attribute);

                // if first char is " and at least two " exist, get attribute value between ""
                if (str_starts_with($remainingString, '"') && strpos($remainingString, '"', 1) !== false) {
                    [, $value, $remainingString] = explode('"', $remainingString, 3);
                    $attributes[$attribute] = $value;
                } elseif (str_contains($remainingString, ' ')) {
                    // attribute value was not between "", get value until next
                    // whitespace or until end of $remainingString
                    [$value, $remainingString] = explode(' ', $remainingString, 2);
                    $attributes[$attribute] = $value;
                } else {
                    $attributes[$attribute] = $remainingString;
                    $remainingString = '';
                }

                $matchAttributeString = $remainingString;
            }

            $matches[] = [
                'shortcode' => (string) ($fullMatches[$i] ?? ''),
                'attributes' => $attributes
            ];
        }

        return $matches;
    }

    /**
     * Reset the structure of all rendered blocks.
     */
    public function resetRenderedBlocks(): void
    {
        $this->renderedBlocks = [];
    }

    /**
     * Return the array of all blocks rendered while parsing shortcodes.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function getRenderedBlocks(): array
    {
        return $this->renderedBlocks;
    }
}
