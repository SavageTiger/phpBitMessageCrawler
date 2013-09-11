<?php

class Protocol
{
    protected $accepted = false;
    protected $readyForSending = false;

    protected $logger = null;
    protected $helper = null;
    protected $ipBag  = null;
    protected $invBag = null;

    public function __construct($sqlite)
    {
        $this->logger = new Logger();
        $this->helper = new Helper();
        $this->ipBag  = new IpBag($sqlite);
        $this->invBag = new invBag($sqlite);
    }

    public function generateVersionPackage($remoteIP, $remotePort, $localPort)
    {
        $streamNumber = 1;

        $payload = '';
        $payload .= pack('N', 2); // Version
        $payload .= $this->helper->pack_double(1, 64, false); // Bitflags
        $payload .= $this->helper->pack_double(time(), 64, false); // Timestamp

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

        // Download the rest of payload otherwise the hash checksum will be incorrect and the
        // payload will be to short to process.
        if ($size > 32) {
            $downloadSize = $size - strlen($data);

            while ($downloadSize > 0) {
                    if (socket_recv($socket['socket'], $buffer, 1024, 0)) {
                        $data .= $buffer;
                    } else {
                        return false;
                    }

                $downloadSize = $size - strlen($data);
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

            case 'inv':
                $this->processInv($payload, $socket);
                break;

            case 'pubkey':
                $this->processKey($payload);
                break;

            case 'version':
                $this->checkRemoteVersion($payload, $socket);
                break;

            default:
                $this->invBag->resetHash();
                break;
        }
    }

    public function sendPackage($socket)
    {
        if ($this->invBag->getHash() === false) {
            $hash = $this->invBag->getRandomInventory($socket['host']);
            $data = $this->buildPackage(pack('C', 1) . $hash, 'getdata');

            socket_send($socket['socket'], $data, strlen($data), 0);
        }
    }

    public function isAccepted()
    {
        return ($this->accepted === true && $this->readyForSending === true);
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
            $agent = substr($payload, 80 + $stringSize['len'], $stringSize['int']);

            $this->logger->log('Remote version is accepted [agent: ' . $agent . '] [version: ' . $version . '] [destination: ' . $ip . ']');

            // Send a verack for good measure
            $data = $this->buildPackage('', 'verack');
            socket_send($socket['socket'], $data, strlen($data), 0);

            $this->accepted = true;

            return true;
        }

        return false;
    }

    protected function processKey($payload)
    {
        if ($this->helper->checkPOW($payload) && strlen($payload) > 146 && strlen($payload) < 600) {
            $timestamp = substr($payload, 12, 4);

            if ($timestamp == 0) {
                $timestamp = substr($payload, 8, 4);
            } else {
                $timestamp = substr($payload, 8, 8);
            }

            $timestamp = $this->helper->unpack_double($timestamp, false);

            $payload = substr($payload, 16);
            $addressVersion = $this->helper->decodeVarInt($payload);

            $payload = substr($payload, $addressVersion['len']);
            $streamNumber = $this->helper->decodeVarInt($payload);

            if ($streamNumber['int'] === 1) {
                $payload = substr($payload, $streamNumber['len']);
                $behavior = substr($payload, 0, 4); // XXX

                $signingKey = substr($payload, 4, 64);
                $encryptionKey = substr($payload, 68, 64);

                if ($this->invBag->addKey($signingKey, $encryptionKey, $timestamp)) {
                    $this->invBag->resetHash();
                }
            } else {
                $this->logger->log('Public key is not in my stream');
            }
        } else {
            $this->logger->log('Public key failed proof of work or size check');
        }
    }

    protected function processAddr($payload)
    {
        $offset = 0;
        $new = 0;
        $amount = $this->helper->decodeVarint(substr($payload, 0, 10));

        if (strlen($payload) === intval($amount['len'] + (38 * $amount['int']))) {
            $this->logger->log('Recieved ' . $amount['int'] . ' ip adresses');

            $payload = substr($payload, $amount['len']);

            while ($offset !== (38 * $amount['int'])) {
                $ipCheck = substr($payload, $offset + 20, 12);

                if (bin2hex($ipCheck) == '00000000000000000000ffff') { // Check if the ip address is IPv4 (IPv6 is unsupported at the moment)
                    $ip = unpack('N', substr($payload, (20 + 12) + $offset, 4));
                    $ip = long2ip(current($ip));

                    $port = unpack('n', substr($payload, (20 + 12) + $offset + 4, 2));
                    $port = current($port);

                    if ($this->ipBag->add($ip, $port)) {
                        $new++;
                    }
                }

                $offset += 38;
            }

            if ($new > 0) {
                $this->logger->log('Added ' . $new . ' new ip addresses');
                $this->ipBag->commit();
            }

            return true;
        }

        $this->logger->log('Invalid addr package');

        return false;
    }

    protected function processInv($payload, $socket)
    {
        $offset = 0;
        $invHash = array();
        $amount = $this->helper->decodeVarint(substr($payload, 0, 10));

        $this->logger->log('Recieved ' . $amount['int'] . ' inventory items');

        if (strlen($payload) === intval($amount['len'] + (32 * $amount['int']))) {
            $payload = substr($payload, $amount['len']);

            while ($offset !== strlen($payload)) {
                $invHash[] = substr($payload, $offset, 32);

                $offset += 32;
            }

            if (count($invHash) !== 0) {
                $new = $this->invBag->addRange($invHash, $socket['host']);

                if ($new > 0) {
                    $this->logger->log('Added ' . $new . ' inventory items');
                }
            }

            if ($this->readyForSending === false) {
                $this->readyForSending = true;
            }

            return true;
        }

        $this->logger->log('Invalid inv package');

        return false;
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
