<?php

class IpBag
{
    protected $store = array();
    protected $sqlite = null;

    function __construct($sqlite)
    {
        $this->sqlite = $sqlite;
    }

    public function add($ip, $port)
    {
        if ($this->sqlite->hasIp($ip) === false) {
            $this->store[] = array('ip' => $ip, 'port' => $port);

            return true;
        }

        return false;
    }

    public function commit()
    {
        $this->sqlite->addIpRange($this->store);
        $this->store = array();

        return true;
    }
}
