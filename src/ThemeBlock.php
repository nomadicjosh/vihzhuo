<?php

declare(strict_types=1);

namespace Vihzhuo;

use Vihzhuo\Contracts\ThemeContract;
use Vihzhuo\Core\ThemeContext;
use Vihzhuo\Modules\GrapesJS\Block\BaseController;
use Vihzhuo\Modules\GrapesJS\Block\BaseModel;
use Vihzhuo\Modules\GrapesJS\PageRenderer;

class ThemeBlock
{
    /** @var array<string, mixed> */
    protected array $config = [];

    /** @var array<string, mixed> */
    public static array $dynamicConfig = [];

    protected ThemeContract $theme;

    protected string $blockSlug;

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
     * Theme constructor.
     *
     * @param ThemeContract $theme The theme this block belongs to
     * @param string $blockSlug
     * @param bool $isExtension
     * @param string|null $extensionSlug
     */
    public function __construct(
        ThemeContract $theme,
        string $blockSlug,
        bool $isExtension = false,
        ?string $extensionSlug = null
    ) {
        $this->theme = $theme;
        $this->blockSlug = $blockSlug;
        $this->isExtension = $isExtension;
        $this->extensionSlug = $extensionSlug;
        $this->resource = new ThemeResource(
            $theme,
            'blocks',
            $isExtension ? ($extensionSlug ?? '') : $blockSlug,
            $isExtension ? $blockSlug : null
        );
        // Preserve custom getFolder() implementations used by existing subclasses.
        if ($this->getFolder() !== $this->resource->getFolder()) {
            $this->resource = new ThemeResource($theme, 'blocks', '', $this->getFolder());
        }
        $this->config = ThemeContext::run($theme, function (): array {
            $this->resource->loadConfiguration(fn (string $file): mixed => require $file);
            return $this->resource->getConfig();
        });

        PageRenderer::setCanBeCached(
            (bool) ($this->config['cache'] ?? true),
            is_scalar($this->config['cache_lifetime'] ?? null) ? (string) $this->config['cache_lifetime'] : null
        );
    }

    /**
     * Return the absolute folder path of this theme block.
     *
     * @return string
     */
    public function getFolder(): string
    {
        return $this->resource->getFolder();
    }

    /**
     * Return the namespace to the folder of this theme block.
     *
     * @return string
     */
    protected function getNamespace(): string
    {
        // return Namespace from the Config file of the Block if it is an extension. Used for Extensions.
        if (isset($this->config['namespace'])) {
            return is_string($this->config['namespace']) ? $this->config['namespace'] : '';
        }

        // return Namespace from Config file if exists;
        $configuredNamespace = phpb_config('theme.namespace');
        if (is_string($configuredNamespace) && $configuredNamespace !== '') {
            return $configuredNamespace;
        }

        // get namespace from directory structure if not provided:
        $configuredPath = phpb_config('theme.folder');
        $themesPath = is_string($configuredPath) ? $configuredPath : '';
        $blockFolder = $this->getFolder();
        return $this->namespaceFromFolder($blockFolder, $themesPath);
    }

    private function namespaceFromFolder(string $blockFolder, string $themesPath): string
    {
        $themesFolderName = basename($themesPath);
        $namespacePath = $themesFolderName . str_replace($themesPath, '', $blockFolder);

        // convert each character after a - to uppercase
        $namespace = implode('-', array_map('ucfirst', explode('-', $namespacePath)));
        // convert each character after a _ to uppercase
        $namespace = implode('_', array_map('ucfirst', explode('-', $namespace)));
        // convert each character after a / to uppercase
        $namespace = implode('/', array_map('ucfirst', explode('/', $namespace)));
        // remove all dashes
        $namespace = str_replace('-', '', $namespace);
        // remove all underscores
        $namespace = str_replace('_', '', $namespace);
        // replace / by \
        $namespace = str_replace('/', '\\', $namespace);

        return $namespace;
    }

    /**
     * Return the controller class of this theme block.
     *
     * @return string
     */
    public function getControllerClass(): string
    {
        $file = $this->getControllerFile();
        return $file !== null ? $this->namespaceForFile($file) . '\\Controller' : BaseController::class;
    }

    /** Return the controller file of this theme block. */
    public function getControllerFile(): ?string
    {
        return $this->resource->findFile(['controller.php']);
    }

    /** Return the model class of this theme block. */
    public function getModelClass(): string
    {
        $file = $this->getModelFile();
        return $file !== null ? $this->namespaceForFile($file) . '\\Model' : BaseModel::class;
    }

    /** Return the model file of this theme block. */
    public function getModelFile(): ?string
    {
        return $this->resource->findFile(['model.php']);
    }

    private function namespaceForFile(string $file): string
    {
        if (dirname($file) === $this->getFolder()) {
            return $this->getNamespace();
        }
        $namespace = $this->resource->getFileNamespace($file);
        if ($namespace !== null) {
            return $namespace;
        }
        $configuredNamespace = phpb_config('theme.namespace');
        if (is_string($configuredNamespace) && $configuredNamespace !== '') {
            return $configuredNamespace;
        }
        $configuredPath = phpb_config('theme.folder');
        return $this->namespaceFromFolder(dirname($file), is_string($configuredPath) ? $configuredPath : '');
    }

