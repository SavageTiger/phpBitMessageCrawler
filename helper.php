<?php

class Helper
{
    public function decodeVarInt($input)
    {
        // TODO
        $int = unpack('C', $input[0]);
        $int = current($int);

        if ($int >= 253) {
            if ((int)$int === 253) {
                $int = unpack('n', substr($input, 1, 2));

                return current($int);
            } else {
                die('unsupported...'); // XXX
            }
        }

        return $int;
    }

    public function varInt($input)
    {
        // TODO
        return pack('C', $input);
    }

    public function pack_double($in, $pad_to_bits = 64, $little_endian = true)
    {
        $in = decbin($in);
        $in = str_pad($in, $pad_to_bits, '0', STR_PAD_LEFT);
        $out = '';

        for ($i = 0, $len = strlen($in); $i < $len; $i += 8) {
            $out .= chr(bindec(substr($in,$i,8)));
        }

        if($little_endian) $out = strrev($out);

        return $out;
    }
}
