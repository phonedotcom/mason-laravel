<?php
namespace Phonedotcom\Mason\Builder\Contrib\MasonCollection\Container;

use Phonedotcom\Mason\Builder\Contrib\MasonCollection\Filter;

interface Container
{
    public function setSorting($field, $direction);
    public function applyFilter(Filter $filter, $operator, array $params);
    public function getItems($limit, $offset);
}
