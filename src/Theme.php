<?php

declare(strict_types=1);

namespace Vihzhuo;

use DirectoryIterator;
use Vihzhuo\Contracts\ThemeContract;

class Theme implements ThemeContract
{
    /** @var array<string, mixed> */
    protected array $config;

    protected string $themeSlug;

    /** @var array<string, ThemeBlock> */
    protected array $blocks = [];

    /** @var array<string, ThemeLayout> */
    protected array $layouts = [];

    /**
     * Theme constructor.
     *
     * @param array<string, mixed> $config
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
    protected function attemptBlockRegistration(DirectoryIterator $entry): void
    {
        if ($entry->isDir() && ! $entry->isDot()) {
            $blockSlug = $entry->getFilename();
            $block = new ThemeBlock($this, $blockSlug);

            $isActive = true;
            $whitelist = $block->get('whitelist');
            foreach (is_array($whitelist) ? $whitelist : [] as $whitelistDomain) {
                $isActive = false;
                if (is_string($whitelistDomain) && str_contains(phpb_current_full_url() ?? '', $whitelistDomain)) {
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
    protected function attemptExtensionBlockRegistration(string $slug, string $path): void
    {
        if ($slug && $path) {
            $block = new ThemeBlock($this, $path, true, $slug);

            $isActive = true;
            $whitelist = $block->get('whitelist');
            foreach (is_array($whitelist) ? $whitelist : [] as $whitelistDomain) {
                $isActive = false;
                if (is_string($whitelistDomain) && str_contains(phpb_current_full_url() ?? '', $whitelistDomain)) {
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
    protected function attemptLayoutRegistration(DirectoryIterator $entry): void
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
    protected function attemptExtensionLayoutRegistration(string $slug, string $path): void
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

    /** @return array<string, ThemeBlock> */
    public function getThemeBlocks(): array
    {
        $this->loadThemeBlocks();
        return $this->blocks;
    }

    /** @return array<string, ThemeLayout> */
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
        $folder = $this->config['folder'] ?? '';
        return (is_string($folder) ? $folder : '') . '/' . basename($this->themeSlug);
    }
}
