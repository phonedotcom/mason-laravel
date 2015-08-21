<?php
namespace PhoneCom\Mason\Builder\Contrib\MasonCollection\Container;

use PhoneCom\Sdk\Api\Eloquent\Builder;
use PhoneCom\Mason\Builder\Contrib\MasonCollection\Filter;

class PhonecomContainer implements Container
{
    /**
     * @var Builder
     */
    protected $query;

    public function __construct($query)
    {
        if (!$query instanceof Builder) {
            throw new \InvalidArgumentException(
                sprintf('Unsupported input, expected an instance of ' . Builder::class)
            );
        }

        $this->query = $query;
    }

    public function getQuery()
    {
        return $this->query;
    }

    public function setSorting($column, $direction)
    {
        $this->query->orderBy($column, $direction);
        
        return $this;
    }

    public function applyFilter(Filter $filter, $operator, array $params)
    {
        $field = $filter->getField();

        switch ($operator) {
            // Zero-parameter operators

            case 'empty':
            case 'not-empty':
                throw new \Exception('Not supported yet');
                //$this->query->where($field, 'ne', '');
                break;

            // Single-parameter operators

            case 'eq':
            case 'ne':
            case 'lt':
            case 'gt':
            case 'lte':
            case 'gte':
            case 'starts-with':
            case 'ends-with':
            case 'contains':
            case 'not-starts-with':
            case 'not-ends-with':
            case 'not-contains':
                $this->query->where($field, $operator, $params[0]);
                break;

            // Dual-parameter operators

            case 'between':
            case 'not-between':
                throw new \Exception('Not supported yet');
                //$this->query->whereBetween($field, $params);
                break;
        }

        return $this;
    }

    public function getItems($limit, $offset)
    {
        return $this->query->skip($offset)->take($limit)->getWithTotal();
    }
}
