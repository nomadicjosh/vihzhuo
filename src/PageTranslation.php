<?php

namespace Vihzhuo;

use stdClass;
use Vihzhuo\Contracts\PageTranslationContract;
use Vihzhuo\Repositories\PageRepository;

use function phpb_config;

class PageTranslation extends stdClass implements PageTranslationContract
{
    /**
     * Return the page this translation belongs to.
     *
     * @return object|null
     */
    public function getPage(): ?object
    {
        $foreignKey = phpb_config('page.translation.foreign_key');
        return new PageRepository()->findWithId($this->{$foreignKey});
    }

    /**
     * Return pages for navigation.
     *
     * @return array
     */
    public function getPages(): array
    {
        $foreignKey = phpb_config('page.translation.foreign_key');
        return new PageRepository()->findAllPages($foreignKey);
    }
}
