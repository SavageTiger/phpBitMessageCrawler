<?php

class Protocol
{
    protected $logger = null;
    protected $helper = null;

    public function __construct()
    {
        $this->logger = new Logger;
        $this->helper = new Helper;
    }

    public function generateVersionPackage($remoteIP, $remotePort, $localPort)
    {
        $streamNumber = 1;

        $payload = '';
        $payload .= pack('N', 2); // Version
        $payload .= $this->helper->pack_double(1, 64, false); // Bitflags
        $payload .= $this->helper->pack_double(time(), 64, false); // Bitflags

        // Remote IP
        $payload .= $this->helper->pack_double(1, 64, false); // Remote bitflags (what a odd this to send...)
        $payload .= hex2bin('00000000000000000000FFFF');
        $payload .= pack('N', ip2long($remoteIP));
        $payload .= pack('n', $remotePort);

        // Local IP
        $payload .= $this->helper->pack_double(1, 64, false); // Bitflags again?
        $payload .= hex2bin('00000000000000000000FFFF');
        $payload .= pack('N', ip2long('127.0.0.1')); // Ignored by remote client anyway...
        $payload .= pack('n', $localPort);

        $payload .= $this->helper->pack_double(rand(919191, 2929292992), 64, false); // Random

        // User agent
        $agent = 'phpBitMessageCrawler/0.1';
        $payload .= $this->helper->varInt(strlen($agent));
        $payload .= $agent;

        // Stream number
        $payload .= $this->helper->varInt(1);
        $payload .= $this->helper->varInt($streamNumber);

        // Build a package
        return $this->buildPackage($payload);
    }

    public function recievePackage($data, $socket)
    {
        $magic = bin2hex(substr($data, 0, 4));
        $hash = bin2hex(substr($data, 20, 2));
        $size = unpack('N', substr($data, 16, 4));
        $size = current($size);
        $type = str_replace("\0", '', substr($data, 4, 12));

        $this->logger->log('Incomming package [Type: ' . $type . '] [Hash: ' . $hash . '] [Size: ' . $size . ']');

        if ($magic !== 'e9beb4d9') {
            $this->logger->log('Invalid magic');

            return false;
        }

        // Big package, download the rest otherwise the hash checksum will be incorrect and the
        // payload to short to process.
        if ($size > 1024) {
            $downloadSize = $size - strlen($data);

            while ($downloadSize > 0) {
                    if (socket_recv($socket, $buffer, 1024, 0)) {
                        $data .= $buffer;
                    } else {
                        return false;
                    }

                $downloadSize = $downloadSize - 1024;
            }
        }

        $payload = substr($data, 24, $size);

        if (substr(hash('sha512', $payload), 0, 4) !== $hash) {
            $this->logger->log('Invalid hash');

            return false;
        }

        switch ($type) {
            case 'verack':
                $this->logger->log('My version is accepted');
                break;

            case 'addr':
                $this->processAddr($payload);
                break;

            case 'version':
                $this->checkRemoteVersion($payload, $socket);
                break;
        }
    }

    protected function checkRemoteVersion($payload, $socket)
    {
        // Check version (should be 2 or higher).
        $version = unpack('N', substr($payload, 0, 4));
        $version = current($version);

        if ($version >= 2) {
            // Read the destination IP
            $ip = unpack('N', substr($payload, 40, 44));
            $ip = long2ip(current($ip));

            // Read the remote useragent
            $stringSize = $this->helper->decodeVarInt(substr($payload, 80, 84));
            $agent = substr($payload, 81, $stringSize);

            $this->logger->log('Remote version is accepted [agent: ' . $agent . '] [version: ' . $version . '] [destination: ' . $ip . ']');

            // Send a verack for good measure
            $data = $this->buildPackage('', 'verack');
            socket_send($socket, $data, strlen($data), 0);
        }
    }

    protected function processAddr($payload)
    {
        $amount = $this->helper->decodeVarint(substr($payload, 0, 10));

        return;
    }

    protected function buildPackage($payload, $type = 'version')
    {
        if (strlen($type) !== 12) { // Add padding to the type
            $padding = 12 - strlen($type);

            $type = $type . hex2bin(str_repeat('00', $padding));
        }

        $package = hex2bin('e9beb4d9'); // Magic
        $package .= $type;
        $package .= pack('N', strlen($payload)); // Packagesize
        $package .= hex2bin(substr(hash('sha512', $payload), 0, 8)); // Sha512 checksum (first 4 digits)

        return $package . $payload;
    }
}
