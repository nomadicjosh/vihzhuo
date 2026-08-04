<?php

declare(strict_types=1);

namespace Vihzhuo;

use Vihzhuo\Contracts\ThemeContract;

class ThemeLayout
{
    /** @var array<string, mixed> */
    protected array $config = [];

    protected ?ThemeContract $theme = null;

    protected string $layoutSlug;

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
        if (file_exists($this->getFolder() . '/config.php')) {
            $config = include $this->getFolder() . '/config.php';
            $this->config = is_array($config) ? $this->stringKeyedArray($config) : [];
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

    /**
     * @param array<mixed> $data
     * @return array<string, mixed>
     */
    private function stringKeyedArray(array $data): array
    {
        return array_filter($data, 'is_string', ARRAY_FILTER_USE_KEY);
    }
}
