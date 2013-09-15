<?php

class Ecc
{
    protected $secp256k1;
    protected $secp256k1_G;

    function __construct()
    {
        if (extension_loaded('gmp') === false) {
            trigger_error('PHP GMP extension required for ECC', E_USER_ERROR);
        }

        if (!defined('USE_EXT')) {
            define('USE_EXT', 'GMP');
        }

        $this->secp256k1 = new CurveFp(
            '115792089237316195423570985008687907853269984665640564039457584007908834671663',
            '0', '7'
        );

        $this->secp256k1_G = new Point(
            $this->secp256k1,
            '55066263022277343669578718895168534326250603453777594175500187360389116729240',
            '32670510020758816978083085130507043184471273380659243275938904335757337482424',
            '115792089237316195423570985008687907852837564279074904382605163141518161494337'
        );
    }

    function ECDSA($binary, $key, $signature)
    {
        $signature = $this->decodeSignature($signature);
        $signature = new Signature(
            gmp_Utils::gmp_hexdec('0x' . $signature['r']),
            gmp_Utils::gmp_hexdec('0x' . $signature['s'])
        );

        $pubKey = $this->loadPublicKey($key);

        if ($pubKey->verifies(PrivateKey::digest_integer($binary), $signature) == false) {
            die ('epic fail...');
        }
    }

    protected function loadPublicKey($key)
    {
        $key =
            hex2bin('02ca0020') .
            substr($key, 1, 32) .
            hex2bin('0020') .
            substr($key, 33, 32);

        $pubKey = $this->decodePubKey($key);
        $pubKey = new PublicKey(
            $this->secp256k1_G,
            new Point(
                $this->secp256k1,
                gmp_Utils::gmp_hexdec('0x' . $pubKey['x']),
                gmp_Utils::gmp_hexdec('0x' . $pubKey['y'])
            )
        );

        return $pubKey;
    }

    protected function decodeSignature($signature)
    {
        $rLength = unpack('C', substr($signature, 3, 1));
        $rLength = $rLength[1];

        $r = substr($signature, 4, $rLength);

        $sLength = unpack('C', substr($signature, 5 + $rLength, 1));
        $sLength = $sLength[1];

        $s = substr($signature, 6 + $rLength, $sLength + 1);

        return array(
            'r' => bin2hex($r),
            's' => bin2hex($s)
        );
    }

    protected function decodePubKey($key)
    {
        $curveType = substr($key, 0, 2);
        $curveType = unpack('n', $curveType);

        $pointLength = substr($key, 2, 2);
        $pointLength = unpack('n', $pointLength);

        $x = substr($key, 4, $pointLength[1]);

        $pointLength = substr($key, $pointLength[1] + 4);
        $pointLength = unpack('n', $pointLength);

        $y = substr($key, strlen($x) + 6, $pointLength[1]);

        return array(
            'curveType' => $curveType,
            'x' => bin2hex($x),
            'y' => bin2hex($y)
        );
    }
}
