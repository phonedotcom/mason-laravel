<?php
namespace PhoneCom\Mason\Schema\Contrib;

use PhoneCom\Mason\Schema\DocumentSchema;
use PhoneCom\Mason\Builder\Contrib\MasonCollection;
use Illuminate\Http\Request;

class MasonCollectionSchema extends DocumentSchema
{
    public function __construct(Request $request = null, $properties = [])
    {
        parent::__construct($request, $properties);

        $this
            ->setMetaProperty('page', 'integer', 'Page number', ['default' => 1])
            ->setMetaProperty('page_size', 'integer', 'Maximum number of items per page', [
                'default' => MasonCollection::DEFAULT_PER_PAGE,
                'maximum' => MasonCollection::MAX_PER_PAGE
            ])
            ->setMetaProperty(
                'total_pages',
                'integer',
                'Total number of pages in the collection, if using the current page_size'
            )
            ->setMetaProperty('total_items', 'integer', 'Total number of items in the collection');
    }
}
