<?php
namespace PhoneCom\Mason\Schema\Contrib;

use PhoneCom\Mason\Schema\DocumentSchema;
use PhoneCom\Mason\Builder\Contrib\MasonCollection;

class MasonCollectionSchema extends DocumentSchema
{
    public function __construct($properties = [])
    {
        parent::__construct($properties);

        $this
            ->setRequiredProperty('page', 'integer', ['title' => 'Page number', 'default' => 1])
            ->setRequiredProperty('page_size', 'integer', [
                'title' => 'Maximum number of items per page',
                'default' => MasonCollection::DEFAULT_PER_PAGE,
                'maximum' => MasonCollection::MAX_PER_PAGE
            ])
            ->setRequiredProperty('total_pages', 'integer', [
                'title' => 'Total number of pages in the collection, if using the current page_size'
            ])
            ->setRequiredProperty('total_items', 'integer', ['title' => 'Total number of items in the collection']);
    }
}
