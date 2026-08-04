<?php

declare(strict_types=1);

namespace Vihzhuo;

use ReflectionException;
use stdClass;
use Vihzhuo\Contracts\PageContract;
use Vihzhuo\Contracts\DataRecordContract;
use Vihzhuo\Contracts\PageTranslationContract;
use Vihzhuo\Repositories\PageRepository;

use function phpb_config;

class PageTranslation extends stdClass implements PageTranslationContract, DataRecordContract
{
    public int|string $id;

    public int|string $page_id;

    public string $locale;

    public string $route;

    public string $title;

    public string $meta_title;

    public string $meta_description;

    /** @param array<string, mixed> $data */
    public function setData(array $data): void
    {
        foreach (['id', 'page_id'] as $key) {
            $value = $data[$key] ?? '';
            $this->{$key} = is_int($value) || is_string($value) ? $value : '';
        }
        foreach (['locale', 'route', 'title', 'meta_title', 'meta_description'] as $key) {
            $value = $data[$key] ?? '';
            $this->{$key} = is_string($value) ? $value : '';
        }
    }

    /**
     * Return the page this translation belongs to.
     *
     * @return PageContract|null
     * @throws ReflectionException
     */
    public function getPage(): ?PageContract
    {
        $foreignKey = phpb_config('page.translation.foreign_key');
        $foreignId = $this->{$foreignKey} ?? null;
        $page = new PageRepository()->findWithId(is_scalar($foreignId) ? (string) $foreignId : '');
        return $page instanceof PageContract ? $page : null;
    }

    public function getLocale(): string
    {
        return is_string($this->locale ?? null) ? $this->locale : '';
    }

    public function getId(): string
    {
        return (string) ($this->id ?? '');
    }

    public function getPageId(): string
    {
        return (string) ($this->page_id ?? '');
    }

    public function getRoute(): string
    {
        return is_string($this->route ?? null) ? $this->route : '';
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'page_id' => $this->page_id,
            'locale' => $this->locale,
            'route' => $this->route,
            'title' => $this->title,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
        ];
    }

    /**
     * Return pages for navigation.
     *
     * @return list<array<string, mixed>>
     */
    public function getPages(): array
    {
        $foreignKey = phpb_config('page.translation.foreign_key');
        return new PageRepository()->findAllPages(is_string($foreignKey) ? $foreignKey : 'page_id');
    }
}
