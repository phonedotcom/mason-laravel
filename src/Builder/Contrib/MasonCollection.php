<?php
namespace PhoneCom\Mason\Builder\Contrib;

use Illuminate\Contracts\Validation\ValidationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Validator;
use PhoneCom\Mason\Builder\Child;
use PhoneCom\Mason\Builder\Document;
use PhoneCom\Sdk\Models\ModelQueryBuilder;

class MasonCollection extends Document
{
    const DEFAULT_PER_PAGE = 10;
    const MAX_PER_PAGE = 300;

    private $assembled = false;

    private $filterOperators = [
        // zero-argument operators
        0 => ['empty', 'not-empty'],

        // one-argument operators
        1 => [
            'eq', 'ne', 'lt', 'gt', 'lte', 'gte',
            'starts-with', 'ends-with', 'contains', 'not-starts-with', 'not-ends-with', 'not-contains'
        ],

        // two-argument operators
        2 => ['between', 'not-between']
    ];

    /**
     * @var Builder|ModelQueryBuilder|BaseCollection
     */
    private $data;

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
     * @param mixed $data Eloquent query for retrieving items, or list of already retrieved items
     */
    public function __construct(Request $request, $data)
    {
        parent::__construct();

        $this->request = $request;

        if (!is_array($data) && !$data instanceof Builder && !$data instanceof BaseCollection && !$data instanceof ModelQueryBuilder) {
            throw new \InvalidArgumentException(sprintf(
                'Data is not an instance of array, %s, %s, or %s, "%s" given instead',
                Builder::class,
                ModelQueryBuilder::class,
                BaseCollection::class,
                gettype($data)
            ));
        }
        $this->data = $data;
    }

    public function setFilterTypes(array $allowedTypes)
    {
        $this->allowedFilterTypes = $allowedTypes;

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

            list($assembledItems, $totalItems, $offset, $limit) = $this->getQueryResults();
            $this->applyPagination($totalItems, $offset, $limit);

            $this->setProperty('items', $assembledItems->toArray());

            $this->assembled = true;
        }

