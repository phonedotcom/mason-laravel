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
        $this->query->where($filter->getField(), $operator, $params);

        return $this;
    }

    public function getItems($limit, $offset)
    {
        return $this->query->skip($offset)->take($limit)->getWithTotal();
    }
}
