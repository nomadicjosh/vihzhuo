<?php

declare(strict_types=1);

namespace Vihzhuo\Modules\GrapesJS\Block;

use Random\RandomException;
use Vihzhuo\Contracts\PageContract;
use Vihzhuo\Contracts\ThemeContract;
use Vihzhuo\Extensions;
use Vihzhuo\ThemeBlock;
use Vihzhuo\Core\View;

class BlockRenderer
{
    protected ?ThemeContract $theme = null;

    protected ?PageContract $page = null;

    protected bool $forPageBuilder;

    /**
     * BlockRenderer constructor.
     *
     * @param ThemeContract $theme
     * @param PageContract $page
     * @param bool $forPageBuilder
     */
    public function __construct(ThemeContract $theme, PageContract $page, bool $forPageBuilder = false)
    {
        $this->theme = $theme;
        $this->page = $page;
        $this->forPageBuilder = $forPageBuilder;
    }

    /**
     * Change this BlockRenderer to render not editable blocks.
     *
     * @return $this
     */
    public function notEditable(): static
    {
        $this->forPageBuilder = false;
        return $this;
    }

    /**
     * Change this BlockRenderer to render editable blocks.
     *
     * @return $this
     */
    public function editable(): static
    {
        $this->forPageBuilder = true;
        return $this;
    }

    /**
     * Render a theme block with the given slug using the given block data.
     *
     * @param string $blockSlug
     * @param array<string, mixed>|null $blockData
     * @param string|null $id id of the specific block instance
     * @return string
     * @throws RandomException
     */
    public function renderWithSlug(string $blockSlug, ?array $blockData = null, ?string $id = null): string
    {
        $block = ($path = Extensions::getBlock($blockSlug))
        ? new ThemeBlock($this->theme, $path, true, $blockSlug)
        : new ThemeBlock($this->theme, $blockSlug);

        return $this->render($block, $blockData, $id);
    }

    /**
     * Render the given theme block with the given stored block data.
     *
     * @param ThemeBlock $themeBlock
     * @param array<string, mixed>|null $blockData
     * @param string|null $id id of the specific block instance
     * @return string
     * @throws RandomException
     */
    public function render(ThemeBlock $themeBlock, ?array $blockData = null, ?string $id = null): string
    {
        $blockData = $blockData ?? [];

        if ($themeBlock->isHtmlBlock()) {
            $html = $this->renderHtmlBlock($themeBlock, $blockData);
        } else {
            $html = $this->renderDynamicBlock($themeBlock, $blockData);
        }

        $wrapperElement = $themeBlock->getWrapperElement();
        if ($this->forPageBuilder) {
            $id = $id ?? $themeBlock->getSlug();
            $html = '<phpb-block block-slug="' . phpb_e($themeBlock->getSlug()) . '" block-id="' . phpb_e($id) . '" wrapper="' . $wrapperElement . '" is-html="' . ($themeBlock->isHtmlBlock() ? 'true' : 'false') . '">'
            . $html . $this->renderBuilderScript($themeBlock)
            . '</phpb-block>';
        } elseif (!$themeBlock->isHtmlBlock() && ($styleIdentifier = $this->styleIdentifier($blockData)) !== null) {
            // add wrapper element around dynamic pagebuilder blocks, which receives the
            // style identifier class if additional styling is added to the block via the pagebuilder
            $html = '<' . $wrapperElement . ' class="' . phpb_e($styleIdentifier) . '">'
            . $html . $this->renderScript($themeBlock)
            . '</' . $wrapperElement . '>';
        } else {
            $html .= $this->renderScript($themeBlock);
        }
        return $html;
    }

    /**
     * Render the pagebuilder script of the given block.
     *
     * @param ThemeBlock $themeBlock
     * @return string
     * @throws RandomException
     */
    public function renderBuilderScript(ThemeBlock $themeBlock): string
    {
        $builderScriptFilePath = $themeBlock->getBuilderScriptFile();
        if ($builderScriptFilePath) {
            if (pathinfo($builderScriptFilePath, PATHINFO_EXTENSION) === 'php') {
                $scriptHtmlString = View::render($builderScriptFilePath, ['renderer' => $this]);
            } else {
                $scriptHtmlString = (string) file_get_contents($builderScriptFilePath);
            }
            return '<script>' . $this->removeWrappedScriptTags($scriptHtmlString) . '</script>';
        }
        // if no builder script was specified, fallback to using the general script (if provided)
        return $this->renderScript($themeBlock, true);
    }

    /**
     * Render the script of the given block for rendering the block on a publicly accessible web page.
     *
     * @param ThemeBlock $themeBlock
     * @param bool $forPageBuilder
     * @return string
     * @throws RandomException
     */
    public function renderScript(ThemeBlock $themeBlock, bool $forPageBuilder = false): string
    {
        $scriptFilePath = $themeBlock->getScriptFile();
        if ($scriptFilePath) {
            if (pathinfo($scriptFilePath, PATHINFO_EXTENSION) === 'php') {
                $scriptHtmlString = View::render($scriptFilePath, ['renderer' => $this]);
            } else {
                $scriptHtmlString = (string) file_get_contents($scriptFilePath);
            }

            $script = $this->removeWrappedScriptTags($scriptHtmlString);
            if ($forPageBuilder) {
                return '<script>' . $script . '</script>';
            }
            return $this->wrapScriptWithScopeAndContextData($script);
        }
        return '';
    }

