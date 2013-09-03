<?php

class IpBag
{
    protected $store = array();

    function __construct()
    {
        if (file_exists(__DIR__ . '/knownhosts.txt')) {
            $this->load();
        }
    }

    public function add($ip, $port)
    {
        if (isset($this->store[$ip]) === false) {
            $this->store[$ip] = array('ip' => $ip, 'port' => $port, 'timestamp' => time());

            return true;
        }

        return false;
    }

    public function write()
    {
        file_put_contents(__DIR__ . '/knownhosts.txt', serialize($this->store));

        return true;
    }

    protected function load()
    {
        $content = file_get_contents(__DIR__ . '/knownhosts.txt');

        return ($this->store = unserialize($content));
    }
}
