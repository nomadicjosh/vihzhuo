<?php

namespace Vihzhuo;

use Vihzhuo\Contracts\ThemeContract;
use Vihzhuo\Modules\GrapesJS\Block\BaseController;
use Vihzhuo\Modules\GrapesJS\Block\BaseModel;
use Vihzhuo\Modules\GrapesJS\PageRenderer;

class ThemeBlock
{
    /**
     * @var array $config
     */
    protected array $config = [];

    /**
     * @var array $dynamicConfig
     */
    public static array $dynamicConfig = [];

    /**
     * @var ?ThemeContract $theme
     */
    protected ?ThemeContract $theme = null;

    /**
     * @var string $blockSlug
     */
    protected string $blockSlug;

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
     * Theme constructor.
     *
     * @param ThemeContract $theme         the theme this block belongs to
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
        if (file_exists($this->getFolder() . '/config.php')) {
            $this->config = require $this->getFolder() . '/config.php';
        }

        PageRenderer::setCanBeCached(
            (bool) ($this->config['cache'] ?? true),
            $this->config['cache_lifetime'] ?? null
        );
    }

    /**
     * Return the absolute folder path of this theme block.
     *
     * @return string
     */
    public function getFolder(): string
    {
        if ($this->isExtension) {
            return $this->blockSlug;
        }
        $folder = $this->theme->getFolder() . '/blocks/archived/' . basename($this->blockSlug);
        if (file_exists($folder)) {
            return $folder;
        }
        $folder = $this->theme->getFolder() . '/blocks/elements/' . basename($this->blockSlug);
        if (file_exists($folder)) {
            return $folder;
        }
        $folder = $this->theme->getFolder() . '/blocks/php/' . basename($this->blockSlug);
        if (file_exists($folder)) {
            return $folder;
        }
        return $this->theme->getFolder() . '/blocks/' . basename($this->blockSlug);
    }

    /**
     * Return the namespace to the folder of this theme block.
     *
     * @return string
     */
    protected function getNamespace(): string
    {
        // return Namespace from the Config file of the Block if it is an extension. Used for Extensions.
        if (isset( $this->config['namespace'])) {
            return $this->config['namespace'];
        }

        // return Namespace from Config file if exists;
        if (phpb_config('theme.namespace')) {
            return phpb_config('theme.namespace');
        }

        // get namespace from directory structure if not provided:
        $themesPath = phpb_config('theme.folder');
        $themesFolderName = basename($themesPath);
        $blockFolder = $this->getFolder();
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
        if (file_exists($this->getFolder() . '/controller.php')) {
            return $this->getNamespace() . '\\Controller';
        }
        return BaseController::class;
    }

    /**
     * Return the controller file of this theme block.
     *
     * @return string|null
     */
    public function getControllerFile(): ?string
    {
        if (file_exists($this->getFolder() . '/controller.php')) {
            return $this->getFolder() . '/controller.php';
        }
        return null;
    }

    /**
     * Return the model class of this theme block.
     *
     * @return string
     */
    public function getModelClass(): string
    {
        if (file_exists($this->getFolder() . '/model.php')) {
            return $this->getNamespace() . '\\Model';
        }
        return BaseModel::class;
    }

    /**
     * Return the model file of this theme block.
     *
     * @return string|null
     */
    public function getModelFile(): ?string
    {
        if (file_exists($this->getFolder() . '/model.php')) {
            return $this->getFolder() . '/model.php';
        }
        return null;
    }

    /**
     * Return the view file of this theme block.
     *
     * @return string
     */
    public function getViewFile(): string
    {
        if ($this->isPhpBlock()) {
            return $this->getFolder() . '/view.php';
        }
        return $this->getFolder() . '/view.html';
    }

    /**
     * Return the pagebuilder script file of this theme block.
     * This script can be used to assist correct rendering of the block in the pagebuilder.
     *
     * @return string|null
     */
    public function getBuilderScriptFile(): ?string
    {
        if (file_exists($this->getFolder() . '/builder-script.php')) {
            return $this->getFolder() . '/builder-script.php';
        } elseif (file_exists($this->getFolder() . '/builder-script.html')) {
            return $this->getFolder() . '/builder-script.html';
        } elseif (file_exists($this->getFolder() . '/builder-script.js')) {
            return $this->getFolder() . '/builder-script.js';
        }
        return $this->getScriptFile();
    }

    /**
     * Return the script file of this theme block.
     * This script can be used to assist correct rendering of the block when used on a publicly accessed web page.
     *
     * @return string|null
     */
    public function getScriptFile(): ?string
    {
        if (file_exists($this->getFolder() . '/script.php')) {
            return $this->getFolder() . '/script.php';
        } elseif (file_exists($this->getFolder() . '/script.html')) {
            return $this->getFolder() . '/script.html';
        } elseif (file_exists($this->getFolder() . '/script.js')) {
            return $this->getFolder() . '/script.js';
        }
        return null;
    }

    /**
     * Return the file path of the thumbnail of this block.
     *
     * @return string
     */
    public function getThumbPath(): string
    {
        $blockThumbsFolder = $this->theme->getFolder() . '/public/block-thumbs/';
        return $blockThumbsFolder . md5($this->blockSlug) . '/' . md5(file_get_contents($this->getViewFile())) . '.jpg';
    }

    public function getThumbUrl(): string
    {
        return phpb_theme_asset('block-thumbs/' . md5($this->blockSlug) . '/' . md5(file_get_contents($this->getViewFile())) . '.jpg');
    }

    /**
     * Return the slug identifying this type of block.
     *
     * @return bool|string|null
     */
    public function getSlug(): bool|string|null
    {
        return ($this->isExtension) ? $this->extensionSlug : $this->blockSlug;
    }

    /**
     * Return whether this block is a block containing/allowing PHP code.
     *
     * @return bool
     */
    public function isPhpBlock(): bool
    {
        return file_exists($this->getFolder() . '/view.php');
    }

    /**
     * Return whether this block is a plain html block that does not contain/allow PHP code.
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
    public function getWrapperElement()
    {
        return $this->config['wrapper'] ?? 'div';
    }

    /**
     * Return configuration with the given key (as dot-separated multidimensional array selector).
     *
     * @param $key
     * @return mixed
     */
    public function get($key = null): mixed
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

    /**
     * Replace configuration at the given key (as dot-separated multidimensional array selector) by the given value.
     *
     * @param $slug
     * @param $key
     * @param $value
     * @return void
     */
    public static function set($slug, $key, $value): void
    {
        if (empty($key)) {
            self::$dynamicConfig[$slug] = $value;
            return;
        }
        // if no dot notation is used, replace first dimension value or empty string
        if (!str_contains($key, '.')) {
            self::$dynamicConfig[$slug][$key] = $value;
            return;
        }

        // if dot notation is used, traverse config and replace at the right depth
        $segments = explode('.', $key);
        $subArray = &self::$dynamicConfig[$slug];
        foreach ($segments as $i => $segment) {
            if (isset($subArray[$segment])) {
                if ($i === count($segments) - 1) {
                    $subArray[$segment] = $value;
                } else {
                    $subArray = &$subArray[$segment];
                }
            } else {
                return;
            }
        }
    }

    /**
     * Get all dynamic configuration of the block with the given slug.
     *
     * @param $slug
     * @return mixed
     */
    public static function getDynamicConfig($slug): mixed
    {
        return self::$dynamicConfig[$slug] ?? null;
    }
}
