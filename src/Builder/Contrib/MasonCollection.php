<?php
namespace Phonedotcom\Mason\Builder\Contrib;

use Illuminate\Contracts\Validation\ValidationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Phonedotcom\Mason\Builder\Child;
use Phonedotcom\Mason\Builder\Contrib\MasonCollection\Container\CollectionContainer;
use Phonedotcom\Mason\Builder\Contrib\MasonCollection\Container\Container;
use Phonedotcom\Mason\Builder\Contrib\MasonCollection\Container\EloquentContainer;
use Phonedotcom\Mason\Builder\Contrib\MasonCollection\Filter;
use Phonedotcom\Mason\Builder\Contrib\MasonCollection\Sort;
use Phonedotcom\Mason\Builder\Document;
use Phonedotcom\Mason\Schema\Contrib\CollectionInputSchema;
use Phonedotcom\Mason\Schema\JsonSchema;

class MasonCollection extends Document
{
    private static $filterOperators = [
        // zero-argument operators
        0 => ['empty', 'not-empty'],

        // one-argument operators
        1 => [
            'eq', 'ne', 'lt', 'gt', 'lte', 'gte',
            'starts-with', 'ends-with', 'contains', 'not-starts-with', 'not-ends-with', 'not-contains'
        ],

        // two-argument operators
        2 => ['between', 'not-between'],

        // unlimited-argument operators
        'unlimited' => ['in', 'not-in']
    ];

    private $pageSize = 25;
    private $maxPerPage = 500;
    private $defaultSorting = [];
    private $allowedFilterTypes = [];
    private $allowedSortTypes = [];

    /**
     * @var \Closure
     */
    private $itemRenderer;

    public function __construct()
    {
        // No op -- We don't want to pass properties into the parent's constructor, since we are requiring
        // the populate() method to be called after the configuration is set up.
    }

    public function setMaxPerPage($max)
    {
        $this->maxPerPage = $max;

        return $this;
    }

    public function getMaxPerPage()
    {
        return $this->maxPerPage;
    }

    public function setPageSize($size)
    {
        $this->pageSize = $size;

        return $this;
    }

    public function getPageSize()
    {
        return $this->pageSize;
    }

    public static function getSupportedQueryParamNames()
    {
        return ['limit', 'offset', 'sort', 'filters', 'fields'];
    }

    public function getInputSchema()
    {
        $schema = CollectionInputSchema::make();

        if ($this->allowedFilterTypes) {
            $filterOptions = JsonSchema::make();
            foreach ($this->allowedFilterTypes as $name => $filter) {
                $params = $filter->getSchemaProperties();
                if ($filter->getTitle()) {
                    $params['title'] = 'Filter by ' . $filter->getTitle();
                }
                $filterOptions->setPropertyRef($name, '#/definitions/filtering', $params);
            }
            $schema->setProperty('filters', $filterOptions);
        }

        if ($this->allowedSortTypes) {
            $sortOptions = JsonSchema::make();
            foreach ($this->allowedSortTypes as $name => $sort) {
                $params = [];
                if ($sort->getTitle()) {
                    $params['title'] = 'Sort by ' . $sort->getTitle();
                }
                $sortOptions->setPropertyRef($name, '#/definitions/sorting', $params);
            }
            $schema->setProperty('sort', $sortOptions);
        }

        return $schema;
    }

    public function setFilterTypes(array $allowedTypes)
    {
        $this->allowedFilterTypes = [];
        foreach ($allowedTypes as $index => $type) {
            if ($type instanceof Filter) {
                $filter = $type;

            } else {
                $filter = new Filter($type);

                if (!is_numeric($index)) {
                    $filter->setField($index);
                }
            }

            $this->allowedFilterTypes[$filter->getName()] = $filter;
        }

        return $this;
    }

