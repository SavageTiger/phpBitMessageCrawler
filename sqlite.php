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
