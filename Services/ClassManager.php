<?php
/**
 * Created by PhpStorm.
 * User: erman.titiz
 * Date: 12.06.2017
 * Time: 14:22
 */

namespace BiberLtd\Bundle\Phorient\Services;


use BiberLtd\Bundle\Phorient\Odm\Repository\BaseRepository;
use Doctrine\Common\Annotations\AnnotationReader;
use PhpOrient\PhpOrient;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ClassManager
{

    private $oService;
    private $cRepositoryFactory;
    private $config;
    private $annotationReader;
    private $container;
    public $currentDb;
    private $entityPath;

    public function __construct(ContainerInterface $container =  null, CMConfig $config=null)
    {
        $this->config = $config;
        $this->container = $container;
        $this->cRepositoryFactory = new ClassRepositoryFactory();
        $this->annotationReader = new AnnotationReader();

    }

    public function getAnnotationReader()
    {
        return $this->annotationReader;
    }
    public function createConnection($dbName,$dbInfo=null)
    {
        if($dbInfo==null)
        {
            $dbInfo =  $this->container->get('service_container')->getParameter('orientdb');
            unset($container);
            if(!isset($dbInfo['database'][$dbName])){
                throw new \Exception("Please check your parameters.yml for Orient Database connection");
            }
        }
        $this->config[$dbName] = new CMConfig();
        $this->config[$dbName]->setHost($dbInfo['database'][$dbName]['hostname']);
        $this->config[$dbName]->setPort($dbInfo['database'][$dbName]['port']);
        $this->config[$dbName]->setToken($dbInfo['database'][$dbName]['token']);
        $this->config[$dbName]->setDbUser($dbInfo['database'][$dbName]['username']);
        $this->config[$dbName]->setDbPass($dbInfo['database'][$dbName]['password']);

        $this->oService[$dbName] = new PhpOrient($this->config[$dbName]->getHost(), $this->config[$dbName]->getPort(), $this->config[$dbName]->getToken());
        $this->oService[$dbName]->connect($this->config[$dbName]->getDbUser(), $this->config[$dbName]->getDbPass());
        $this->oService[$dbName]->dbOpen($dbName, $this->config[$dbName]->getDbUser(), $this->config[$dbName]->getDbPass());
        return $this->setConnection($dbName);
    }

    public function setConnection($dbName)
    {
        $this->currentDb = $dbName;
        return $this;
    }
    public function getConnection($dbName=null)
    {
        return $this->oService[$dbName ?? $this->currentDb];
    }

    /**
     * @param $entityName
     * @return BaseRepository
     */
    public function getRepository($entityName)
    {
        return $this->cRepositoryFactory->getRepository($this,$entityName);

    }

    public function setEntityPath($bundleName,$path)
    {
        $this->entityPath[$bundleName]=$path;
    }

    public function getEntityPath($bundleName)
    {
        return $this->entityPath[$bundleName];
    }
}