<?php
/**
 * @package     bodev-core-bundles/php-orient-bundle
 * @subpackage  Odm/Types
 * @name        EmbeddedSet
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

class OEmbeddedSet extends OrientCollection{

	/** @var array $value */
	protected $value;

	/**
	 * @param array $value
	 *
	 * @throws \BiberLtd\Bundle\Phorient\Odm\Exceptions\InvalidValueException
	 */
	public function __construct(array $value = []){
		parent::__construct('OEmbeddedSet', $value);
	}

	/**
	 * @return array
	 */
	public function getValue($embedded = false){
		return $this->value;
	}

	/**
	 * @param array $value
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
		if(!is_array($value)){
			throw new InvalidValueException($this);
		}
		$unique=[];
		foreach($value as $key => $item){
			if(!is_int($item)){
				throw new InvalidValueException($this);
			}
			if(array_key_exists($item,$unique))
            {
                throw new InvalidValueException($this);
            }else{
                $unique[$item] = true;
            }
		}
		return true;
	}
}