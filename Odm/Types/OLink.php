<?php
/**
 * @package     bodev-core-bundles/php-orient-bundle
 * @subpackage  Odm/Types
 * @name        Link
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
use PhpOrient\Protocols\Binary\Data\ID as ID;

class OLink extends BaseType
{

    /** @var ID $value */
    protected $value;

    /**
     * @param string $value
     *
     * @throws \BiberLtd\Bundle\Phorient\Odm\Exceptions\InvalidValueException
     */
    public function __construct($value = null, $embedded = false)
    {
        parent::__construct('OLink', $value, $embedded);
    }

    /**
     * @return ID
     */
    public function getValue($embedded = false)
    {
        if (!$embedded) {
            if (is_object($this->value) && method_exists($this->value, 'getRid')) {
                return $this->value->getRid();
            }
        }
        return $this->value;
    }

    /**
     * @param $value
     *
     * @return $this
     * @throws \BiberLtd\Bundle\Phorient\Odm\Exceptions\InvalidValueException
     * @throws \BiberLtd\Bundle\Phorient\Odm\Types\InvalidRecordIdString
     */
    public function setValue($value)
    {
        if (!$this->validateValue($value)) {
            throw new InvalidValueException('ORecordId');
        }
        if ($value instanceof ID) {
            $this->value = $value;
        } else if (is_array($value) && count($value) === 2) {
            $this->value = new ID($value[0], $value[1]);
        } else if (is_string($value)) {
            if (strpos($value, '#') !== 0) {
                throw new InvalidRecordIdString();
            }
            $value = str_replace('#', '', $value);
            $value = explode(':', $value);
            if (count($value) !== 2) {
                throw new InvalidRecordIdString();
            }
            $this->value = new ID($value[0], $value[1]);
        } else if (is_object($value) && method_exists($value, 'getRid')) {
            $this->value = $value;
        }

        unset($value);

        return $this;
    }

    /**
     * @param mixed $value
     *
     * @return bool
     */
    public function validateValue($value)
    {
        if ($value == null) {
            return true;
        }
        if ($value instanceof ID) {
            return true;
        } else if (is_array($value) && count($value) === 2) {
            return true;
        } else {
            if (is_string($value)) {
                if (strpos($value, '#') !== 0) {
                    return false;
                }
                $value = str_replace('#', '', $value);
                $value = explode(':', $value);
                if (count($value) !== 2) {
                    return false;
                }
                return true;
            }
            elseif (is_object($value)) {
                if (method_exists($value, 'getRid')) {
                    return true;
                }
            }
        }
        return false;
    }

}