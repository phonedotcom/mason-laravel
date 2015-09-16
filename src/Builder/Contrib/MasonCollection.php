<?php
namespace PhoneCom\Mason\Builder\Contrib;

use Illuminate\Contracts\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Validator;
use PhoneCom\Mason\Builder\Child;
use PhoneCom\Mason\Builder\Contrib\MasonCollection\Container\Container;
use PhoneCom\Mason\Builder\Contrib\MasonCollection\Filter;
use PhoneCom\Mason\Builder\Document;

class MasonCollection extends Document
{
    const DEFAULT_PER_PAGE = 10;
    const MAX_PER_PAGE = 300;

    private $assembled = false;

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

    /**
     * @var Container
     */
    private $container;

    private $defaultSorting = [];
    private $allowedFilterTypes = [];
    private $allowedSortTypes = [];

    /**
     * @var Request
     */
    private $request;

    /**
     * @var \Closure
     */
    private $itemRenderer;

    /**
     * @param Request $request HTTP request
     * @param Container $container Container for storing or querying the data set
     */
    public function __construct(Request $request, Container $container)
    {
        parent::__construct();

        $this->request = $request;
        $this->container = $container;
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
        $this->allowedSortTypes = $allowedTypes;
        $this->defaultSorting = $defaultSorting;

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
     * Assemble the Mason document based on the inputs
     * @return $this
     */
    public function assemble()
    {
        if (!$this->assembled) {
            $this->assertValidInputs();
            $this->applyFiltering();
            $this->applySorting();

            $limit = (int)$this->request->input('limit', self::DEFAULT_PER_PAGE);
            if ($limit < 1) {
                $limit = 1;
            }

            $offset = (int)$this->request->input('offset', 0);
            if ($offset < 0) {
                $offset = 0;
            }

            list($items, $totalItems) = $this->container->getItems($limit, $offset);

            $this->addPaginationProperties($totalItems, $offset, $limit);
            $this->setProperty('items', $this->getRenderedItemList($items));

            $this->assembled = true;
        }

        return $this;
    }

    private function getRenderedItemList($rawItems)
    {
        $items = [];
        foreach ($rawItems as $index => $rawItem) {
            if ($this->itemRenderer) {
                $item = new Child();
                call_user_func_array($this->itemRenderer, [$item, $rawItem]);

            } elseif (is_object($rawItem) && method_exists($rawItem, 'toFullMason')) {
                $item = $rawItem->toFullMason();

            } else {
                $item = $rawItem;
            }

            $items[] = $item;

        }

        return $items;
    }

    private function addPaginationProperties($totalItems, $offset, $limit)
    {
        $this->setProperties([
            'total' => $totalItems,
            'offset' => $offset,
            'limit' => $limit
        ]);

        $totalPages = ceil($totalItems / $limit);
        $currentPage = ceil(($offset + 1) / $limit);

        if ($totalPages > 1) {
            $this->setControl('first', $this->url($this->pageNumToOffset(1, $limit)));
        }
        if ($currentPage > 1) {
            $this->setControl('prev', $this->url($this->pageNumToOffset($currentPage - 1, $limit)));
        }
        if ($currentPage < $totalPages) {
            $this->setControl('next', $this->url($this->pageNumToOffset($currentPage + 1, $limit)));
        }
        if ($totalPages > 1) {
            $this->setControl('last', $this->url($this->pageNumToOffset($totalPages, $limit)));
        }
    }

    private function url($offset)
    {
        $parameters = $this->request->query->all();
        $parameters['offset'] = $offset;

        return $this->request->url() . '?' . http_build_query($parameters);
    }

    private function pageNumToOffset($page, $limit)
    {
        return ($page - 1) * $limit;
    }

    private function assertValidInputs()
    {
        $rules = [
            'limit' => 'sometimes|integer|min:1|max:' . self::MAX_PER_PAGE,
            'offset' => 'sometimes|integer|min:0',
            'sort' => 'sorting:' . join(',', $this->getValidSortTypes()),
        ];

        $filters = $this->request->get('filters');
        if ($filters) {
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

        $validator = Validator::make($this->request->all(), $rules);

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
        $types = [];
        foreach ($this->allowedSortTypes as $index => $value) {
            if ($value instanceof \Closure) {
                $types[] = $index;
            } else {
                $types[] = $value;
            }
        }

        return $types;
    }

    private function applySorting()
    {
        // TODO: Add a Sort class like we did for Filters

        $sort = $this->request->input('sort', $this->defaultSorting);
        foreach ($sort as $type => $direction) {
            if (isset($this->allowedSortTypes[$type])) {
                $closure = $this->allowedSortTypes[$type];
                $closure($this->container, $direction);

            } elseif (($key = array_search($type, $this->allowedSortTypes)) !== false) {
                $column = (is_numeric($key) ? $type : $key);
                $this->container->setSorting($column, $direction);
            }
        }

        if ($sort) {
            $this->setMetaProperty('sort', $sort);
        }
    }

    private function applyFiltering()
    {
        $filters = $this->request->input('filters');
        if ($filters) {
            $this->setMetaProperty('filters', $filters);

            foreach ($filters as $name => $subfilters) {

                if (is_scalar($subfilters)) {
                    $subfilters = [$subfilters];
                }

                foreach ($subfilters as $subfilter) {
                    list($operator, $params) = self::parseFilterItem($subfilter);
                    $this->allowedFilterTypes[$name]->apply($this->container, $operator, $params);
                }
            }
        }
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

        if ($paramString) {
            $paramString = str_replace('\,', '|#$DELIMITER$#|', $paramString);
            $params = explode(',', $paramString);
            array_walk($params, function (&$value) {
                $value = trim(str_replace('|#$DELIMITER$#|', ',', $value));
            });
        } else {
            $params = [];
        }

        return [$operator, $params];
    }

}