    /**
     * Which sorting types are supported. The input is an array that can contain a mix of indexed and associative
     * keys and values.  Simple array values as shown below must refer to columns in the database table.
     *
     *     $allowedTypes[] = 'blahblah';
     *     $allowedTypes[] = 'yoyoyo';
     *
     * For complex sort types, use the syntax below. The key does not necessarily refer to a column in the database
     * table.  The value must be a closure, which will be called with two arguments. The first is a reference to the
     * Eloquent query, which the closure is expected to modify by adding one or more calls to $query->orderBy().
     * The second argument given to the closure is the requested sort direction, e.g. "asc" or "desc".
     *
     *     $allowedTypes['foofoo'] = function(Builder $query, $direction) {
     *         $query->orderBy('yip', $direction)
     *              ->orderBy('bang', $direction);
     *     };
     *
     * @param array $allowedTypes List of allowed sorting types for this collection.
     * @param array $sorting Associative array of default sorting. Keys are sort types. Values are "asc or "desc".
     *                       For example: $sorting = ['scheduled' => 'asc', 'created' => 'desc'];
     * @return $this
     */
    public function setSortTypes(array $allowedTypes, $defaultSorting = [])
    {
        $this->allowedSortTypes = [];
        foreach ($allowedTypes as $index => $type) {
            if ($type instanceof Sort) {
                $sort = $type;

            } else {
                $sort = new Sort($type);

                if (!is_numeric($index)) {
                    $sort->setField($index);
                }
            }

            $this->allowedSortTypes[$sort->getName()] = $sort;
        }

        $this->defaultSorting = $defaultSorting;

        return $this;
    }

    public function setDefaultSorting($name, $direction = 'asc')
    {
        $this->defaultSorting = [$name => $direction];

        return $this;
    }

    /**
     * How the items in the response should be formatted. The specified closure will be called with two
     * arguments: A Mason Child instance which the closure is expected to populate, and an Eloquent model
     * instance which represents the source of the data. Example implementation:
     *
     *     $renderer = function(Child $childDoc, Model $item) {
     *         $childDoc->setProperties([
     *             'id' => $item->id,
     *             'name' => $item->name
     *         ]);
     *     }
     *
     * If no Item Renderer is set, the default behavior is equivalent to:
     *
     *     $renderer = function(Child $childDoc, Model $item) {
     *         $childDoc->setProperties($item->toArray());
     *     }
     *
     * @param \Closure $renderer Function for populating a Mason Child instance from the Model instance
     * @return $this
     */
    public function setItemRenderer(\Closure $renderer)
    {
        $this->itemRenderer = $renderer;

        return $this;
    }

    /**
     * Populate the Mason document based on the inputs
     * @return $this
     */
    public function populate(Request $request = null, $container = null)
    {
        if ($container instanceof Builder) {
            $container = new EloquentContainer($container);

        } elseif ($container instanceof Collection) {
            $container = new CollectionContainer($container);

        } elseif (!$container instanceof Container) {
            throw new \InvalidArgumentException('Unsupported container');
        }

        $this->assertValidInputs($request);
        $this->applyFiltering($request, $container);
        $this->applySorting($request, $container);

        $limit = (int)$request->input('limit', $this->pageSize);
        if ($limit < 1) {
            $limit = 1;
        }

        $offset = (int)$request->input('offset', 0);
        if ($offset < 0) {
            $offset = 0;
        }

        list($items, $totalItems) = $container->getItems($limit, $offset);

        $this->addPaginationProperties($request, $totalItems, $offset, $limit);
        $this->setProperty('items', $this->getRenderedItemList($request, $items));

        return $this;
    }

    private function getRenderedItemList(Request $request, $rawItems)
    {
        $fields = $request->input('fields');
        $renderFull = ($fields === null || in_array($request->input('fields'), ['all', 'full']));

        $items = [];
        foreach ($rawItems as $index => $rawItem) {
            if ($this->itemRenderer) {
                $item = new Child();
                call_user_func_array($this->itemRenderer, [$request, $item, $rawItem, $renderFull]);

            } elseif (is_object($rawItem)) {
                if ($renderFull && method_exists($rawItem, 'toFullMason')) {
                    $item = $rawItem->toFullMason();

                } elseif (!$renderFull && method_exists($rawItem, 'toBriefMason')) {
                    $item = $rawItem->toBriefMason();

                } else {
                    $item = get_object_vars($rawItem);
                }

            } else {
                $item = $rawItem;
            }

            $items[] = $item;
        }

        return $items;
    }

    private function addPaginationProperties(Request $request, $totalItems, $offset, $limit)
    {
        $this->setProperties([
            'total' => $totalItems,
            'offset' => $offset,
            'limit' => $limit
        ]);

        $this->setControl('first', $this->url($request, 0));
        if ($offset > 0) {
            $this->setControl('prev', $this->url($request, ($offset - $limit > 0 ? $offset - $limit : 0)));
        }
        if ($offset + $limit < $totalItems) {
            $this->setControl('next', $this->url($request, $offset + $limit));
        }
        if (floor($totalItems / $limit) > 1) {
            $this->setControl('last', $this->url($request, floor($totalItems / $limit) * $limit));
        }
    }

