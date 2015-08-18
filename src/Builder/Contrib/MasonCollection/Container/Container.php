<?php
namespace PhoneCom\Mason\Builder\Contrib\MasonCollection\Container;

use Illuminate\Database\Eloquent\Builder;
use PhoneCom\Mason\Builder\Contrib\MasonCollection\Filter;

interface Container
{
    public function setSorting($field, $direction);
    public function applyFilter(Filter $filter, $operator, array $params);
    public function getItems($limit, $offset);
}
