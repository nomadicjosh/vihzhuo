<?php

declare(strict_types=1);

namespace Vihzhuo;

use DirectoryIterator;
use RuntimeException;
use Vihzhuo\Contracts\ThemeInheritanceContract;

class Theme implements ThemeInheritanceContract
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
        ThemeResource::validateSlug($themeSlug);
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
        $seen = [];
        foreach ($this->getThemeFolders() as $themeFolder) {
            foreach ($folders as $folder) {
                $path = $themeFolder . '/blocks' . $folder;
                if (!is_dir($path)) {
                    continue;
                }
                ThemeResource::assertContained($themeFolder, $path);
                foreach (new DirectoryIterator($path) as $entry) {
                    if (in_array('/' . $entry, $folders, true)) {
                        continue;
                    }
                    if ($entry->isDir() && !$entry->isDot()) {
                        ThemeResource::assertContained($themeFolder, $entry->getPathname());
                    }
                    if (!$entry->isDir() || $entry->isDot() || isset($seen[$entry->getFilename()])) {
                        continue;
                    }
                    $seen[$entry->getFilename()] = true;
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

        $seen = [];
        foreach ($this->getThemeFolders() as $themeFolder) {
            $path = $themeFolder . '/layouts';
            if (!is_dir($path)) {
                continue;
            }
            ThemeResource::assertContained($themeFolder, $path);
            foreach (new DirectoryIterator($path) as $entry) {
                if ($entry->isDir() && !$entry->isDot()) {
                    ThemeResource::assertContained($themeFolder, $entry->getPathname());
                }
                if (!$entry->isDir() || $entry->isDot() || isset($seen[$entry->getFilename()])) {
                    continue;
                }
                $seen[$entry->getFilename()] = true;
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
        ThemeResource::validateSlug($this->themeSlug);
        $folder = $this->config['folder'] ?? '';
        return (is_string($folder) ? $folder : '') . '/' . basename($this->themeSlug);
    }

    /** @return array<string, string> Theme slug => folder, child first. */
    public function getThemeFolders(): array
    {
        $folders = [];
        $slug = $this->themeSlug;
        $root = $this->config['folder'] ?? '';
        $root = is_string($root) ? $root : '';
        while (true) {
            ThemeResource::validateSlug($slug);
            if (array_key_exists($slug, $folders)) {
                throw new RuntimeException('Circular theme inheritance involving ' . $slug . '.');
            }
            $parent = $this->getParentThemeSlug($slug);
            $folder = $slug === $this->themeSlug ? $this->getFolder() : $root . '/' . $slug;
            if (is_dir($folder)) {
                ThemeResource::assertContained($root, $folder);
            } elseif ($folders !== [] || $parent !== null) {
                throw new RuntimeException('Theme directory does not exist: ' . $folder);
            }
            $folders[$slug] = $folder;
            if ($parent === null) {
                break;
            }
            $slug = $parent;
        }
        return $folders;
    }

    /**
     * CMS adapters can resolve the parent from their existing theme metadata.
     * Return a folder slug, never a PHP class name or an arbitrary filesystem path.
     */
    protected function getParentThemeSlug(string $themeSlug): ?string
    {
        $parents = $this->config['parents'] ?? [];
        if (!is_array($parents)) {
            throw new RuntimeException('theme.parents must be a child => parent map.');
        }
        $parent = $parents[$themeSlug] ?? null;
        if ($parent !== null && (!is_string($parent) || $parent === '')) {
            throw new RuntimeException('Parent theme for ' . $themeSlug . ' must be a non-empty slug.');
        }
        return $parent;
    }

    /**
     * CMS adapters can return false to replace a whole block/layout directory by slug.
     * The default preserves file-level overrides and configuration merging.
     */
    public function inheritsResourceFiles(): bool
    {
        return true;
    }

    /** Resolve a relative theme file, checking the child before its ancestors. */
    public function findFile(string $path): ?string
    {
        ThemeResource::validatePath($path);
        foreach ($this->getThemeFolders() as $folder) {
            $file = ThemeResource::findInFolder($folder, $path);
            if ($file !== null) {
                return $file;
            }
        }
        return null;
    }

    /** Missing assets retain the child's URL, including assets generated later. */
    public function getAssetUrl(string $path): string
    {
        return phpb_full_url($this->getAssetPath($path));
    }

    /** Return the asset URL before applying the application's base URL. */
    public function getAssetPath(string $path): string
    {
        $base = $this->config['folder_url'] ?? '/themes';
        return ThemeResource::assetPath($this, $path, is_string($base) ? $base : '/themes', $this->themeSlug);
    }
}
