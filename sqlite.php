<?php

class SQLite
{
    protected $connection = null;

    function __construct()
    {
        if (class_exists('sqlite3')) {
            $sqlite = new SQLite3('store.sqlite', SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);

            $this->connection = $sqlite;
            $this->initialize();
        } else {
            trigger_error('SQLite3 PHP extension is not installed', E_USER_ERROR);
        }
    }

    public function hasInventory($inventory)
    {
        if ($this->connection->querySingle('SELECT ID FROM Inventory WHERE Hash = X\'' . bin2hex($inventory) . '\' LIMIT 1') === NULL) {
            return false;
        }

        return true;
    }

    public function hasIp($ip)
    {
        if ($this->connection->querySingle('SELECT ID FROM Known_Hosts WHERE IP = ' . ip2long($ip) . ' LIMIT 1') === NULL) {
            return false;
        }

        return true;
    }

    public function addIpRange($ipCollection)
    {
        $query = 'INSERT INTO Known_Hosts (IP, Port, Timestamp) VALUES ';

        foreach ($ipCollection as $address) {
            $query .= ' (' . ip2long($address['ip']) . ', ' . (int)$address['port'] . ', ' . time() . '),';
        }

        $query = substr($query, 0, -1);

        return $this->connection->exec($query);
    }

    public function addInventoryRange($inventoryCollection, $host)
    {
        $query = 'INSERT INTO Inventory (Hash, Host, Timestamp) VALUES ';

        foreach ($inventoryCollection as $inventory) {
            $query .= ' (X\'' . bin2hex($inventory) . '\', ' . ip2long($host) . ', ' . time() . '),';
        }

        $query = substr($query, 0, -1);

        return $this->connection->exec($query);
    }

    public function getRandomInventory($host)
    {
        $query = 'SELECT Hash FROM Inventory WHERE Host = ' . ip2long($host) . ' AND InStore = 0 ORDER BY RANDOM() LIMIT 1';
        $result = $this->connection->querySingle($query);

        return $result;
    }

    public function addBinary($type, $hash, $binary, $timestamp)
    {
        $query = 'SELECT ID FROM Inventory WHERE Hash = X\'' . bin2hex($hash) . '\' LIMIT 1';
        $result = $this->connection->querySingle($query);

        $query  = 'INSERT INTO ' . $type . 'Store (Inventory, Binary, Timestamp) VALUES ';
        $query .= '(\'' . $result . '\', X\'' . bin2hex($binary) . '\', ' . $timestamp . ')';

        return $this->connection->exec($query);
    }

    public function markInventory($hash)
    {
        return $this->connection->exec('UPDATE Inventory SET InStore = 1 WHERE Hash = X\'' . bin2hex($hash) . '\'');
    }

    protected function initialize()
    {
        @$this->connection->exec('SELECT ID FROM Known_Hosts LIMIT 1');

        if ($this->connection->lastErrorCode() !== 0) {
            $sqlSchema = file_get_contents('store.schema.sql');
            $sqlSchema = explode(';', $sqlSchema);

            foreach ($sqlSchema as $query) {
                $this->connection->exec($query);
            }
        }
    }
}
