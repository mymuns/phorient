<?php

namespace BiberLtd\Bundle\Phorient\Odm\Types;

abstract class BaseType{
    /** @var  string $name Descriptive name of type */
    public $name;
    /** @var  mixed $value Value of type. */
    protected $value;
    /**
     * @var bool $embedded
     */
    protected $embedded = false;

    /**
     * @param $name     string
     * @param $value    mixed
     *
     * @throws \BiberLtd\Bundle\Phorient\Odm\Exceptions\InvalidValueException
     */
    public function __construct($name, $value = null, $embedded = false){
        $this->name = $name;
        $this->embedded = $embedded;

        $this->setValue($value);
    }
    /**
     * Gets the stored value.
     * @return mixed
     */
    abstract public function getValue($embedded = false);

    /**
     * Sets the stored value.
     * @param $value
     *
     * @return mixed
     */
    abstract public function setValue($value);

    /**
     * Checks if the value is valid. Must be used with setValue() method.
     * @param mixed $value
     */
    abstract public function validateValue($value);
}