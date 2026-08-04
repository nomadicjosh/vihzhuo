<?php

declare(strict_types=1);

namespace Vihzhuo\Repositories;

use JsonException;
use ReflectionException;
use Vihzhuo\Contracts\PageContract;
use Vihzhuo\Contracts\PageRepositoryContract;
use Exception;

use function phpb_config;

/** @extends BaseRepository<PageContract> */
class PageRepository extends BaseRepository implements PageRepositoryContract
{
    /**
     * The pages database table.
     *
     * @var string
     */
    protected string $table;

    /**
     * The class that represents each page.
     *
     * @var class-string<PageContract>
     */
    protected string $class;

    /**
     * PageRepository constructor.
     *
     * @throws Exception
     */
    public function __construct()
    {
        $configuredTable = phpb_config('page.table');
        $this->table = is_string($configuredTable) && $configuredTable !== '' ? $configuredTable : 'pages';
        parent::__construct();
        $pageClass = phpb_static('page');
        if ($pageClass === null || !is_a($pageClass, PageContract::class, true)) {
            throw new Exception('Configured page class must implement PageContract.');
        }
        $this->class = $pageClass;
    }

    /**
     * Create a new page.
     *
     * @param array<string, mixed> $data
     * @return PageContract|false
     * @throws Exception
     */
    public function create(array $data): PageContract|false
    {
        if (
            array_any(
                ['name', 'layout', 'show_in_nav', 'nav_position', 'nav_type'],
                fn($field) => !isset($data[$field]) || !is_string($data[$field])
            )
        ) {
            return false;
        }

        $page = $this->createRecord([
            'name' => $data['name'],
            'layout' => $data['layout'],
            'show_in_nav' => $data['show_in_nav'],
            'nav_position' => $data['nav_position'],
            'nav_type' => $data['nav_type'],
        ]);
        if (! ($page instanceof PageContract)) {
            throw new Exception("Page not of type PageContract");
        }
        return $this->replaceTranslations($page, $data) ? $page : false;
    }

    /**
     * Update the given page with the given updated data.
     *
     * @param PageContract $page
     * @param array<string, mixed> $data
     * @return bool
     */
    public function update(PageContract $page, array $data): bool
    {
        if (
            array_any(
                ['name', 'layout', 'show_in_nav', 'nav_position', 'nav_type'],
                fn($field) => !isset($data[$field]) || !is_string($data[$field])
            )
        ) {
            return false;
        }

        $this->replaceTranslations($page, $data);

        $updateResult = $this->updateRecord($page, [
            'name' => $data['name'],
            'layout' => $data['layout'],
            'show_in_nav' => $data['show_in_nav'],
            'nav_position' => $data['nav_position'],
            'nav_type' => $data['nav_type'],
        ]);
        $page->invalidateCache();
        return $updateResult;
    }

    /**
     * Replace the translations of the given page by the given data.
     *
     * @param PageContract $page
     * @param array<string, mixed> $data
     * @return bool
     */
    protected function replaceTranslations(PageContract $page, array $data): bool
    {
        $activeLanguages = phpb_active_languages();
        foreach (['title', 'meta_title', 'meta_description', 'route'] as $field) {
            $translations = $data[$field] ?? null;
            if (!is_array($translations)) {
                return false;
            }
            if (
                array_any(
                    $activeLanguages,
                    fn($languageTranslation, $languageCode) => !is_string($translations[$languageCode] ?? null)
                )
            ) {
                return false;
            }
        }

        $pageTranslationRepository = new PageTranslationRepository();
        $configuredForeignKey = phpb_config('page.translation.foreign_key');
        $foreignKey = is_string($configuredForeignKey) ? $configuredForeignKey : 'page_id';
        $pageTranslationRepository->destroyWhere($foreignKey, $page->getId());
        foreach ($activeLanguages as $languageCode => $languageTranslation) {
            $title = $data['title'];
            $metaTitle = $data['meta_title'];
            $metaDescription = $data['meta_description'];
            $route = $data['route'];
            if (!is_array($title) || !is_array($metaTitle) || !is_array($metaDescription) || !is_array($route)) {
                return false;
            }
            $pageTranslationRepository->create([
                $foreignKey => $page->getId(),
                'locale' => $languageCode,
                'title' => is_string($title[$languageCode] ?? null) ? $title[$languageCode] : '',
                'meta_title' => is_string($metaTitle[$languageCode] ?? null) ? $metaTitle[$languageCode] : '',
                'meta_description' => is_string($metaDescription[$languageCode] ?? null) ? $metaDescription[$languageCode] : '',
                'route' => is_string($route[$languageCode] ?? null) ? $route[$languageCode] : '',
            ]);
        }

        return true;
    }

    /**
     * Update the given page with the given updated page data.
     *
     * @param PageContract $page
     * @param array<string, mixed> $data
     * @return bool
     * @throws JsonException
     */
    public function updatePageData(PageContract $page, array $data): bool
    {
        $updateResult = $this->updateRecord($page, [
            'data' => json_encode($data, JSON_THROW_ON_ERROR),
        ]);
        $page->invalidateCache();
        return $updateResult;
    }

    /**
     * Remove the given page from the database.
     *
     * @param int|string $id
     * @return bool
     * @throws ReflectionException
     */
    public function destroy(int|string $id): bool
    {
        $page = $this->findWithId($id);
        if ($page instanceof PageContract) {
            $page->invalidateCache();
        }

        return parent::destroy($id);
    }

    /**
     * Return translations and their pages.
     *
     * @param string $id
     * @return list<array<string, mixed>>
     */
    public function findAllPages(string $id): array
    {
        $configuredPrefix = phpb_config('storage.database.prefix');
        $prefix = is_string($configuredPrefix) ? preg_replace('/\W/', '', $configuredPrefix) : '';
        $prefix = is_string($prefix) ? $prefix : '';
        $foreignKey = preg_replace('/\W/', '', $id) ?: 'page_id';

        $query = $this->db->rawQuery(
            query: "SELECT DISTINCT pages.id, pages.show_in_nav, pages.nav_position, pages.nav_type, trans.title, " .
            "trans.route FROM {$prefix}pages AS pages JOIN {$prefix}page_translations AS trans ON pages.id = trans.{$foreignKey}"
        );

        return $query;
    }
}
