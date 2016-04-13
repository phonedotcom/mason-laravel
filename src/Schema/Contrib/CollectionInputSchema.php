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
            ])
            ->setDefinition('sorting', JsonSchema::make([
                'type' => 'string',
                'enum' => ['asc', 'desc']
            ]))
            ->setDefinition('filtering', JsonSchema::make([
                'oneOf' => [
                    JsonSchema::make([
                        'type' => 'array',
                        'items' => JsonSchema::make([
                            'oneOf' => [
                                '#/definitions/filter-empty',
                                '#/definitions/filter-not-empty',
                                '#/definitions/filter-equals',
                                '#/definitions/filter-not-equals',
                                '#/definitions/filter-less-than',
                                '#/definitions/filter-greater-than',
                                '#/definitions/filter-less-than-or-equals',
                                '#/definitions/filter-greater-than-or-equals',
                                '#/definitions/filter-starts-with',
                                '#/definitions/filter-not-starts-with',
                                '#/definitions/filter-ends-with',
                                '#/definitions/filter-ends-with',
                                '#/definitions/filter-contains',
                                '#/definitions/filter-not-contains',
                                '#/definitions/filter-between',
                                '#/definitions/filter-not-between',
                            ]
                        ])
                    ]),
                    '#/definitions/filter-empty',
                    '#/definitions/filter-not-empty',
                    '#/definitions/filter-equals',
                    '#/definitions/filter-not-equals',
                    '#/definitions/filter-less-than',
                    '#/definitions/filter-greater-than',
                    '#/definitions/filter-less-than-or-equals',
                    '#/definitions/filter-greater-than-or-equals',
                    '#/definitions/filter-starts-with',
                    '#/definitions/filter-not-starts-with',
                    '#/definitions/filter-ends-with',
                    '#/definitions/filter-ends-with',
                    '#/definitions/filter-contains',
                    '#/definitions/filter-not-contains',
                    '#/definitions/filter-between',
                    '#/definitions/filter-not-between',
                ]
            ]))            
            ->setDefinition('filter-empty', JsonSchema::make([
                'type' => 'string',
                'description' => 'Include records where the field is an empty string, 0, or null',
                'pattern' => '^empty$'
            ]))
            ->setDefinition('filter-not-empty', JsonSchema::make([
                'type' => 'string',
                'description' => 'Include records where the field is neither an empty string, nor 0, nor null',
                'pattern' => '^not-empty$'
            ]))
            ->setDefinition('filter-equals', JsonSchema::make([
                'type' => 'string',
                'description' => 'Include records where the field equals the value after the colon',
                'pattern' => '^eq:.*'
            ]))
            ->setDefinition('filter-not-equals', JsonSchema::make([
                'type' => 'string',
                'description' => 'Include records where the field does not equal the value after the colon',
                'pattern' => '^ne:.*'
            ]))
            ->setDefinition('filter-less-than', JsonSchema::make([
                'type' => 'number',
                'description' => 'Include records where the field is less than the given value.',
                'pattern' => '^lt:.*'
            ]))
            ->setDefinition('filter-greater-than', JsonSchema::make([
                'type' => 'number',
                'description' => 'Include records where the field is greater than the given value.',
                'pattern' => '^gt:.*'
            ]))
            ->setDefinition('filter-less-than-or-equals', JsonSchema::make([
                'type' => 'number',
                'description' => 'Include records where the field is less than or equal to the given value.',
                'pattern' => '^lte:.*'
            ]))
            ->setDefinition('filter-greater-than-or-equals', JsonSchema::make([
                'type' => 'number',
                'description' => 'Include records where the field is greater than or equal to the given value.',
                'pattern' => '^gte:.*'
            ]))
            ->setDefinition('filter-starts-with', JsonSchema::make([
                'type' => 'string',
                'description' => 'Include records where the field begins with the given value.',
                'pattern' => '^starts-with:.*'
            ]))
            ->setDefinition('filter-ends-with', JsonSchema::make([
                'type' => 'string',
                'description' => 'Include records where the field ends with the given value.',
                'pattern' => '^ends-with:.*'
            ]))
            ->setDefinition('filter-contains', JsonSchema::make([
                'type' => 'string',
                'description' => 'Include records where the field contains the given value.',
                'pattern' => '^contains:.*'
            ]))
            ->setDefinition('filter-not-starts-with', JsonSchema::make([
                'type' => 'string',
                'description' => 'Include records where the field does not begin with the given value.',
                'pattern' => '^not-starts-with:.*'
            ]))
            ->setDefinition('filter-not-ends-with', JsonSchema::make([
                'type' => 'string',
                'description' => 'Include records where the field does not end with the given value.',
                'pattern' => '^not-ends-with:.*'
            ]))
            ->setDefinition('filter-not-contains', JsonSchema::make([
                'type' => 'string',
                'description' => 'Include records where the field does not contain the given value.',
                'pattern' => '^not-contains:.*'
            ]))
            ->setDefinition('filter-between', JsonSchema::make([
                'type' => 'string',
                'description' => 'Include records where the field is between the given values. '
                    . 'Must be a comma-separated list with two items.',
                'pattern' => '^between:.*,.*'
            ]))
            ->setDefinition('filter-not-between', JsonSchema::make([
                'type' => 'string',
                'description' => 'Include records where the field is outside of the given range. '
                    . 'Must be a comma-separated list with two items.',
                'pattern' => '^not-between:.*,.*'
            ]));
    }
}
