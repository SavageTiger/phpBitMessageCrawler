<?php

$host = "127.0.0.1";
$port = 8444;

$sqlite = new SQLite();
$protocol = new Protocol($sqlite);

if ($socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) {

    $socket = array('host' => $host, 'port' => $port, 'socket' => $socket);

    if (socket_connect($socket['socket'], $host, $port)) {
        $data = $protocol->generateVersionPackage($host, $port, 8444);

        socket_send($socket['socket'], $data, strlen($data), 0);

        while (true) {
            if (socket_recv($socket['socket'], $buffer, 32, 0)) {
                if ($protocol->recievePackage($buffer, $socket) === false) {
                    die('Error' . "\r\n");
                }
            }

            // Just for testing, sending should get its own thread
            if ($protocol->isAccepted()) {
                if ($protocol->sendPackage($socket)) {
                    // Dum dum duuuuhhmm...
                }
            }
        }
    }
}

function __autoload($class) {
    $class = str_replace('..', '', strtolower($class)) . '.php';

    require($class);
}
