<?php
namespace PhoneCom\Mason\Schema\Contrib;

use PhoneCom\Mason\Schema\JsonSchema;
use PhoneCom\Mason\Builder\Contrib\MasonCollection;

class CollectionInputSchema extends JsonSchema
{
    public function __construct($request = null, $properties = [])
    {
        parent::__construct($request, $properties);

        $this
            ->setOptionalProperty('page', 'integer', [
                'title' => 'Page number',
                'default' => 1
            ])
            ->setOptionalProperty('page_size', 'integer', [
                'title' => 'Maximum number of items per page',
                'default' => MasonCollection::DEFAULT_PER_PAGE,
                'maximum' => MasonCollection::MAX_PER_PAGE
            ]);
    }
}
