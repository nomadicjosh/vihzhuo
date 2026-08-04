<?php

declare(strict_types=1);

namespace Vihzhuo\Repositories;

use ReflectionException;
use Vihzhuo\Contracts\PageTranslationRepositoryContract;
use Vihzhuo\Contracts\PageTranslationContract;

/** @extends BaseRepository<PageTranslationContract> */
class PageTranslationRepository extends BaseRepository implements PageTranslationRepositoryContract
{
    /**
     * The page translations database table.
     *
     * @var string
     */
    protected string $table;

    /**
     * The class that represents each page translation.
     *
     * @var class-string<PageTranslationContract>
     */
    protected string $class;

    /**
     * PageTranslationRepository constructor.
     */
    public function __construct(?string $table = null)
    {
        $configuredTable = phpb_config('page.translation.table');
        $this->table = $table ?? (is_string($configuredTable) && $configuredTable !== '' ? $configuredTable : 'page_translations');
        parent::__construct();
        $translationClass = phpb_static('page.translation');
        if ($translationClass === null || !is_a($translationClass, PageTranslationContract::class, true)) {
            throw new \LogicException('Configured translation class must implement PageTranslationContract.');
        }
        $this->class = $translationClass;
    }

    /**
     * @param array<string, scalar|null> $data
     * @throws ReflectionException
     */
    public function create(array $data): ?PageTranslationContract
    {
        $record = $this->createRecord($data);
        return $record instanceof PageTranslationContract ? $record : null;
    }
}
