<?php
/**
 * 2016 (C) BOdev Office | bodevoffice.com
 *
 * @license MIT
 *
 * Developed by Biber Ltd. (http://www.biberltd.com), a partner of BOdev Office (http://www.bodevoffice.com)
 *
 * Paid Customers ::
 *
 * Check http://team.bodevoffice.com for technical documentation or consult your representative.
 *
 * Contact support@bodevoffice.com for support requests.
 */

namespace BiberLtd\Bundle\Phorient\Odm\Entity;

use AppBundle\Entity\SaasAcademic\AcademicUnit;
use BiberLtd\Bundle\Phorient\Odm\Exceptions\InvalidRecordIdString;
use BiberLtd\Bundle\Phorient\Odm\Types\BaseType;
use BiberLtd\Bundle\Phorient\Odm\Types\ORecordId;
use BiberLtd\Bundle\Phorient\Services\ClassManager;
use BiberLtd\Bundle\Phorient\Services\Metadata;
use Doctrine\Common\Annotations\AnnotationException;
use Doctrine\ORM\Mapping AS ORM;
use Doctrine\ORM\Mapping\Column;
use PhpOrient\Protocols\Binary\Data\ID as ID;
use PhpOrient\Protocols\Binary\Data\Record as ORecord;
use Doctrine\Common\Annotations\AnnotationReader as AnnotationReader;
use BiberLtd\Bundle\Phorient\Odm\Repository\BaseRepository;
use PhpOrient\Protocols\Binary\Data\Record;
use JMS\Serializer\Annotation as JMS;
class BaseClass
{
    /**
     * @ORM\Column(type="ORecordId")
     */
    public $rid = null;

    /**
     * @var bool
     * @JMS\Exclude()
     */
    protected $modified = false;

    /**
     * @var \DateTime
     * @JMS\Exclude()
     */
    protected $dateAdded;

    /**
     * @var \DateTime
     */
    protected $dateUpdated;

    /**
     * @var \DateTime|null
     * @JMS\Exclude()
     */
    protected $dateRemoved = null;

    /**
     * @var string $version md5 Hash of object serialization
     * @JMS\Exclude()
     */
    protected $versionHash;

    /**
     * @var array Version history, the first element is always the original version
     * @JMS\Exclude()
     * @ORM\Column(type="OEmbeddedMap")
     */
    private $versionHistory = [];


    /**
     * @var array Holds definition of all public properties of a class for serialization purposes.
     * @JMS\Exclude()
     */
    private $updatedProps = [];

    /**
     * @var ClassManager
     * @JMS\Exclude()
     */
    protected $cm;

    /**
     * @JMS\Exclude()
     */
    protected $dtFormat;
    /**
     * @var string
     * @JMS\Exclude()
     */
    protected $typePath = 'BiberLtd\\Bundle\\Phorient\\Odm\\Types\\';

    /**
     * BaseClass constructor.
     *
     * @param ClassManager $cm
     * @param ORecord|null $record
     * @param string       $timezone
     */
    public function __construct(ClassManager $cm, ORecord $record = null, $timezone = 'Europe/Istanbul')
    {
        $this->cm = $cm;

        if(isset($this->controller->dateTimeFormat)) {
            $this->dtFormat = $this->controller->dateTimeFormat;
        } else {
            $this->dtFormat = 'd.m.Y H:i:s';
        }
        if(is_null($record)) {
            $this->dateAdded = new \DateTime('now', new \DateTimeZone($timezone));
            $this->dateUpdated = $this->dateAdded;
            $this->setDefaults();
        } else {
            $this->cm->convertRecordToOdmObject($this,$record);
        }

        $this->versionHash = md5($this->cm->getDataManipulator()->output($this,'json'));
    }

    /**
     * @return bool
     */
    final public function isModified()
    {
        $this->getUpdatedVersionHash();
        if($this->getUpdatedVersionHash() === $this->versionHash) {
            $this->modified = false;
            return false;
        }

        $this->modified = true;

        return true;
    }

