<?php

declare(strict_types=1);

namespace Vihzhuo;

use Vihzhuo\Contracts\ThemeContract;
use Vihzhuo\Core\ThemeContext;

class ThemeLayout
{
    /** @var array<string, mixed> */
    protected array $config = [];

    protected ThemeContract $theme;

    protected string $layoutSlug;

    private ThemeResource $resource;

    /**
     * Determines if a block was registered by an extension.
     *
     * @var bool $isExtension
     */
    protected bool $isExtension;

    /** Custom slug in case of extension. */
    protected ?string $extensionSlug;

    /**
     * Theme ThemeLayout.
     *
     * @param ThemeContract $theme the theme this layout belongs to
     * @param string $layoutSlug
     * @param bool $isExtension
     * @param string|null $extensionSlug
     */
    public function __construct(
        ThemeContract $theme,
        string $layoutSlug,
        bool $isExtension = false,
        ?string $extensionSlug = null
    ) {
        $this->theme = $theme;
        $this->layoutSlug = $layoutSlug;
        $this->isExtension = $isExtension;
        $this->extensionSlug = $extensionSlug;
        $this->resource = new ThemeResource(
            $theme,
            'layouts',
            $isExtension ? ($extensionSlug ?? '') : $layoutSlug,
            $isExtension ? $layoutSlug : null
        );
        // Preserve custom getFolder() implementations used by existing subclasses.
        if ($this->getFolder() !== $this->resource->getFolder()) {
            $this->resource = new ThemeResource($theme, 'layouts', '', $this->getFolder());
        }
        $this->config = ThemeContext::run($theme, function (): array {
            $this->resource->loadConfiguration(fn (string $file): mixed => require $file);
            return $this->resource->getConfig();
        });
    }

    /**
     * Return the absolute folder path of this theme layout.
     *
     * @return string
     */
    public function getFolder(): string
    {
        return $this->resource->getFolder();
    }

    /**
     * Return the view file of this theme layout.
     *
     * @return string
     */
    public function getViewFile(): string
    {
        return $this->resource->findFile(['view.php']) ?? $this->getFolder() . '/view.php';
    }

    /** Return the slug identifying this type of layout. */
    public function getSlug(): string
    {
        return $this->isExtension ? ($this->extensionSlug ?? $this->layoutSlug) : $this->layoutSlug;
    }

    /** Return the title of this theme layout. */
    public function getTitle(): string
    {
        $title = $this->get('title');
        return is_string($title) ? $title : ucfirst($this->getSlug());
    }

    /**
     * Return configuration with the given key (as dot-separated multidimensional array selector).
     *
     * @param string $key
     * @return mixed|string
     */
    public function get(string $key): mixed
    {
        // if no dot notation is used, return first dimension value or empty string
        if (!str_contains($key, '.')) {
            return $this->config[$key] ?? null;
        }

        // if dot notation is used, traverse config string
        $segments = explode('.', $key);
        $value = $this->config;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
