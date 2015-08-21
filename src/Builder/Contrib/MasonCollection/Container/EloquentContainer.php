<?php
namespace PhoneCom\Mason\Builder\Contrib\MasonCollection\Container;

use Illuminate\Database\Eloquent\Builder;
use PhoneCom\Mason\Builder\Contrib\MasonCollection\Filter;

class EloquentContainer implements Container
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
        $column = $filter->getField();

        $sqlLikeEscapes = [
            '%' => '\%',
            '_' => '\_'
        ];

        switch ($operator) {
            // Zero-parameter operators

            case 'empty':
                $this->query->where(function ($query) use ($column) {
                    $query->whereIn($column, ['', 0])
                        ->orWhereNull($column);
                });
                break;

            case 'not-empty':
                $this->query->where(function ($query) use ($column) {
                    $query->whereNotIn($column, ['', 0])
                        ->whereNotNull($column);
                });
                break;

            // Single-parameter operators

            case 'eq':
                $this->query->where($column, $params[0]);
                break;

            case 'ne':
                $this->query->where(function ($query) use ($column, $params) {
                    $query->where($column, '!=', $params[0])
                        ->orWhereNull($column);
                });
                break;

            case 'lt':
                $this->query->where($column, '<', $params[0]);
                break;

            case 'gt':
                $this->query->where($column, '>', $params[0]);
                break;

            case 'lte':
                $this->query->where($column, '<=', $params[0]);
                break;

            case 'gte':
                $this->query->where($column, '>=', $params[0]);
                break;

            case 'starts-with':
                $this->query->where($column, 'LIKE', strtr($params[0], $sqlLikeEscapes) . '%');
                break;

            case 'ends-with':
                $this->query->where($column, 'LIKE', '%' . strtr($params[0], $sqlLikeEscapes));
                break;

            case 'contains':
                $this->query->where($column, 'LIKE', '%' . strtr($params[0], $sqlLikeEscapes) . '%');
                break;

            case 'not-starts-with':
                $this->query->where(function ($query) use ($column, $params, $sqlLikeEscapes) {
                    $query->where($column, 'NOT LIKE', strtr($params[0], $sqlLikeEscapes) . '%')
                        ->orWhereNull($column);
                });
                break;

            case 'not-ends-with':
                $this->query->where(function ($query) use ($column, $params, $sqlLikeEscapes) {
                    $query->where($column, 'NOT LIKE', '%' . strtr($params[0], $sqlLikeEscapes))
                        ->orWhereNull($column);
                });
                break;

            case 'not-contains':
                $this->query->where(function ($query) use ($column, $params, $sqlLikeEscapes) {
                    $query->where($column, 'NOT LIKE', '%' . strtr($params[0], $sqlLikeEscapes) . '%')
                        ->orWhereNull($column);
                });
                break;

            // Dual-parameter operators

            case 'between':
                $this->query->whereBetween($column, $params);
                break;

            case 'not-between':
                $this->query->where(function ($query) use ($column, $params, $sqlLikeEscapes) {
                    $query->whereNotBetween($column, $params)
                        ->orWhereNull($column);
                });
                break;

            // Unlimited-parameter operators

            case 'in':
                $this->query->whereIn($column, $params);
                break;

            case 'not-in':
                $this->query->whereNotIn($column, $params);
                break;
        }

        return $this;
    }

    public function getItems($limit, $offset)
    {
        $total = $this->query->getQuery()->getCountForPagination();
        $pageOfItems = $this->query->skip($offset)->take($limit)->get();

        return [$pageOfItems, $total];
    }
}
