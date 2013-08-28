<?php

class Logger
{
    public function Log($message) {
        echo '[' . time() . ']' . "\t" . $message . "\r\n";
    }
}