    /**
     * @return $this
     */
    final public function setVersionHistory()
    {
        if($this->versionHash !== $this->getUpdatedVersionHash() && !$this->modified) {
            $this->versionHistory[] = $this->cm->getDataManipulator()->output($this,'json');
            $this->modified = true;
        } else {
            $this->modified = false;
        }

        return $this;
    }

    /**
     * @return string
     */
    final protected function getUpdatedVersionHash()
    {
        return md5($this->cm->getDataManipulator()->output($this,'json'));
    }

    /**
     * Alias to getRid() method
     *
     * @return ID
     */
    public function getRecordId($as = 'object')
    {
        return $this->getRid($as);
    }

    /**
     * @param string
     *
     * @return ID
     */
    public function getRid($as = 'object')
    {
        if($as == 'string') {
            if(is_null($this->rid->getValue())) return null;
            $id = $this->rid->getValue();

            return '#' . $id->cluster . ':' . $id->position;
        }

        return $this->rid->getValue();
    }

    /**
     * Alias to setRid() method.
     *
     * @param $rid
     *
     * @return $this
     */
    public function setRecordId(ID $rid)
    {
        return $this->setRid($rid);
    }

    /**
     * @param $rid
     *
     * @return $this
     */
    public function setRid(ID $rid)
    {
        $this->rid = new ORecordId($rid);

        return $this;
    }