    /**
     * Return the view file of this theme block.
     *
     * @return string
     */
    public function getViewFile(): string
    {
        $name = $this->isPhpBlock() ? 'view.php' : 'view.html';
        return $this->resource->findFile([$name]) ?? $this->getFolder() . '/' . $name;
    }

    /**
     * Return the pagebuilder script file of this theme block.
     * This script can be used to assist correct rendering of the block in the pagebuilder.
     *
     * @return string|null
     */
    public function getBuilderScriptFile(): ?string
    {
        return $this->resource->findFile(['builder-script.php', 'builder-script.html', 'builder-script.js'])
        ?? $this->getScriptFile();
    }

    /**
     * Return the script file of this theme block.
     * This script can be used to assist correct
     * rendering of the block when used on a
     * publicly accessed web page.
     *
     * @return string|null
     */
    public function getScriptFile(): ?string
    {
        return $this->resource->findFile(['script.php', 'script.html', 'script.js']);
    }

    /**
     * Return the file path of the thumbnail of this block.
     *
     * @return string
     */
    public function getThumbPath(): string
    {
        $folder = $this->usesInheritedTheme() ? '/block-thumbs/' : '/public/block-thumbs/';
        return $this->theme->getFolder() . $folder . $this->thumbRelativePath();
    }

    public function getThumbUrl(): string
    {
        $path = 'block-thumbs/' . $this->thumbRelativePath();
        return $this->theme instanceof Theme ? $this->theme->getAssetUrl($path) : phpb_theme_asset($path);
    }

    private function usesInheritedTheme(): bool
    {
        return count(ThemeResource::themeFolders($this->theme)) > 1;
    }

    private function thumbRelativePath(): string
    {
        // Preserve existing standalone thumbnail names and storage conventions.
        $version = $this->usesInheritedTheme()
        ? $this->resource->fingerprint()
        : md5((string) file_get_contents($this->getViewFile()));
        return md5($this->blockSlug) . '/' . $version . '.jpg';
    }

    /** Return the slug identifying this type of block. */
    public function getSlug(): string
    {
        return ($this->isExtension) ? ($this->extensionSlug ?? $this->blockSlug) : $this->blockSlug;
    }

    /**
     * Return whether this block is a block containing/allowing PHP code.
     *
     * @return bool
     */
    public function isPhpBlock(): bool
    {
        return str_ends_with($this->resource->findFile(['view.php', 'view.html']) ?? '', '/view.php');
    }

    /**
     * Return whether this block is a plain HTML block that does not contain/allow PHP code.
     *
     * @return bool
     */
    public function isHtmlBlock(): bool
    {
        return (! $this->isPhpBlock());
    }

    /**
     * The wrapper element to be used in the pagebuilder and for carrying style in case this block is a PHP block.
     */
    public function getWrapperElement(): string
    {
        $wrapper = $this->config['wrapper'] ?? 'div';
        return is_string($wrapper) ? $wrapper : 'div';
    }

    /**
     * Return configuration with the given key (as dot-separated multidimensional array selector).
     *
     * @param string|null $key
     * @return mixed
     */
    public function get(?string $key = null): mixed
    {
        if (empty($key)) {
            return $this->config;
        }
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
     * Replace configuration at the given key (as dot-separated multidimensional array selector) by the given value.
     *
     * @param string $slug
     * @param string|null $key
     * @param mixed $value
     * @return void
     */
    public static function set(string $slug, ?string $key = null, mixed $value = null): void
    {
        if (empty($key)) {
            self::$dynamicConfig[$slug] = $value;
            return;
        }
        $segments = explode('.', $key);
        $current = self::$dynamicConfig[$slug] ?? [];
        self::$dynamicConfig[$slug] = self::withNestedValue(
            is_array($current) ? self::stringKeyedArray($current) : [],
            $segments,
            $value
        );
    }

    /**
     * Get all dynamic configuration of the block with the given slug.
     *
     * @param string $slug
     * @return mixed
     */
    public static function getDynamicConfig(string $slug): mixed
    {
        return self::$dynamicConfig[$slug] ?? null;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $segments
     * @return array<string, mixed>
     */
    private static function withNestedValue(array $data, array $segments, mixed $value): array
    {
        $segment = array_shift($segments);
        if ($segment === null) {
            return $data;
        }
        if ($segments === []) {
            $data[$segment] = $value;
            return $data;
        }
        $child = $data[$segment] ?? [];
        $data[$segment] = self::withNestedValue(
            is_array($child) ? self::stringKeyedArray($child) : [],
            $segments,
            $value
        );
        return $data;
    }

    /**
     * @param array<mixed> $data
     * @return array<string, mixed>
     */
    private static function stringKeyedArray(array $data): array
    {
        return array_filter($data, 'is_string', ARRAY_FILTER_USE_KEY);
    }
}
