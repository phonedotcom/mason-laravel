<?php
namespace PhoneCom\Mason\Builder\Contrib\MasonCollection;

use PhoneCom\Mason\Builder\Contrib\MasonCollection\Container\Container;

class Filter
{
    /**
     * Name of the filter
     * @var string
     */
    protected $name;

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

    public function __construct($name, $function = null, $operators = null, $rules = null)
    {
        $this->setName($name);
        if ($function) {
            $this->setFunction($function);
        }
        if ($operators) {
            $this->setSupportedOperators($operators);
        }
        if ($rules) {
            $this->setValidationRules($rules);
        }
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
            $callable($container, $operator, $params);

        } else {
            $container->applyFilter($this, $operator, $params);
        }
    }

}