        return $this;
    }

    private function applyPagination($totalItems, $offset, $limit)
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

        $filters = $this->request->get('filter');
        if ($filters) {
            $filterTypeString = join(',', array_values($this->allowedFilterTypes));
            foreach ($filters as $key => $value) {
                $rules["filter.$key"] = "required|filter_type:$filterTypeString";

                if (is_scalar($value)) {
                    $rules["filter.$key"] .= '|filter_operator|filter_param_count';

                } else {
                    foreach ($value as $index => $subvalue) {
                        $rules["filter.$key.$index"] = 'required|filter_operator|filter_param_count';
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
        Validator::extend('filterType', function ($attribute, $clause, $allowedFilterTypes) {
            $type = substr($attribute, strrpos($attribute, '.') + 1);
            if (!in_array($type, $allowedFilterTypes)) {
                return false;
            }

            return true;
        });

        Validator::extend('filterOperator', function ($attribute, $filters, $parameters) {
            $operator = (strstr($filters, ':', true) ?: $filters);

            foreach ($this->filterOperators as $parameterCount => $operators) {
                if (in_array($operator, $operators)) {
                    return true;
                }
            }

            return false;
        });

        Validator::extend('filterParamCount', function ($attribute, $value, $parameters) {
            list($operator, $params) = self::parseFilterItem($value);

            $parameterCount = count($params);
            if (!isset($this->filterOperators[$parameterCount])
                || !in_array($operator, $this->filterOperators[$parameterCount])
            ) {
                return false;
            }

            return true;
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
        if (!$this->data instanceof Builder && !$this->data instanceof ModelQueryBuilder) {
            return;
        }

        $sort = $this->request->input('sort', $this->defaultSorting);
        foreach ($sort as $type => $direction) {
            if (isset($this->allowedSortTypes[$type])) {
                $closure = $this->allowedSortTypes[$type];
                $closure($this->data, $direction);
            } else {
                $this->data->orderBy($type, $direction);
            }
        }

        if ($this->allowedSortTypes) {
            $this->setMetaProperty('sort', $sort);
        }
    }

    private function applyFiltering()
    {
        $filters = $this->request->input('filter');
        if ($filters) {
            foreach ($filters as $type => $subfilters) {
                $this->applySubfilters($type, $subfilters);
            }

            if ($this->allowedFilterTypes) {
                $this->setMetaProperty('filter', $filters);
            }
        }
    }

    private function applySubfilters($type, $subfilters)
    {
        if (is_scalar($subfilters)) {
            $subfilters = [$subfilters];
        }

        foreach ($subfilters as $subfilter) {
            list($operator, $params) = self::parseFilterItem($subfilter);
            $this->applyFilter($type, $operator, $params);
        }
    }

    public static function parseFilterItem($filter)
    {
        $operator = (strstr($filter, ':', true) ?: $filter);
        $offset = strpos($filter, ':');
        $paramString = ($offset !== false ? substr($filter, $offset + 1) : '');

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

    private function applyFilter($column, $operator, array $params)
    {
        if (!$this->data instanceof Builder && !$this->data instanceof ModelQueryBuilder) {
            return;
        }

        $index = array_search($column, $this->allowedFilterTypes);
        if (!is_numeric($index)) {
            $column = $index;
        }

        $sqlLikeEscapes = [
            '%' => '\%',
            '_' => '\_'
        ];

        switch ($operator) {
            // Zero-parameter operators

            case 'empty':
                $this->data->where(function ($query) use ($column) {
                    $query->whereIn($column, ['', 0])
                        ->orWhereNull($column);
                });
                break;

            case 'not-empty':
                $this->data->where(function ($query) use ($column) {
                    $query->whereNotIn($column, ['', 0])
                        ->whereNotNull($column);
                });
                break;

            // Single-parameter operators

            case 'eq':
                $this->data->where($column, $params[0]);
                break;

            case 'ne':
                $this->data->where(function ($query) use ($column, $params) {
                    $query->where($column, '!=', $params[0])
                        ->orWhereNull($column);
                });

                break;

            case 'lt':
                $this->data->where($column, '<', $params[0]);
                break;

            case 'gt':
                $this->data->where($column, '>', $params[0]);
                break;

            case 'lte':
                $this->data->where($column, '<=', $params[0]);
                break;

            case 'gte':
                $this->data->where($column, '>=', $params[0]);
                break;

            case 'starts-with':
                $this->data->where($column, 'LIKE', strtr($params[0], $sqlLikeEscapes) . '%');
                break;

            case 'ends-with':
                $this->data->where($column, 'LIKE', '%' . strtr($params[0], $sqlLikeEscapes));
                break;

            case 'contains':
                $this->data->where($column, 'LIKE', '%' . strtr($params[0], $sqlLikeEscapes) . '%');
                break;

            case 'not-starts-with':
                $this->data->where(function ($query) use ($column, $params, $sqlLikeEscapes) {
                    $query->where($column, 'NOT LIKE', strtr($params[0], $sqlLikeEscapes) . '%')
                        ->orWhereNull($column);
                });
                break;

            case 'not-ends-with':
                $this->data->where(function ($query) use ($column, $params, $sqlLikeEscapes) {
                    $query->where($column, 'NOT LIKE', '%' . strtr($params[0], $sqlLikeEscapes))
                        ->orWhereNull($column);
                });
                break;

            case 'not-contains':
                $this->data->where(function ($query) use ($column, $params, $sqlLikeEscapes) {
                    $query->where($column, 'NOT LIKE', '%' . strtr($params[0], $sqlLikeEscapes) . '%')
                        ->orWhereNull($column);
                });
                break;

            // Dual-parameter operators

            case 'between':
                $this->data->whereBetween($column, $params);
                break;

            case 'not-between':
                $this->data->where(function ($query) use ($column, $params, $sqlLikeEscapes) {
                    $query->whereNotBetween($column, $params)
                        ->orWhereNull($column);
                });
                break;

        }
    }

    private function url($offset)
    {
        $parameters = $this->request->query->all();
        $parameters['offset'] = $offset;

        return $this->request->url() . '?' . http_build_query($parameters);
    }

    private function getQueryResults()
    {
        if (is_array($this->data) || $this->data instanceof BaseCollection) {
            $assembledItems = $this->getRenderedItemList($this->data);
            $total = count($this->data);
            $offset = 0;
            $limit = $total;

        } elseif ($this->data instanceof Builder || $this->data instanceof ModelQueryBuilder) {

            if ($this->request->has('page_size')) {
                $limit = (int)$this->request->input('page_size');
            } else {
                $limit = (int)$this->request->input('limit', self::DEFAULT_PER_PAGE);
            }

            if ($this->request->has('page')) {
                $offset = (int)$this->request->input('page') * $limit;
            } else {
                $offset = (int)$this->request->input('offset', 0);
            }

            if ($this->data instanceof ModelQueryBuilder) {
                list($pageOfItems, $total) = $this->data
                    ->skip($offset)
                    ->take($limit)
                    ->getWithTotal();

            } else {
                $total = $this->data->getQuery()->getCountForPagination();

                $pageOfItems = $this->data
                    ->skip($offset)
                    ->take($limit)
                    ->get();
            }

            $assembledItems = $this->getRenderedItemList($pageOfItems);
        }

        return [$assembledItems, $total, $offset, $limit];
    }

    private function getRenderedItemList($items)
    {
        $assembledItems = new Collection();
        foreach ($items as $index => $item) {
            if ($this->itemRenderer) {
                $childItem = new Child();
                call_user_func_array($this->itemRenderer, [$childItem, $item]);

            } elseif (is_object($item) && method_exists($item, 'toFullMason')) {
                $childItem = $item->toFullMason($this);

            } else {
                $childItem = $item;
            }

            $assembledItems->add($childItem);
        }

        return $assembledItems;
    }
}
