<?php
namespace PhoneCom\Mason\Builder\Contrib\MasonCollection;

use PhoneCom\Mason\Builder\Contrib\MasonCollection\Container\Container;

class Sort
{
    /**
     * Name of the filter
     * @var string
     */
    protected $name;

    protected $title;

    /**
     * Callback function for applying filters
     * @var callable
     */
    protected $function;

    /**
     * Name of the field to filter on, if different from the filter's name. This is useful for example if the
     * primary key of the database table is different from how you wish it to be represented in the query arguments.
     * @var string
     */
    protected $field;

    public static function make($name, $title = null)
    {
        return new static($name, $title);
    }

    public function __construct($name, $title = null)
    {
        $this->setName($name);
        if ($title) {
            $this->setTitle($title);
        }
    }

    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setField($field)
    {
        $this->field = $field;

        return $this;
    }

    public function getField()
    {
        return ($this->field ?: $this->getName());
    }

    public function setFunction($callable)
    {
        $this->function = $callable;

        return $this;
    }

    public function apply(Container $container, $direction)
    {
        $callable = $this->function;
        if ($callable) {
            $callable($container, $direction, $this);

        } else {
            $container->setSorting($this->getField(), $direction);
        }
    }

}
