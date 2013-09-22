<?php

class InvBag
{
    protected $ecc = null;
    protected $hash = false;
    protected $sqlite;

    function __construct($sqlite)
    {
        $this->ecc = new Ecc();
        $this->sqlite = $sqlite;
    }

    public function getRandomInventory($host)
    {
        $this->hash = $this->hash = $this->sqlite->getRandomInventory($host);
return hex2bin('cddfcd9ea2435b667f6bd25c01edcce26e72d639d071ff28626e640fc9316e82');
        return $this->hash;
    }

    public function getHash()
    {
        return $this->hash;
    }

    public function resetHash()
    {
        $this->hash = false;
    }

    public function addRange($invCollection, $host)
    {
        $added = 0;
        $buffer = array();

        foreach ($invCollection as $inventory) {
            if ($this->sqlite->hasInventory($inventory, $host) === false) {
                $buffer[] = $inventory;
            }

            if (count($buffer) > 300) {
                $this->sqlite->addInventoryRange($buffer, $host);

                $added += count($buffer);
                $buffer = array();
            }
        }

        if (count($buffer) > 0) {
            $this->sqlite->addInventoryRange($buffer, $host);

            $added += count($buffer);
        }

        $this->sqlite->executeCache();

        return $added;
    }

    public function addMessage($binary, $timestamp)
    {
        $this->sqlite->addBinary('Message', $this->hash, $binary, $timestamp);
        $this->sqlite->markInventory($this->hash);

        // TODO - Try to decrypt the message here...if we have privkeys :P
    }

    public function addBroadcast($binary, $timestamp, $headerSize)
    {
        $this->ecc->decrypt(substr($binary, $headerSize));

        $this->sqlite->addBinary('Broadcast', $this->hash, $binary, $timestamp);
        $this->sqlite->markInventory($this->hash);
    }

    public function addKey($key, $binary, $keySize)
    {
        // Note: As long as version 2 pubkeys are supported key signing is basicly useless
        if ($key['version'] === 3) {
            $body = substr($binary, 8);
            $body = substr($body, 0, $keySize);

            if ($this->ecc->ECDSA($body, $key['signingKey'], $key['ecdsaSignature']) === false) {
                return false;
            }
        }

        if ($key['version'] > 3) {
            return false;
        }

        $this->sqlite->addBinary('Key', $this->hash, $binary, $key['timestamp']);
        $this->sqlite->markInventory($this->hash);

        return true;
    }
}
