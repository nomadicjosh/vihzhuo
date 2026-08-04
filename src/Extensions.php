<?php

declare(strict_types=1);

namespace Vihzhuo;

class Extensions
{
    /**
     * Blocks that can be added by plugins / composer packages.
     *
     * @var array<string, string>
     */
    protected static array $blocks = [];

    /**
     * Layouts that can be added by plugins / composer packages.
     *
     * @var array<string, string>
     */
    protected static array $layouts = [];

    /** @var array{
     *     header: list<array{src: string, type: string, attributes: array<string, string>}>,
     *     footer: list<array{src: string, type: string, attributes: array<string, string>}>
     *  }
     */
    protected static array $assets = [
        'header' => [],
        'footer' => []
    ];

    /**
     * Register an asset.
     *
     * @param string $src
     * @param string $type
     * @param string $location
     * @param array<string, string> $attributes
     */
    public static function registerAsset(
        string $src,
        string $type,
        string $location = 'header',
        array $attributes = []
    ): void {
        $location = $location === 'footer' ? 'footer' : 'header';
        self::$assets[$location][] = [
            'src' => $src,
            'type' => $type,
            'attributes' => $attributes
        ];
    }

    /**
     * Register a single block.
     *
     * @param string $slug
     * @param string $directoryPath
     */
    public static function registerBlock(string $slug, string $directoryPath): void
    {
        self::$blocks[$slug] = $directoryPath;
    }

    /**
     * Register a single layout.
     *
     * @param string $slug
     * @param string $directoryPath
     */
    public static function registerLayout(string $slug, string $directoryPath): void
    {
        self::$layouts[$slug] = $directoryPath;
    }

    /**
     * Register multiple blocks at once.
     *
     * @param array<string, string> $blocks
     */
    public static function addBlocks(array $blocks): void
    {
        self::$blocks = array_merge(self::$blocks, $blocks);
    }

    /**
     * Register multiple blocks at once.
     *
     * @param array<string, string> $layouts
     */
    public static function addLayouts(array $layouts): void
    {
        self::$layouts = array_merge(self::$layouts, $layouts);
    }

    /**
     * Get all blocks.
     *
     * @return array<string, string>
     */
    public static function getBlocks(): array
    {
        return self::$blocks;
    }

    /**
     * Get all layouts.
     *
     * @return array<string, string>
     */
    public static function getLayouts(): array
    {
        return self::$layouts;
    }

    /**
     * Get a single block.
     */
    public static function getBlock(string $id): ?string
    {
        return self::$blocks[$id] ?? null;
    }

    /**
     * Get a single layout.
     */
    public static function getLayout(string $id): ?string
    {
        return self::$layouts[$id] ?? null;
    }

    /**
     * Get all header assets.
     *
     * @return list<array{src: string, type: string, attributes: array<string, string>}>
     */
    public static function getHeaderAssets(): array
    {
        return self::$assets['header'];
    }

    /**
     * Get all footer assets.
     *
     * @return list<array{src: string, type: string, attributes: array<string, string>}>
     */
    public static function getFooterAssets(): array
    {
        return self::$assets['footer'];
    }
}