    /**
     * Remove script tags wrapped around to given JavaScript string, if they are present.
     * This is necessary if the script is coming from a .html or .php file.
     *
     * @param string $scriptHtmlString
     * @return string
     */
    protected function removeWrappedScriptTags(string $scriptHtmlString): string
    {
        return str_replace('<script>', '', str_replace('</script>', '', $scriptHtmlString));
    }

    /**
     * Wrap the given JavaScript with a script tag that has a unique id,
     * add a scope around the script and add context data giving the script
     * access to the exact block instance in the DOM.
     *
     * @param string $script
     * @return string
     * @throws RandomException
     */
    protected function wrapScriptWithScopeAndContextData(string $script): string
    {
        $scriptId = 'script' . random_int(0, 10_000_000_000);
        $html = '<script type="text/javascript" class="' . $scriptId . '">';
        $html .= 'document.getElementsByClassName("' . $scriptId . '")[0].addEventListener("run-script", function() {';
        $html .= 'let inPageBuilder = false;';
        $html .= 'let block = document.getElementsByClassName("' . $scriptId . '")[0].previousSibling;';
        $html .= 'let blockSelector = "." + block.className;';
        $html .= $script;
        $html .= '});';
        return $html . '</script>';
    }

    /**
     * Render the given html theme block with the given stored block data.
     *
     * @param ThemeBlock $themeBlock
     * @param array<string, mixed> $blockData
     * @return string
     */
    protected function renderHtmlBlock(ThemeBlock $themeBlock, array $blockData): string
    {
        if ($themeBlock->getControllerFile()) {
            require_once $themeBlock->getControllerFile();
            $controllerClass = $themeBlock->getControllerClass();
            if (!is_a($controllerClass, BaseController::class, true)) {
                throw new \LogicException("Block controller {$controllerClass} must extend " . BaseController::class);
            }
            $controller = new $controllerClass();

            $model = new BaseModel($themeBlock, $blockData, $this->page, $this->forPageBuilder);
            $controller->init($model, $this->page, $this->forPageBuilder);
            $controller->handleRequest();
        }
        $html = $blockData['html'] ?? null;
        return is_string($html) ? $html : (string) file_get_contents($themeBlock->getViewFile());
    }

    /**
     * Render the given dynamic theme block with the given stored block data.
     *
     * @param ThemeBlock $themeBlock
     * @param array<string, mixed>|null $blockData
     * @return string
     */
    protected function renderDynamicBlock(ThemeBlock $themeBlock, ?array $blockData = null): string
    {
        $blockData = $blockData ?? [];
        $controller = new BaseController();
        $model = new BaseModel($themeBlock, $blockData, $this->page, $this->forPageBuilder);

        if ($themeBlock->getModelFile()) {
            require_once $themeBlock->getModelFile();
            $modelClass = $themeBlock->getModelClass();
            if (!is_a($modelClass, BaseModel::class, true)) {
                throw new \LogicException("Block model {$modelClass} must extend " . BaseModel::class);
            }
            $model = new $modelClass($themeBlock, $blockData, $this->page, $this->forPageBuilder);
            if ($model->doNotRender()) {
                return '';
            }
        }

        if ($themeBlock->getControllerFile()) {
            require_once $themeBlock->getControllerFile();
            $controllerClass = $themeBlock->getControllerClass();
            if (!is_a($controllerClass, BaseController::class, true)) {
                throw new \LogicException("Block controller {$controllerClass} must extend " . BaseController::class);
            }
            $controller = new $controllerClass();
        }
        $controller->init($model, $this->page, $this->forPageBuilder);
        $controller->handleRequest();

        // init additional variables that should be accessible in the view
        $renderer = $this;
        $page = $this->page;
        $block = $model;
        $hasSkeleton = $model->hasSkeleton();
        $hasDynamicSkeleton = $model->hasDynamicSkeleton();

        // unset variables that should be inaccessible inside the view
        unset($controller, $model, $blockData);

        $html = View::render($themeBlock->getViewFile(), compact(
            'renderer',
            'page',
            'block',
            'hasSkeleton',
            'hasDynamicSkeleton'
        ));

        if ($hasSkeleton) {
            $className = 'skeleton-' . $themeBlock->getSlug() . ' skeleton-data';
            if (phpb_is_skeleton_data_request()) {
                return "<span class='{$className}'>{$html}</span>";
            } elseif ($hasDynamicSkeleton) {
                return "<span class='{$className}'>{$html}</span>";
            } else {
                return "<span class='{$className}'></span>";
            }
        }

        return $html;
    }

    /** @param array<string, mixed> $blockData */
    private function styleIdentifier(array $blockData): ?string
    {
        $settings = $blockData['settings'] ?? null;
        $attributes = is_array($settings) ? ($settings['attributes'] ?? null) : null;
        $identifier = is_array($attributes) ? ($attributes['style-identifier'] ?? null) : null;
        return is_string($identifier) && $identifier !== '' ? $identifier : null;
    }
}
