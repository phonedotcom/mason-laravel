<?php
namespace PhoneCom\Mason\Builder\Contrib\MasonCollection\Container;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use PhoneCom\Mason\Builder\Contrib\MasonCollection\Filter;

class CollectionContainer implements Container
{
    /**
     * @var Collection
     */
    protected $collection;

    public function __construct($collection)
    {
        if (!$collection instanceof Collection) {
            throw new \InvalidArgumentException(
                sprintf('Unsupported input, expected an instance of ' . Collection::class)
            );
        }

        $this->collection = $collection;
    }

    public function setSorting($field, $direction)
    {
        $this->collection = $this->collection->sortBy($field, SORT_NATURAL, (strtolower($direction) == 'desc'));

        return $this;
    }

    public function applyFilter(Filter $filter, $operator, array $params)
    {
        $this->collection = $this->collection->filter(function($item) use ($filter, $operator, $params) {

            $field = $filter->getField();

            switch ($operator) {
                // Zero-parameter operators

                case 'empty':
                    return (empty($item->{$field}));

                case 'not-empty':
                    return (!empty($item->{$field}));

                // Single-parameter operators

                case 'eq':
                    return ($item->{$field} == $params[0]);

                case 'ne':
                    return ($item->{$field} != $params[0]);

                case 'lt':
                    return ($item->{$field} < $params[0]);

                case 'gt':
                    return ($item->{$field} > $params[0]);

                case 'lte':
                    return ($item->{$field} <= $params[0]);

                case 'gte':
                    return ($item->{$field} >= $params[0]);

                case 'starts-with':
                    return (preg_match("/^" . preg_quote($params[0]) . "/i", $item->{$field}));

                case 'ends-with':
                    return (preg_match("/" . preg_quote($params[0]) . "\$/i", $item->{$field}));

                case 'contains':
                    return (preg_match("/" . preg_quote($params[0]) . "/i", $item->{$field}));

                case 'not-starts-with':
                    return (!preg_match("/^" . preg_quote($params[0]) . "/i", $item->{$field}));

                case 'not-ends-with':
                    return (!preg_match("/" . preg_quote($params[0]) . "\$/i", $item->{$field}));

                case 'not-contains':
                    return (!preg_match("/" . preg_quote($params[0]) . "/i", $item->{$field}));

                // Dual-parameter operators

                case 'between':
                    return ($item->{$field} >= $params[0] && $item->{$field} <= $params[1]);

                case 'not-between':
                    return ($item->{$field} < $params[0] || $item->{$field} > $params[1]);

                // Unlimited-parameter operators

                case 'in':
                    foreach ($params as $value) {
                        if ($item->{$field} == $value) {
                            return true;
                        }
                    }
                    return false;

                case 'not-in':
                    foreach ($params as $value) {
                        if ($item->{$field} == $value) {
                            return false;
                        }
                    }
                    return true;
            }
        });

        return $this;
    }

    public function getItems($requestedLimit, $requestedOffset)
    {
        $items = $this->collection->slice($requestedOffset, $requestedLimit);

        return [$items, $this->collection->count()];
    }
}
