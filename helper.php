<?php

class Helper
{
    protected $payloadLengthExtra = 14000;
    protected $payloadProofTrials = 320;

    public function decodeVarInt($input)
    {
        $int = unpack('C', $input[0]);
        $int = current($int);

        if ($int >= 253) {
            if ((int)$int === 253) {
                $int = unpack('n', substr($input, 1, 2));

                return array('len' => 3, 'int' => current($int));
            } else if ((int)$int === 254) {
                $int = unpack('N', substr($input, 1, 5));

                return array('len' => 5, 'int' => current($int));
            }
        }

        return array('len' => 1, 'int' => $int);
    }

    public function varInt($input)
    {
        // TODO
        return pack('C', $input);
    }

    public function convertTime($payload)
    {
        $timestamp = substr($payload, 0, 4);
        $timestamp = $this->unpack_double($timestamp, false);

        if ($timestamp == 0) {
            $timestamp = substr($payload, 0, 8);
            $timestamp = $this->unpack_double($timestamp, false);
        }

        return $timestamp;
    }

    public function checkPOW($payload)
    {
        $nonce = substr($payload, 0, 8);
        $payload = substr($payload, 8);
        $hash = hash('sha512', $payload, true);

        $pow = hash('sha512', hash('sha512', $nonce . $hash, true), true);
        $pow = substr($pow, 0, 8);
        $pow = $this->unpack_double($pow, false);

        $target = pow(2, 64) / ((strlen($payload) + $this->payloadLengthExtra) * $this->payloadProofTrials);

        if ($pow <= $target) {
            return true;
        }

        return false;
    }

    public function pack_double($in, $pad_to_bits = 64, $little_endian = true)
    {
        $in = decbin($in);
        $in = str_pad($in, $pad_to_bits, '0', STR_PAD_LEFT);
        $out = '';

        for ($i = 0, $len = strlen($in); $i < $len; $i += 8) {
            $out .= chr(bindec(substr($in, $i, 8)));
        }

        if($little_endian) {
            $out = strrev($out);
        }

        return $out;
    }

	function unpack_double($bytes, $little_endian = true)
    {
        if ($little_endian) {
            $bytes = strrev($bytes);
        }

        $result = '0';

        while (strlen($bytes)) {
            $ord = ord(substr($bytes, 0, 1));
            $result = bcadd(bcmul($result, 256), $ord);
            $bytes = substr($bytes, 1);
        }

        return $result;
    }
}
