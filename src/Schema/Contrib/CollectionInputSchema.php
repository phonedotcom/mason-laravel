<?php
namespace Phonedotcom\Mason\Schema\Contrib;

use Phonedotcom\Mason\Schema\JsonSchema;
use Phonedotcom\Mason\Builder\Contrib\MasonCollection;

class CollectionInputSchema extends JsonSchema
{
    public function __construct($request = null, $properties = [])
    {
        parent::__construct($request, $properties);

        $this
            ->setProperty('offset', 'integer', [
                'title' => 'Number of records to skip in the result set',
                'default' => 0
            ])
            ->setProperty('limit', 'integer', [
                'title' => 'Maximum number of items to return',
                'default' => MasonCollection::DEFAULT_PER_PAGE,
                'maximum' => MasonCollection::MAX_PER_PAGE
            ]);
    }
}
