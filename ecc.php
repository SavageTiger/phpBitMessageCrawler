<?php

class Ecc
{
    function __construct()
    {
        if (extension_loaded('gmp') === false) {
            trigger_error('PHP GMP extension required for ECC', E_USER_ERROR);
        }
        
        if (!defined('USE_EXT')) {
            define('USE_EXT', 'GMP');
        }
    }

    function ECDSA($binary, $key, $signature)
    {
        $key =
            hex2bin('02ca0020') .
            substr($key, 0, 32) .
            hex2bin('0020') .
            substr($key, 32, 32);

        //$p256 = NISTcurve::generator_256();
        $pubKey = $this->decodePubKey($key);
    }
       
    protected function decodePubKey($key)
    {
        $curve = substr($key, 0, 2);
        $curve = unpack('n', $curve);      

        $pointLength = substr($key, 2, 4);
        $pointLength = unpack('n', $pointLength);

        $x = substr($key, 4, $pointLength[1]);
        
        $pointLength = substr($key, $pointLength[1] + 4);
        $pointLength = unpack('n', $pointLength);

        $y = substr($key, strlen($x) + 6, $pointLength[1]);
        
        return array(
            'curve' => $curve,
            'x' => $x,
            'y' => $y
        );
    }
} 
