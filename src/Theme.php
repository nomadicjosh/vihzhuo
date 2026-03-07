<?php

namespace Vihzhuo;

use DirectoryIterator;
use Vihzhuo\Contracts\ThemeContract;

class Theme implements ThemeContract
{
    /**
     * @var array $config
     */
    protected array $config;

    /**
     * @var string $themeSlug
     */
    protected string $themeSlug;

    /**
     * @var array $blocks
     */
    protected array $blocks;

    /**
     * @var array $layouts
     */
    protected array $layouts;

    /**
     * Theme constructor.
     *
     * @param array $config
     * @param string $themeSlug
     */

    public function __construct(array $config, string $themeSlug)
    {
        $this->config = $config;
        $this->themeSlug = $themeSlug;
    }

    /**
     * Load a single block entry
     */

    protected function attemptBlockRegistration($entry): void
    {
        if ($entry->isDir() && ! $entry->isDot()) {
            $blockSlug = $entry->getFilename();
            $block = new ThemeBlock($this, $blockSlug);

            $isActive = true;
            foreach (($block->get('whitelist') ?? []) as $whitelistDomain) {
                $isActive = false;
                if (str_contains(phpb_current_full_url(), $whitelistDomain)) {
                    $isActive = true;
                    break;
                }
            }

            if ($isActive) {
                $this->blocks[$blockSlug] = $block;
            }
        }
    }

    /**
     * Load a single extension block entry
     */

    protected function attemptExtensionBlockRegistration($slug, $path): void
    {
        if ($slug && $path) {
            $block = new ThemeBlock($this, $path, true, $slug);

            $isActive = true;
            foreach (($block->get('whitelist') ?? []) as $whitelistDomain) {
                $isActive = false;
                if (str_contains(phpb_current_full_url(), $whitelistDomain)) {
                    $isActive = true;
                    break;
                }
            }

            if ($isActive) {
                $this->blocks[$slug] = $block;
            }
        }
    }

    /**
     * Load a single layout entry
     */

    protected function attemptLayoutRegistration($entry): void
    {
        if ($entry->isDir() && ! $entry->isDot()) {
            $layoutSlug = $entry->getFilename();
            $layout = new ThemeLayout($this, $layoutSlug);
            $this->layouts[$layoutSlug] = $layout;
        }
    }

    /**
     * Load a single layout entry
     */

    protected function attemptExtensionLayoutRegistration($slug, $path): void
    {
        $layout = new ThemeLayout($this, $path, true, $slug);
        $this->layouts[$slug] = $layout;
    }

    /**
     * Load all blocks of the current theme.
     */
    protected function loadThemeBlocks(): void
    {
        $this->blocks = [];

        $folders = [
            '',
            '/archived',
            '/elements',
            '/php',
        ];
        foreach ($folders as $folder) {
            if (file_exists($this->getFolder() . '/blocks' . $folder)) {
                $blocksDirectory = new DirectoryIterator($this->getFolder() . '/blocks' . $folder);
                foreach ($blocksDirectory as $entry) {
                    // skip special subfolders containing blocks
                    if (in_array('/' . $entry, $folders)) {
                        continue;
                    }
                    $this->attemptBlockRegistration($entry);
                }
            }
        }

        foreach (Extensions::getBlocks() as $slug => $path) {
            $this->attemptExtensionBlockRegistration($slug, $path);
        }
    }

    /**
     * Load all layouts of the current theme.
     */
    protected function loadThemeLayouts(): void
    {
        $this->layouts = [];

        if (file_exists($this->getFolder() . '/layouts')) {
            $layoutsDirectory = new DirectoryIterator($this->getFolder() . '/layouts');
            foreach ($layoutsDirectory as $entry) {
                $this->attemptLayoutRegistration($entry);
            }
        }

        foreach (Extensions::getLayouts() as $slug => $path) {
            $this->attemptExtensionLayoutRegistration($slug, $path);
        }
    }

    /**
     * Return all blocks of this theme.
     *
     * @return array        array of ThemeBlock instances
     */
    public function getThemeBlocks(): array
    {
        $this->loadThemeBlocks();
        return $this->blocks;
    }

    /**
     * Return all layouts of this theme.
     *
     * @return array        array of ThemeLayout instances
     */
    public function getThemeLayouts(): array
    {
        $this->loadThemeLayouts();
        return $this->layouts;
    }

    /**
     * Return the absolute folder path of the theme passed to this Theme instance.
     *
     * @return string
     */
    public function getFolder(): string
    {
        return $this->config['folder'] . '/' . basename($this->themeSlug);
    }
}
