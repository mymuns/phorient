<?php
/**
 * Created by PhpStorm.
 * User: erman.titiz
 * Date: 15.06.2017
 * Time: 11:44
 */

namespace BiberLtd\Bundle\Phorient\Services;


class CMConfig
{

    private $host;
    private $port;
    private $token;
    private $db_user;
    private $db_pass;

    /**
     * CMConfig constructor.
     * @param $host
     * @param $port
     * @param $token
     * @param $db_user
     * @param $db_pass
     */
    public function __construct($host='localhost', $port='2424', $token='', $db_user='root', $db_pass='root')
    {
        $this->host = $host;
        $this->port = $port;
        $this->token = $token;
        $this->db_user = $db_user;
        $this->db_pass = $db_pass;
    }


    /**
     * @return mixed
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @param mixed $host
     * @return $this
     */
    public function setHost($host)
    {
        $this->host = $host;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @param mixed $port
     * @return $this
     */
    public function setPort($port)
    {
        $this->port = $port;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @param mixed $token
     * @return $this
     */
    public function setToken($token)
    {
        $this->token = $token;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDbUser()
    {
        return $this->db_user;
    }

    /**
     * @param mixed $db_user
     * @return $this
     */
    public function setDbUser($db_user)
    {
        $this->db_user = $db_user;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDbPass()
    {
        return $this->db_pass;
    }

    /**
     * @param mixed $db_pass
     * @return $this
     */
    public function setDbPass($db_pass)
    {
        $this->db_pass = $db_pass;
        return $this;
    }


}