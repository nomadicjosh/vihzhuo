<?php

namespace Vihzhuo\Modules\GrapesJS\Block;

use Vihzhuo\Contracts\PageContract;
use Vihzhuo\ThemeBlock;

class BaseModel
{
    /**
     * @var ?ThemeBlock $block
     */
    protected ?ThemeBlock $block = null;

    /**
     * @var array $data
     */
    protected array $data;

    /**
     * @var ?PageContract $page
     */
    protected ?PageContract $page = null;

    /**
     * @var bool $forPageBuilder
     */
    protected bool $forPageBuilder;

    /**
     * @var bool $doNotRender
     */
    protected bool $doNotRender;

    /**
     * @var bool $hasSkeleton
     */
    protected bool $hasSkeleton;

    /**
     * @var bool $hasDynamicSkeleton
     */
    protected bool $hasDynamicSkeleton;

    /**
     * BaseModel constructor.
     *
     * @param ThemeBlock $block
     * @param array $data
     * @param PageContract|null $page
     * @param bool $forPageBuilder
     */
    public function __construct(ThemeBlock $block, array $data = [], PageContract $page = null, bool $forPageBuilder = false)
    {
        $this->block = $block;
        $this->data = is_array($data) ? $data : [];
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
    protected function init()
    {
    }

    /**
     * Return the given setting stored for this block instance using the page builder.
     *
     * @param $setting
     * @param bool $allowHtml
     * @return string
     */
    public function setting(mixed $setting, bool $allowHtml = false): string
    {
        $value = $this->block->get('settings.' . $setting . '.value');

        if (isset($this->data['settings']['attributes'][$setting])) {
            $value = $this->data['settings']['attributes'][$setting];
        }

        return $allowHtml ? $value : phpb_e($value);
    }

    /**
     * Return data of this block, passed as argument by a parent block.
     *
     * @param $key
     * @return string|null
     */
    public function data(mixed $key): ?string
    {
        return $this->data[$key] ?? null;
    }

    /**
     * Return data of the child block with the given relative ID.
     *
     * @param mixed $childBlockId
     * @return string|null
     */
    public function childData(mixed $childBlockId): ?string
    {
        return $this->data['blocks'][$childBlockId] ?? null;
    }

    /**
     * Whether this page is rendered on the webpage.
     *
     * @return false
     */
    public function doNotRender(): bool
    {
        return $this->doNotRender ?? false;
    }

    /**
     * Whether this block has skeleton loading.
     *
     * @return false
     */
    public function hasSkeleton(): bool
    {
        return $this->hasSkeleton ?? false;
    }

    /**
     * Whether this block has dynamic skeleton loading (i.e. partially rendered, but needs to be replaced).
     *
     * @return false
     */
    public function hasDynamicSkeleton(): bool
    {
        return $this->hasDynamicSkeleton ?? false;
    }

}
