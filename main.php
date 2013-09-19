<?php

$host = "127.0.0.1";
$port = 8444;

$sqlite = new SQLite();
$protocol = new Protocol($sqlite);


if ($socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) {

    socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec'=> 10, 'usec' => 0));

    $socket = array('host' => $host, 'port' => $port, 'socket' => $socket);

    if (socket_connect($socket['socket'], $host, $port)) {
        $operations = 0;
        $data = $protocol->generateVersionPackage($host, $port, 8444);

        socket_send($socket['socket'], $data, strlen($data), 0);

        while (true) {
            if (socket_recv($socket['socket'], $buffer, 24, 0)) {
                if ($protocol->recievePackage($buffer, $socket) === false) {
                    die('Error' . "\r\n");
                }

                $operations++;

                if ($operations > 50) {
                    $sqlite->executeCache();

                    $operations = 0;
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

function __autoload($class)
{
    $pathList = array(
        './',
        './phpecc/classes/',
        './phpecc/classes/interface/',
        './phpecc/classes/util/',
    );

    foreach ($pathList as $path) {
        $classPath = $path . str_replace('..', '', strtolower($class)) . '.php';

        if (file_exists($classPath)) {
            require($classPath);

            return;
        }

        $classPath = $path . str_replace('..', '', $class) . '.php';

        if (file_exists($classPath)) {
            require($classPath);

            return;
        }
    }
}
