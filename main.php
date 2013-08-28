<?php

require('logger.php');
require('protocol.php');

$host = "127.0.0.1";
$port = 8444;

$protocol = new Protocol();

if ($socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) {
    if (socket_connect($socket, $host, $port)) {
        $data = $protocol->generateVersionPackage($host, $port, 8444);
        
        socket_send($socket, $data, strlen($data), 0);

        while (true) {
            if (socket_recv($socket, $buffer, 1024, 0)) {
                if ($protocol->recievePackage($buffer, $socket) === false) {
                    die('Error' . "\r\n");             
                }
            }
        }
    }
}

