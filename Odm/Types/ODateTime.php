<?php
/**
 * @package     bodev-core-bundles/php-orient-bundle
 * @subpackage  Odm/Types
 * @name        DateTime
 *
 * @author      Biber Ltd. (www.biberltd.com)
 * @author      Can Berkol
 *
 * @copyright   Biber Ltd. (C) 2015
 *
 * @version     1.0.0
 */

namespace BiberLtd\Bundle\Phorient\Odm\Types;

use BiberLtd\Bundle\Phorient\Odm\Exceptions\InvalidValueException;

class ODateTime extends BaseType{

    /** @var \DateTime $value */
    protected $value;

    /**
     * @param \DateTime $value
     *
     * @throws \BiberLtd\Bundle\Phorient\Odm\Exceptions\InvalidValueException
     */
    public function __construct($value = null){
        parent::__construct('ODateTime', $value);
    }

    /**
     * @return \DateTime
     */
    public function getValue($embedded = false){
        if (!$embedded) {
            return $this->value->format('Y-m-d\TH:i:s\Z');;
        }

        return $this->value;
    }

    /**
     * @param $value
     *
     * @return $this
     * @throws \BiberLtd\Bundle\Phorient\Odm\Exceptions\InvalidValueException
     */
    public function setValue($value){
        if($this->validateValue($value)){
            $this->value = $value;
        }
        return $this;
    }
    /*
     * @param mixed $value
     *
     * @return bool
     * @throws \BiberLtd\Bundle\Phorient\Odm\Exceptions\InvalidValueException
     */
    public function validateValue($value){
        if(!$value instanceof \DateTime && $value != null){
            throw new InvalidValueException($this);
        }
        return true;
    }

}