    private function url(Request $request, $offset)
    {
        $parameters = $request->query->all();
        $parameters['offset'] = $offset;

        return $request->url() . '?' . http_build_query($parameters);
    }

    private function assertValidInputs(Request $request)
    {
        $rules = [
            'limit' => 'sometimes|integer|min:1|max:' . $this->maxPerPage,
            'offset' => 'sometimes|integer|min:0',
            'sort' => 'sometimes|array|sorting:' . join(',', $this->getValidSortTypes()),
            'filters' => 'sometimes|array',
            'fields' => 'sometimes|in:all,full,brief'
        ];

        $filters = $request->get('filters');
        if ($filters && is_array($filters)) {
            $filterList = join(',', $this->getValidFilterTypes());

            foreach ($filters as $key => $value) {
                $rules["filters.$key"] = "required|filter_type:$filterList";

                $filter = @$this->allowedFilterTypes[$key];
                if ($filter) {

                    $extraRules = "filter_param_count";
                    $filterRules = $filter->getValidationRules();
                    if ($filterRules) {
                        $extraRules .= "|$filterRules";
                    }

                    if (is_scalar($value)) {
                        $rules["filters.$key"] .= "|$extraRules";

                    } else {
                        foreach ($value as $index => $subvalue) {
                            $rules["filters.$key.$index"] = "required|$extraRules";
                        }
                    }
                }
            }
        }

        $this->extendValidator();

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    private function extendValidator()
    {
        Validator::extend('filterEnum', function ($attribute, $value, $parameters) {
            return (preg_match("/^\w+\:(" . join('|', $parameters) . ")/", $value));
        });

        Validator::extend('filterType', function ($attribute, $clause, $allowedFilterTypes) {
            $type = substr($attribute, strrpos($attribute, '.') + 1);
            if (!in_array($type, $allowedFilterTypes)) {
                return false;
            }

            return true;
        });

        Validator::extend('filterParamCount', function ($attribute, $value, $parameters) {
            list($operator, $params) = self::parseFilterItem($value);

            $parameterCount = count($params);

            return (
                in_array($operator, self::$filterOperators['unlimited'])
                || (
                    isset(self::$filterOperators[$parameterCount])
                    && in_array($operator, self::$filterOperators[$parameterCount])
                )
            );
        });

        Validator::extend('sorting', function ($attribute, $sort, $allowedSortingTypes) {
            foreach ($sort as $type => $direction) {
                if (!in_array($type, $allowedSortingTypes) || !in_array($direction, ['asc', 'desc'])) {
                    return false;
                }
            }

            return true;
        });

    }

    private function getValidFilterTypes()
    {
        return array_keys($this->allowedFilterTypes);
    }

    private function getValidSortTypes()
    {
        return array_keys($this->allowedSortTypes);
    }

    private function applySorting(Request $request, Container $container)
    {
        $sort = $request->input('sort', $this->defaultSorting);
        foreach ($sort as $name => $direction) {
            $this->allowedSortTypes[$name]->apply($container, $direction);
        }

        $this->setProperty('sort', $sort);
    }

    private function applyFiltering(Request $request, Container $container)
    {
        $filters = $request->input('filters', []);
        if ($filters) {

            foreach ($filters as $name => $subfilters) {

                if (is_scalar($subfilters)) {
                    $subfilters = [$subfilters];
                }

                foreach ($subfilters as $subfilter) {
                    list($operator, $params) = self::parseFilterItem($subfilter);
                    $this->allowedFilterTypes[$name]->apply($container, $operator, $params);
                }
            }
        }

        $this->setProperty('filters', (object)$filters);
    }

    public static function parseFilterItem($filter)
    {
        $parts = explode(':', $filter);

        $operator = array_shift($parts);
        $paramString = join(':', $parts);

        $foundOperator = false;
        foreach (self::$filterOperators as $paramCount => $operators) {
            if (in_array($operator, $operators)) {
                $foundOperator = true;
                break;
            }
        }
        if (!$foundOperator) {
            $operator = 'eq';
            $paramString = $filter;
        }

        if ($paramString !== '') {
            $paramString = str_replace('\,', '|#$DELIMITER$#|', $paramString);
            $params = explode(',', $paramString);
            array_walk($params, function (&$value) {
                $value = str_replace('|#$DELIMITER$#|', ',', $value);
            });
        } else {
            $params = [];
        }

        return [$operator, $params];
    }

}
