<?php
namespace Phonedotcom\Mason\Builder\Contrib\MasonCollection;

use Phonedotcom\Mason\Builder\Contrib\MasonCollection\Container\Container;

class Filter
{
    /**
     * Name of the filter
     * @var string
     */
    protected $name;

    protected $title;

    protected $schemaProperties = [];

    /**
     * Supported operators. If unspecified, all known operators will be accepted.
     * @var array
     */
    protected $operators;

    /**
     * Callback function for applying filters
     * @var callable
     */
    protected $function;

    /**
     * Extra validation rules
     * @var string|array
     */
    protected $rules;

    /**
     * Name of the field to filter on, if different from the filter's name. This is useful for example if the
     * primary key of the database table is different from how you wish it to be represented in the query arguments.
     * @var string
     */
    protected $field;

    /**
     * Class which contains the grammar for assembling the filters. Defaults to EloquentGrammar.
     * @var Grammar
     */
    protected $grammar;

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

    public function setSchemaProperties(array $properties)
    {
        $this->schemaProperties = $properties;

        return $this;
    }

    public function getSchemaProperties()
    {
        $properties = $this->schemaProperties;
        if (!isset($properties['title']) && $this->title) {
            $properties['title'] = $this->title;
        }

        return $properties;
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

    public function setSupportedOperators($operators)
    {
        if (is_string($operators)) {
            $operators = func_get_args();
        }

        $this->operators = $operators;

        return $this;
    }

    public function getSupportedOperators()
    {
        return $this->operators;
    }

    public function setFunction($callable)
    {
        $this->function = $callable;

        return $this;
    }

    public function setValidationRules($rules)
    {
        $this->rules = $rules;

        return $this;
    }

    public function getValidationRules()
    {
        return $this->rules;
    }

    public function apply(Container $container, $operator, $params)
    {
        $callable = $this->function;
        if ($callable) {
            $callable($container, $operator, $params, $this);

        } else {
            $container->applyFilter($this, $operator, $params);
        }
    }

}