    public function setProperty($property,$arguments)
    {
        if(count($arguments) != 1) {
            throw new \Exception("Setter for {$property} requires exactly one parameter.");
        }

        /**
         * @var Metadata $metadata
         */
        $metadata = $this->cm->getMetadata($this);
        $columnType = $metadata->getColumnProperty($property,'Type');
        $colType = $this->typePath . $columnType;
        $propOptions = $metadata->getColumnProperty($property,'Options');

        if ($columnType === 'OLink') {
            if ($arguments[0] instanceof Record) {
                if ($this->ifHasLinkedClass($property)) {
                    $linkedObj = $this->getNameSpace() . $propOptions ['class'];
                    $this->$property = new $linkedObj($this->cm, $arguments[0]);
                } else {
                    $this->$property = $arguments[0]->getRid();
                }
            } else {
                $this->$property = new $colType($arguments[0]);
            }
        }elseif ($columnType === 'OLinkList') {
            $isRecordObject = false;
            if (is_array($arguments[0])) {
                $result = [];

                foreach ($arguments[0] as $argument) {
                    if ($this->ifHasLinkedClass($property)) {
                        if ($argument instanceof Record) {
                            $isRecordObject = true;
                            $linkedObj = $this->getNameSpace() . $propOptions ['class'];
                            $result[] = new $linkedObj($this->cm, $argument);
                        } else {
                            $result[] = $argument;
                        }
                    } else {
                        if ($argument instanceof Record) {
                            $result[] = $argument->getRid();
                        } else {
                            $result[] = $argument;
                        }
                    }
                }
            }
            $this->$property = $isRecordObject ? $result : new $colType($result);
        }elseif($columnType === 'OEmbeddedList') {
            if (is_array($arguments[0])) {
                $newdata = [];

                foreach ($arguments[0] as $item) {
                    $item = $item instanceof Record ? $item->getOdata() : $item;
                    $newdata[] = $item;
                }
                $newdata = $this->sortArray($newdata);

                $this->$property = new $colType($newdata);
            } else {
                $this->$property = new $colType([]);
            }
        }elseif ($columnType === 'ODateTime') {
            if(!is_null($arguments[0])) {
                $this->$property = new $colType($arguments[0] instanceof \DateTime ? $arguments[0] : \DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d H:i:s', strtotime($arguments[0]))), (array_key_exists('embedded', $propOptions) && $propOptions['embedded'] == true) ? true : false);
            }
        }else{
            $this->$property = new $colType($arguments[0]);
        }

    }
    /**
     * @param $name
     * @param $arguments
     *
     * @return BaseClass
     * @throws InvalidRecordIdString
     * @throws \Exception
     */
    public function __call($name, $arguments)
    {
        $prefix = substr($name, 0, 3);
        $property = strtolower($name[3]) . substr($name, 4);
        /**
         * @var Metadata $metadata
         */
        $metadata = $this->cm->getMetadata($this);
        $columnType = $metadata->getColumnProperty($property,'Type');
        $propOptions = $metadata->getColumnProperty($property,'Options');

        if(!property_exists(get_class($this), $property)) {
            $property = strtolower(preg_replace('/([^A-Z])([A-Z])/', "$1_$2", $property));
        }

        switch($prefix) {
            case 'get':
                $colType = $this->typePath . $columnType;
                $onerow = false;



                if (!method_exists($this->$property, 'getValue'))  return $value = $this->$property;

                switch ($columnType) {
                    case 'ODateTime':
                        $value = (isset($propOptions['embedded']) && $propOptions['embedded'] == true) ? $value = $this->$property->setPattern($this->dtFormat)->getValue(true) : $this->$property->getValue();
                        break;
                    default:
                        $value = $this->$property->getValue();
                }

                return $value;
                break;

            case 'set':
                $this->checkUpdatedProb(lcfirst(substr($name, 3)),$arguments[0]);
                $this->setProperty($property,$arguments);
                break;

            default:
                throw new \Exception("Property $name doesn't exist.");
                break;
        }
    }

    private function checkUpdatedProb($property,$value)
    {
        $oldValue = $this->{'get'.ucfirst($property)}();

        $oldValue = $oldValue instanceof BaseType ? $oldValue->getValue() : $oldValue;
        $oldValue = $oldValue instanceof ID ? $oldValue->__toString() : $oldValue;
        $oldValue = $oldValue instanceof Record ? $oldValue->getOData() : $oldValue;
        $oldValue = is_object($oldValue) ? $this->toArray($oldValue) : $oldValue;
        $oldValue = md5(is_array($oldValue) ? json_encode($oldValue) : $oldValue);

        $value = $value instanceof ID ? $value->__toString() : $value;
        $value = is_object($value) ? $this->toArray($value) : $value;
        $value = md5(is_array($value) ? json_encode($value) : $value);

        if($oldValue != $value)
        {
            $this->updatedProps[] = $property;
            $this->modified = true;
        }
    }
    /**
     * @param $entity
     *
     * @return mixed
     */
    private function createRepository($entity)
    {
        return $this->cm->getRepository($entity);
    }

    /**
     * @param $property
     *
     * @return bool
     */
    private function ifHasLinkedClass($property)
    {
        /**
         * @var Metadata $metadata
         */
        $metadata = $this->cm->getMetadata($this);

        $options = $metadata->getColumnProperty($property,'Options');
        if(!is_array($options)) return false;
        if(!array_key_exists('class', $options)) return false;
        if(!array_key_exists('embedded', $options)) return false;
        if(!$options['embedded']) return false;
        return true;
    }

    /**
     * @return string
     */
    private function getNameSpace()
    {
        $reflectionClass = new \ReflectionClass($this);

        return $reflectionClass->getNamespaceName() . '\\';
    }


    final public function getUpdatedProps()
    {
        return $this->updatedProps;
    }
    /**
     * @param string|null $nsRoot
     *
     * @return $this
     */
    private function setDefaults(string $nsRoot = null)
    {
        $nsRoot = $nsRoot ?? $this->typePath;
        /**
         * @var Metadata $metadata
         */
        $metadata = $this->cm->getMetadata($this);
        foreach($metadata->getColumns()->toArray() as $columnIndex => $columnValue) {
            $class = $nsRoot . $metadata->getColumnProperty($columnIndex,'Type');
            $this->$columnIndex = new $class();
        }

        return $this;
    }
}