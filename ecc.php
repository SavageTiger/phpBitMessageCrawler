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

    public function ECDSA($binary, $key, $signature)
    {
        $signature = $this->decodeSignature($signature);
        $signature = new Signature(
            gmp_Utils::gmp_hexdec('0x' . $signature['r']),
            gmp_Utils::gmp_hexdec('0x' . $signature['s'])
        );

        $pubKey = $this->loadPublicKey($key);

        return $pubKey->verifies($this->digest_integer($binary), $signature);
    }
    
    public function Decrypt($binary)
    {
        // https://bitmessage.org/wiki/Protocol_specification#Encrypted_payload    
        $initVector = substr($binary, 0, 16);
        
        $binary = substr($binary, 16);
        $pubKey = $this->decodePubkey($binary);

        if ($pubKey['curveType'][1] === 714) {
            $body = substr($binary, $pubKey['len']);
            //$body = substr($body, 0, strlen($body) - 32);

            $ecDh = new EcDH(
                new Point(
                    $this->secp256k1,
                    gmp_Utils::gmp_hexdec('0x' . $pubKey['x']),
                    gmp_Utils::gmp_hexdec('0x' . $pubKey['y'])              
                )
            );

            die(var_dump($ecDh->decrypt($body, $initVector)));
        }
        
        return false;
    }

    private function digest_integer($message)
    {
        // Copy't from the phpecc library, not sure why, there implementation might be broken
        // That was a nice 5 hours of searching :P
        return PrivateKey::string_to_int(hash('sha1', $message, true));
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
            'len' => (strlen($x) + 6 + $pointLength[1]),
            'curveType' => $curveType,
            'x' => bin2hex($x),
            'y' => bin2hex($y)
        );
    }
}
