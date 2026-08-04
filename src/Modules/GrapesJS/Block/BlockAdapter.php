<?php

declare(strict_types=1);

namespace Vihzhuo\Modules\GrapesJS\Block;

use Vihzhuo\Modules\GrapesJS\PageRenderer;
use Vihzhuo\ThemeBlock;
use Exception;

/**
 * Class BlockAdapter
 *
 * Class for adapting a ThemeBlock into a JSON object understood by the GrapesJS page builder.
 *
 * @package Vihzhuo\GrapesJS
 */
class BlockAdapter
{
    protected ?PageRenderer $pageRenderer = null;

    protected ?ThemeBlock $block = null;

    /**
     * BlockAdapter constructor.
     *
     * @param PageRenderer $pageRenderer
     * @param ThemeBlock $block
     */
    public function __construct(PageRenderer $pageRenderer, ThemeBlock $block)
    {
        $this->pageRenderer = $pageRenderer;
        $this->block = $block;
    }

    /**
     * Return the slug identifying this type of theme block.
     *
     * @return string
     */
    public function getSlug(): string
    {
        return $this->block->getSlug();
    }

    /**
     * Return the visible title of this block.
     *
     * @return string
     */
    public function getTitle(): string
    {
        $title = $this->block->get('title');
        if (is_string($title) && $title !== '') {
            return $title;
        }
        return str_replace('-', ' ', ucfirst($this->getSlug()));
    }

    /**
     * Return the category this block belongs to.
     *
     * @return string|null
     */
    public function getCategory(): ?string
    {
        $category = $this->block->get('category');
        if (is_string($category) && $category !== '') {
            return $category;
        }
        $translated = phpb_trans('pagebuilder.default-category');
        return is_string($translated) ? $translated : null;
    }

    /**
     * Return an array representation of the theme block, for adding as a block to GrapesJS.
     *
     * @return array<string, mixed>
     * @throws Exception
     */
    public function getBlockManagerArray(): array
    {
        $content = $this->pageRenderer->renderBlock($this->block->getSlug());

        $img = '';
        if (file_exists($this->block->getThumbPath())) {
            $img = '<div class="block-thumb" style="background-image: url(' . phpb_full_url($this->block->getThumbUrl()) . '); background-size: cover"></div>';
        }

        $data = [
            'label' => $img . $this->getTitle(),
            'category' => $this->getCategory(),
            'content' => $content
        ];

        if (empty($img)) {
            $iconClass = 'fa fa-bars';
            $icon = $this->block->get('icon');
            if (is_string($icon) && $icon !== '') {
                $iconClass = $icon;
            }
            $data['attributes'] = ['class' => $iconClass];
        }

        return $data;
    }

    /**
     * Return the array of settings of the theme block, for populating the settings tab in the GrapesJS sidebar.
     *
     * @return list<array<string, mixed>>
     */
    public function getBlockSettingsArray(): array
    {
        $dynamic = $this->block::getDynamicConfig($this->getSlug());
        $blockSettings = is_array($dynamic) ? ($dynamic['settings'] ?? null) : null;
        $blockSettings ??= $this->block->get('settings');
        if ($this->block->isHtmlBlock() || ! is_array($blockSettings)) {
            return [];
        }

        $settings = [];
        foreach ($blockSettings as $name => $blockSetting) {
            if (!is_string($name) || !is_array($blockSetting) || !isset($blockSetting['label'])) {
                continue;
            }
            $type = is_string($blockSetting['type'] ?? null) ? $blockSetting['type'] : 'text';

            $setting = [
                'type' => $type,
                'name' => $name,
                'label' => $blockSetting['label'],
                'default-value' => $blockSetting['value'] ?? '',
                'placeholder' => $blockSetting['placeholder'] ?? '',
            ];

            if ($type === 'select') {
                $setting['options'] = $blockSetting['options'] ?? [];
            } elseif ($type === 'yes_no') {
                $setting['type'] = 'select';
                $setting['options'] = [
                    ['id' => 0, 'name' => phpb_trans('pagebuilder.no')],
                    ['id' => 1, 'name' => phpb_trans('pagebuilder.yes')]
                ];
            }

            $settings[] = $setting;
        }

        return $settings;
    }
}
