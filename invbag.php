<?php

class InvBag
{
    protected $sqlite;

    function __construct($sqlite)
    {
        $this->sqlite = $sqlite;
    }

    public function getRandomInventory()
    {
        return $this->sqlite->getRandomInventory();
    }

    public function addRange($invCollection)
    {
        $buffer = array();

        foreach ($invCollection as $inventory) {
            if ($this->sqlite->hasInventory($inventory) === false) {
                $buffer[] = $inventory;
            }

            if (count($buffer) > 300) {
                $this->sqlite->addInventoryRange($buffer);

                $buffer = array();
            }
        }

        if (count($buffer) > 0) {
            $this->sqlite->addInventoryRange($buffer);
        }

        return count($buffer);
    }
}
