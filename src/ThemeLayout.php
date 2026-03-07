<?php

namespace Vihzhuo;

use Vihzhuo\Contracts\ThemeContract;

class ThemeLayout
{
    /**
     * @var array $config
     */
    protected array $config = [];

    /**
     * @var ?ThemeContract $theme
     */
    protected ?ThemeContract $theme = null;

    /**
     * @var string $layoutSlug
     */
    protected string $layoutSlug;

    /**
     * @var bool $isExtension
     * Determines if a block was registered by an extension.
     */
    protected bool $isExtension;

    /**
     * @var bool $extensionSlug
     * Custom slug in case of extension.
     */
    protected string|bool|null $extensionSlug;

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
        if (file_exists($this->getFolder() . '/config.php')) {
            $this->config = include $this->getFolder() . '/config.php';
        }
    }

    /**
     * Return the absolute folder path of this theme layout.
     *
     * @return string
     */
    public function getFolder(): string
    {
        return ($this->isExtension) ? ($this->layoutSlug) : $this->theme->getFolder() . '/layouts/' . $this->layoutSlug;
    }

    /**
     * Return the view file of this theme layout.
     *
     * @return string
     */
    public function getViewFile(): string
    {
        return $this->getFolder() . '/view.php';
    }

    /**
     * Return the slug identifying this type of layout.
     *
     * @return bool|string|null
     */
    public function getSlug(): bool|string|null
    {
        return $this->isExtension ? $this->extensionSlug : $this->layoutSlug;
    }

    /**
     * Return the title of this theme layout.
     *
     * @return array|string
     */
    public function getTitle(): array|string
    {
        return $this->get('title') ?? ucfirst($this->getSlug());
    }

    /**
     * Return configuration with the given key (as dot-separated multidimensional array selector).
     *
     * @param $key
     * @return mixed|string
     */
    public function get($key): mixed
    {
        // if no dot notation is used, return first dimension value or empty string
        if (!str_contains($key, '.')) {
            return $this->config[$key] ?? null;
        }

        // if dot notation is used, traverse config string
        $segments = explode('.', $key);
        $subArray = $this->config;
        foreach ($segments as $segment) {
            if (isset($subArray[$segment])) {
                $subArray = &$subArray[$segment];
            } else {
                return null;
            }
        }

        return $subArray;
    }
}
