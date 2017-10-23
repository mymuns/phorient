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
     * @var null|ORecord Stores the original Orient Record
     * @JMS\Exclude()
     */
    protected $record;

    /**
     * @var array Holds definition of all public properties of a class for serialization purposes.
     * @JMS\Exclude()
     */
    private $props = [];

    /**
     * @var array Holds definition of all public properties of a class for serialization purposes.
     * @JMS\Exclude()
     */
    private $updatedProps = [];

    /**
     * @var array Holds annotation definitions.
     * @JMS\Exclude()
     */
    private $propAnnotations = [];

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
        $this->prepareProps()->preparePropAnnotations();

        if(isset($this->controller->dateTimeFormat)) {
            $this->dtFormat = $this->controller->dateTimeFormat;
        } else {
            $this->dtFormat = 'd.m.Y H:i:s';
        }
        if(is_null($record)) {
            $this->dateAdded = new \DateTime('now', new \DateTimeZone($timezone));
            $this->record = $record;
            $this->dateUpdated = $this->dateAdded;
            $this->setDefaults();
        } else {
            $this->convertRecordToOdmObject($record);
        }

        $this->versionHash = md5($this->output('json'));
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
        $this->versionHistory[] = $this->output('json');

        if($this->versionHash !== $this->getUpdatedVersionHash() && !$this->modified) {
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
        return md5($this->output('json'));
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

    /**
     * @param ORecord $record
     */
    public function convertRecordToOdmObject(ORecord $record)
    {
        $this->rid = new ORecordId($record->getRid());
        $recordData = $record->getOData();

        foreach($this->propAnnotations as $propName => $propAnnotations) {
            if($propName == 'rid') {
                continue;
            }

            foreach($propAnnotations as $propAnnotation) {
                if($propAnnotation instanceof Column) {
                    if(array_key_exists($propName, $recordData)) {
                        $this->setProperty($propName,array($recordData[$propName]));
                    } else {
                        $this->setProperty($propName,array(null));
                    }
                }
            }
        }
    }

    public function setProperty($property,$arguments)
    {
        if(count($arguments) != 1) {
            throw new \Exception("Setter for {$property} requires exactly one parameter.");
        }

        $columnType = $this->getColumnType($property);
        $colType = $this->typePath . $columnType;
        $propOptions = $this->getColumnOptions($property);

        $onerow = false;

        if ($columnType === 'OLink') {
            if ($arguments[0] instanceof Record) {
                if ($this->ifHasLinkedClass($property)) {
                    $linkedObj = $this->getNameSpace() . $this->getColumnOptions($property) ['class'];
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
                            $linkedObj = $this->getNameSpace() . $this->getColumnOptions($property) ['class'];
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
        $columnType = $this->getColumnType($property);

        if(!property_exists(get_class($this), $property)) {
            $property = strtolower(preg_replace('/([^A-Z])([A-Z])/', "$1_$2", $property));
        }

        switch($prefix) {
            case 'get':
                $colType = $this->typePath . $columnType;
                $onerow = false;

                $propOptions = $this->getColumnOptions($property);

                switch ($this->getColumnType($property)) {
                    case 'ODateTime':
                        dump($property);die;
                        if (isset($propOptions['embedded']) && $propOptions['embedded'] == true) {
                            $value = $this->$property->setPattern($this->dtFormat)->getValue(true);
                        }
                        break;

                    default:
                        $value=$this->$property->getValue();
                        break;
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
        $options = $this->getColumnOptions($property);
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

    /**
     * @return $this
     */
    final private function prepareProps()
    {
        $reflectionClass = new \ReflectionClass($this);
        $this->props = $reflectionClass->getProperties(\ReflectionProperty::IS_PUBLIC);

        return $this;
    }

    /**
     * @return $this
     */
    final private function preparePropAnnotations()
    {
        $annoReader = new AnnotationReader();
        foreach($this->props as $aProperty) {
            $aPropertyReflection = new \ReflectionProperty(get_class($this), $aProperty->getName());
            $this->propAnnotations[$aProperty->getName()] = $annoReader->getPropertyAnnotations($aPropertyReflection);
        }

        return $this;
    }

    /**
     * @param $propertyName
     *
     * @return mixed
     * @throws \Doctrine\Common\Annotations\AnnotationException
     */
    public function getColumnDefinition($propertyName)
    {
        if(!$this instanceof BaseClass) throw new AnnotationException();
        $aPropertyReflection = new \ReflectionProperty(get_class($this), $propertyName);
        $annoReader = new AnnotationReader();
        $propAnnotations = $annoReader->getPropertyAnnotations($aPropertyReflection);

        foreach($propAnnotations as $aPropAnnotation) {
            if($aPropAnnotation instanceof Column) {
                return $aPropAnnotation;
            }
        }

        throw new AnnotationException();
    }

    /**
     * @param $propertyName
     *
     * @return mixed
     * @throws \Doctrine\Common\Annotations\AnnotationException
     */
    public function getColumnType($propertyName)
    {
        $colDef = $this->getColumnDefinition($propertyName);

        return $colDef->type;
    }

    /**
     * @param $propertyName
     *
     * @return mixed
     * @throws \Doctrine\Common\Annotations\AnnotationException
     */
    public function getColumnOptions($propertyName)
    {
        $colDef = $this->getColumnDefinition($propertyName);

        return isset($colDef->options) ? $colDef->options : false;
    }

    /**
     * @param string     $to
     * @param array|null $props
     *
     * @return string
     */
    public function output($to = 'json', array $props = null)
    {
        switch($to) {
            case 'json':
                return $this->outputToJson($props);
            case 'xml':
                return $this->outputToXml($props);
            case 'array':
                return json_decode($this->outputToJson($props), true);
        }
    }

    /**
     * @param array|null $props
     *
     * @return \stdClass
     */
    public function getRepObject(array $props = null)
    {
        //$props = $props ?? $this->props;
        $objRepresentation = new \stdClass();
        if(isset($this->controller->dateTimeFormat)) {
            $dtFormat = $this->controller->dateTimeFormat;
        } else {
            $dtFormat = 'd.m.Y H:i:s';
        }

        foreach($this->props as $aProperty) {
            $propName = $aProperty->getName();
            $propOptions = $this->getColumnOptions($propName);

            if(!is_null($props) && !in_array($propName, $props)) {
                continue;
            }

            if(!is_null($this->$propName)) {

                if (method_exists($this->$propName, 'getValue')) {

                    if (is_array($this->$propName->getValue())) {
                        $collection = [];

                        foreach ($this->$propName->getValue() as $anItem) {
                            if ($anItem instanceOf ID) {
                                $collection[] = '#' . $anItem->cluster . ':' . $anItem->position;
                            } else if ($anItem instanceOf \DateTime) {
                                $collection[] = $anItem->format($dtFormat);
                            } else if (is_object($anItem) && method_exists($anItem, 'getValue')) {
                                $collection[] = $anItem->getValue();
                            } else {
                                $collection[] = $anItem;
                            }
                            $objRepresentation->$propName = $collection;
                        }
                    } else if ($this->$propName->getValue() instanceOf \DateTime) {
                        $objRepresentation->$propName = $this->$propName->getValue()->format($dtFormat);
                    } else if ($this->$propName->getValue() instanceOf ID) {
                        if ($this->getColumnType($propName) == 'OLink' && isset($propOptions['embedded']) && $propOptions['embedded'] == true) {
                            $objRepresentation->$propName = $this->$propName->getValue(true)->getRepObject($props);
                        } else {
                            $idObj = $this->$propName->getValue();
                            $objRepresentation->$propName = '#' . $idObj->cluster . ':' . $idObj->position;
                        }
                    } else {
                        $propType = gettype($this->$propName->getValue());
                        $propObj = $this->$propName->getValue();

                        if ($propType == 'object' && method_exists($propObj, 'getRepObject')) {
                            $objRepresentation->$propName = $this->$propName->getValue()->getRepObject($props);
                        } else if ($propType == 'object' && !method_exists($propObj, 'getRepObject')) {
                            $objRepresentation->$propName = json_decode(json_encode($this->$propName->getValue()));
                        } else {
                            $propType = $this->getColumnType($propName);
                            $value = in_array($propType, ['OEmbeddedList', 'OLinkList', 'OEmbeddedSet']) && ($this->$propName->getValue() == null || empty($this->$propName->getValue())) ? [] : $this->$propName->getValue();
                            $objRepresentation->$propName = $value;
                        }
                    }

                }else{

                    switch ($this->getColumnType($propName)) {

                        case 'OLink':
                            if (isset($propOptions['embedded']) && $propOptions['embedded'] == true) {
                                $value =  $this->$propName->getRepObject();
                            }else{
                                $value =  '#' . $this->$propName->cluster . ':' . $this->$propName->position;
                            }
                            break;

                        case 'OLinkList':
                            $value = [];
                            if (is_array($this->$propName) && count($this->$propName) > 0) {
                                foreach ($this->$propName as $propValue) {
                                    if (isset($propOptions['embedded']) && $propOptions['embedded'] == true) {
                                        if($propValue instanceof BaseClass)
                                        {
                                            $value[] =  $propValue->getRepObject();
                                        }
                                    }else{
                                        if($propValue instanceof ID)
                                        {
                                            $value[] =  '#' . $propValue->cluster . ':' . $propValue->position;
                                        }
                                    }
                                }
                            }
                            break;

                        default:
                            $value = $propValue;
                            break;
                    }

                    $objRepresentation->$propName = $value;

                }

            } else {
                $propType = $this->getColumnType($propName);
                if(in_array($propType, [ 'OEmbeddedList', 'OLinkList', 'OEmbeddedSet' ])) {
                    $value = [];
                }else{
                    $value = null;
                }

                $objRepresentation->$propName = $value;
            }
        }

        return $objRepresentation;
    }

    /**
     * @param array $props
     *
     * @return string
     */
    final private function outputToJson(array $props = null)
    {
        //return json_encode($this->getRepObject($props));
        return json_encode($this->toArray());
    }

    public function getToMapProperties($object)
    {
        return array_diff_key(get_object_vars($object), array_flip(array(
            'index', 'parent','modified','versionHash','record','props','cm','typePath','propAnnotations','updatedProps', 'dateAdded', 'dateRemoved', 'versionHistory','dtFormat'
        )));
    }

    public function sortArray($array)
    {
        if(is_object($array) || is_array($array))
        {
            $array = (array) $array;
            foreach ($array as $index => $value)
            {
                $array[$index] = $this->sortArray($value);
            }
            ksort($array);
        }
        return $array;
    }

    public function toArray($object = null)
    {
        $object = $object == null ? $object = $this : $object;
        $array = $object instanceof BaseClass ? $object->getToMapProperties($object) : get_object_vars($object);
        array_walk_recursive($array, function (&$value, $index) use($object) {
            $value = $value instanceof BaseType ? (method_exists($object,'get'.ucfirst($index)) ? $object->{'get'.ucfirst($index)}() : $value->getValue()) : $value;
            $value = $value instanceof ID ? '#'.$value->cluster.':'.$value->position : $value;
            if ($value instanceof BaseClass) {
                $value = $value->toArray();
            }else{
                $value = is_object($value) || is_array($value) ? (array) $value : $value;
            }
        });
        $this->sortArray($array);
        return $array;
    }
    /**
     * @param array $props
     *
     * @return string
     *
     * @todo !! BE AWARE !! xmlrpc_encode is an experimental method.
     */
    final private function outputToXml(array $props = null)
    {
        return xmlrpc_encode($this->getRepObject($props));
    }

    /**
     * @return array
     */
    final public function getProps()
    {
        return $this->props;
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

        foreach($this->props as $aProperty) {
            $propName = $aProperty->getName();
            $colType = $this->getColumnType($propName);
            $class = $nsRoot . $colType;
            $this->$propName = new $class();
        }

        return $this;
    }
}