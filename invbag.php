<?php

class InvBag
{
    protected $hash = false;
    protected $sqlite;

    function __construct($sqlite)
    {
        $this->sqlite = $sqlite;
    }

    public function getRandomInventory($host)
    {
        $this->hash = $this->hash = $this->sqlite->getRandomInventory($host);

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
            if ($this->sqlite->hasInventory($inventory) === false) {
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

        return $added;
    }

    public function addMessage($binary, $timestamp)
    {
        $this->sqlite->addBinary('Message', $this->hash, $binary, $timestamp);
        $this->sqlite->markInventory($this->hash);

        // TODO - Try to decrypt the message here...if we have privkeys :P
    }

    public function addKey($key, $binary, $keySize)
    {
        $ecc = new Ecc();

        $body = substr($binary, 8);
        $body = substr($body, 0, $keySize);

        if ($ecc->ECDSA($body, $key['signingKey'], $key['ecdsaSignature']) === false) {
            return false;
        }

        $this->sqlite->addBinary('Key', $this->hash, $binary, $key['timestamp']);
        $this->sqlite->markInventory($this->hash);

        return true;
    }
}
