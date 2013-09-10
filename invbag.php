<?php

class InvBag
{
    protected $sqlite;

    function __construct($sqlite)
    {
        $this->sqlite = $sqlite;
    }

    public function getRandomInventory($host)
    {
        return $this->sqlite->getRandomInventory($host);
    }

    public function addRange($invCollection, $host)
    {
        $buffer = array();

        foreach ($invCollection as $inventory) {
            if ($this->sqlite->hasInventory($inventory) === false) {
                $buffer[] = $inventory;
            }

            if (count($buffer) > 300) {
                $this->sqlite->addInventoryRange($buffer, $host);

                $buffer = array();
            }
        }

        if (count($buffer) > 0) {
            $this->sqlite->addInventoryRange($buffer, $host);
        }

        return count($buffer);
    }
}
