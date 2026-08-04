<?php

declare(strict_types=1);

namespace Vihzhuo\Modules\GrapesJS\Block;

use Vihzhuo\Contracts\PageContract;
use Vihzhuo\ThemeBlock;

class BaseModel
{
    protected ?ThemeBlock $block = null;

    /**
     * @var array<string, mixed>
     */
    protected array $data;

    protected ?PageContract $page = null;

    protected bool $forPageBuilder;

    protected bool $doNotRender;

    protected bool $hasSkeleton;

    protected bool $hasDynamicSkeleton;

    /**
     * BaseModel constructor.
     *
     * @param ThemeBlock $block
     * @param array<string, mixed> $data
     * @param PageContract|null $page
     * @param bool $forPageBuilder
     */
    public function __construct(
        ThemeBlock $block,
        array $data = [],
        ?PageContract $page = null,
        bool $forPageBuilder = false
    ) {
        $this->block = $block;
        $this->data = array_filter($data, 'is_string', ARRAY_FILTER_USE_KEY);
        $this->page = $page;
        $this->forPageBuilder = $forPageBuilder;

        if (phpb_in_editmode() && method_exists($this, 'initEdit')) {
            $this->initEdit();
        } else {
            $this->init();
        }
    }

    /**
     * Initialize the model.
     */
    protected function init(): void
    {
    }

    /**
     * Return the given setting stored for this block instance using the page builder.
     *
     * @param string $setting
     * @param bool $allowHtml
     * @return string
     */
    public function setting(string $setting, bool $allowHtml = false): string
    {
        $value = $this->block->get('settings.' . $setting . '.value');
        $settings = $this->data['settings'] ?? [];
        $attributes = is_array($settings) ? ($settings['attributes'] ?? []) : [];
        if (is_array($attributes) && isset($attributes[$setting])) {
            $value = $attributes[$setting];
        }
        $stringValue = is_scalar($value) ? (string) $value : '';
        return $allowHtml ? $stringValue : phpb_e($stringValue);
    }

    /**
     * Return data of this block, passed as argument by a parent block.
     *
     * @param string $key
     * @return mixed
     */
    public function data(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    /**
     * Return data of the child block with the given relative ID.
     *
     * @param string $childBlockId
     * @return mixed
     */
    public function childData(string $childBlockId): mixed
    {
        $blocks = $this->data['blocks'] ?? [];
        return is_array($blocks) ? ($blocks[$childBlockId] ?? null) : null;
    }

    /**
     * Whether this page is rendered on the webpage.
     *
     * @return bool
     */
    public function doNotRender(): bool
    {
        return $this->doNotRender ?? false;
    }

    /**
     * Whether this block has skeleton loading.
     *
     * @return bool
     */
    public function hasSkeleton(): bool
    {
        return $this->hasSkeleton ?? false;
    }

    /**
     * Whether this block has dynamic skeleton loading (i.e. partially rendered, but needs to be replaced).
     *
     * @return bool
     */
    public function hasDynamicSkeleton(): bool
    {
        return $this->hasDynamicSkeleton ?? false;
    }
}
