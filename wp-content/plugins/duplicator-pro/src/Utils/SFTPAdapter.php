<?php

namespace Duplicator\Utils;

use DUP_PRO_Log;
use phpseclib3\Crypt\Common\PrivateKey;
use phpseclib3\Crypt\RSA;
use phpseclib3\Net\SFTP;
use RuntimeException;

class SFTPAdapter
{
    public $sourceLocalFiles = 1;
    public $sFtpResume       = 1;

    /**
     * Constructor
     * @return void
     */
    public function __construct()
    {
        $this->sFtpResume       = SFTP::RESUME;
        $this->sourceLocalFiles = SFTP::SOURCE_LOCAL_FILE;
    }

    /**
     * Get PHPSecLib SFTP Client
     * @param string $server hosting server domain or ip
     * @param string $port   hosting port
     * @return SFTP|bool     return SFTP client or false
     */
    public function getClient($server = '', $port = '')
    {
        if (empty($server) || empty($port)) {
            return false;
        }
        return new SFTP($server, $port);
    }

    /**
     * Connect to an SFTP Server
     *
     * @param string                        $server             hosting domain or ip address
     * @param string                        $port               hosting port
     * @param string                        $username           hosting username
     * @param string                        $password           hosting password
     * @param string                        $privateKey         hosting private key
     * @param string                        $privateKeyPassword hosting private key password
     * @param null|IncrementalStatusMessage $statusMessagesList status message object
     * @return bool|SFTP                                        return SFTP object or false
     */
    public function connect(
        $server = '',
        $port = '',
        $username = '',
        $password = '',
        $privateKey = '',
        $privateKeyPassword = '',
        $statusMessagesList = null
    ) {
        if ($statusMessagesList === null) {
            $statusMessagesList = new IncrementalStatusMessage();
        }

        if (empty($server)) {
            $errorMessage = __('Server name is required to make sftp connection', 'duplicator-pro');
            return $this->throwError($errorMessage);
        }
        if (empty($port)) {
            $errorMessage = __('Server port is required to make sftp connection', 'duplicator-pro');
            return $this->throwError($errorMessage);
        }
        if (empty($username)) {
            $errorMessage = __('Username is required to make sftp connection', 'duplicator-pro');
            return $this->throwError($errorMessage);
        }
        if (empty($password) && empty($privateKey)) {
            $errorMessage = __(
                'You should provide either sftp user pasword or the private key to make sftp connection',
                'duplicator-pro'
            );
            return $this->throwError($errorMessage);
        }

        if (!empty($privateKey)) {
            $key = $this->getPrivateKey($privateKey, $privateKeyPassword);
        }

        $statusMessagesList->addMessage(sprintf(
            __('Connecting to SFTP server %1$s:%2$d', 'duplicator-pro'),
            $server,
            $port
        ));
        DUP_PRO_Log::trace("Connect to SFTP server $server:$port");
        $sftp = $this->getClient($server, $port);
        $statusMessagesList->addMessage(sprintf(__('Attempting to login to SFTP server %1$s', 'duplicator-pro'), $server));
        DUP_PRO_Log::trace("Attempting to login to SFTP server $server");
        if (isset($key) && $key) {
            $statusMessagesList->addMessage(__('Login to SFTP using private key', 'duplicator-pro'));
            DUP_PRO_Log::trace("Login to SFTP using private key");
            if ($sftp->login($username, $key)) {
                $statusMessagesList->addMessage(__('Successfully connected to server using private key', 'duplicator-pro'));
                DUP_PRO_Log::trace('Successfully connected to server using private key');
            } else {
                $errorMessage = __('Error opening SFTP connection using private key', 'duplicator-pro');
                return $this->throwError($errorMessage);
            }
        } else {
            DUP_PRO_Log::trace("Login to SFTP");
            if ($sftp->login($username, $password)) {
                $statusMessagesList->addMessage(__('Successfully connected to server using password', 'duplicator-pro'));
                DUP_PRO_Log::trace('Successfully connected to server using password');
            } else {
                $errorMessage = __('Error opening SFTP connection using password', 'duplicator-pro');
                return $this->throwError($errorMessage);
            }
        }
        return $sftp;
    }

    /**
     * Set an SFTP Private Key
     *
     * @param string $privateKey         hosting private key
     * @param string $privateKeyPassword hosting private key password
     * @return PrivateKey|false return key object or false
     */
    public function getPrivateKey($privateKey, $privateKeyPassword)
    {
        if (empty($privateKey)) {
            $errorMessage = 'Private key is null';
            return $this->throwError($errorMessage);
        }

        if (!empty($privateKeyPassword)) {
            DUP_PRO_Log::trace("Get Private Key Object with Password");
            $key = RSA::loadPrivateKey($privateKey, $privateKeyPassword);
        } else {
            DUP_PRO_Log::trace("Get Private Object Key");
            $key = RSA::loadPrivateKey($privateKey);
        }
        DUP_PRO_Log::trace("Private Key Loaded");
        return $key;
    }

    /**
     * Create directory recursively
     *
     * @param string $storagePath storage directory path
     * @param null   $sFtp        SFTP object
     * @return false|string return the directory path or exception
     */
    public function mkDirRecursive($storagePath = '', $sFtp = null)
    {
        if (empty($storagePath)) {
            $errorMessage = 'Storage Folder is null.';
            return $this->throwError($errorMessage);
        }
        if (empty($sFtp)) {
            $errorMessage = 'You must connect to SFTP before making directory.';
            return $this->throwError($errorMessage);
        }
        $storageFolders = explode("/", $storagePath);
        $path           = '';
        foreach ($storageFolders as $dir) {
            $path = $path . '/' . $dir;
            if (!$sFtp->file_exists($path)) {
                if (!$sFtp->mkdir($path)) {
                    $errorMessage = 'Directory not created ' . $path . '. Make sure you have write permissions on your SFTP server.';
                    return $this->throwError($errorMessage);
                }
            }
        }
        return $storagePath;
    }

    /**
     * Throws an exception in case of an error
     * @param  string $errorMessage Error Message
     * @return false                throw an exception and return false
     * @throws RuntimeException     if there is an error message
     */
    private function throwError($errorMessage = '')
    {
        if (!empty($errorMessage)) {
            DUP_PRO_Log::trace($errorMessage);
            throw new RuntimeException($errorMessage);
        }
        return false;
    }
}
