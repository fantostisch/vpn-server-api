<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Server\CA;

use DateTime;
use LC\Common\FileIO;
use LC\Server\CA\Exception\CaException;
use RuntimeException;

class VpnCa implements CaInterface
{
    /** @var string */
    private $caDir;

    /** @var string */
    private $vpnCaPath;

    /** @var string */
    private $easyRsaDataDir;

    /** @var string */
    private $openSslPath = '/usr/bin/openssl';

    /**
     * @param string $caDir
     * @param string $vpnCaPath
     * @param string $easyRsaDataDir
     */
    public function __construct($caDir, $vpnCaPath, $easyRsaDataDir)
    {
        $this->caDir = $caDir;
        $this->vpnCaPath = $vpnCaPath;
        $this->easyRsaDataDir = $easyRsaDataDir;
        $this->init();
    }

    /**
     * Get the CA root certificate.
     *
     * @return string the CA certificate in PEM format
     */
    public function caCert()
    {
        $certFile = sprintf('%s/ca.crt', $this->caDir);

        return $this->readCertificate($certFile);
    }

    /**
     * Generate a certificate for the VPN server.
     *
     * @param string $commonName
     *
     * @return array the certificate, key in array with keys
     *               'cert', 'key', 'valid_from' and 'valid_to'
     */
    public function serverCert($commonName)
    {
        $this->execVpnCa(sprintf('--server %s', $commonName));

        return $this->certInfo($commonName);
    }

    /**
     * Generate a certificate for a VPN client.
     *
     * @param string    $commonName
     * @param \DateTime $expiresAt
     *
     * @return array the certificate and key in array with keys 'cert', 'key',
     *               'valid_from' and 'valid_to'
     */
    public function clientCert($commonName, DateTime $expiresAt)
    {
        // prevent expiresAt to be in the past
        $dateTime = new DateTime();
        if ($dateTime >= $expiresAt) {
            throw new CaException('can not issue certificates that expire in the past');
        }

        $this->execVpnCa(sprintf('--client %s --not-after %s', $commonName, $expiresAt->format(DateTime::ATOM)));

        return $this->certInfo($commonName);
    }

    /**
     * @return bool
     */
    private function isInitialized()
    {
        $hasKey = FileIO::exists(sprintf('%s/ca.key', $this->caDir));
        $hasCert = FileIO::exists(sprintf('%s/ca.crt', $this->caDir));

        return $hasKey && $hasCert;
    }

    /**
     * @return void
     */
    private function init()
    {
        if ($this->isInitialized()) {
            return;
        }

        if (!FileIO::exists($this->caDir)) {
            // we do not have the CA dir, create it
            FileIO::createDir($this->caDir, 0700);
        }

        // copy the CA from easyRsaDataDir iff it is there
        if (FileIO::exists($this->easyRsaDataDir)) {
            $easyRsaCert = sprintf('%s/pki/ca.crt', $this->easyRsaDataDir);
            $easyRsaKey = sprintf('%s/pki/private/ca.key', $this->easyRsaDataDir);
            $hasEasyRsaCert = FileIO::exists($easyRsaCert);
            $hasEasyRsaKey = FileIO::exists($easyRsaKey);
            if ($hasEasyRsaCert && $hasEasyRsaKey) {
                // we found old CA cert/key, copy it to new location
                self::copy($easyRsaCert, sprintf('%s/ca.crt', $this->caDir));
                // we actually need to convert the CA private key to the
                // proper format so Go can read it... Weird that EasyRsa
                // uses a different format...
                // @see https://groups.google.com/d/msg/golang-nuts/hHFbXwyePDA/ZNZsXIQYrKMJ
                //
                //"You can fix this with:
                //% openssl rsa -in key.pem -out rsakey.pem"
                self::convertKey($easyRsaKey, sprintf('%s/ca.key', $this->caDir));

                return;
            }
        }

        // intitialize new CA
        $this->execVpnCa('--init');
    }

    /**
     * @param string $commonName
     *
     * @return array<string,string>
     */
    private function certInfo($commonName)
    {
        $certData = $this->readCertificate(sprintf('%s/%s.crt', $this->caDir, $commonName));
        $keyData = $this->readKey(sprintf('%s/%s.key', $this->caDir, $commonName));

        $parsedCert = openssl_x509_parse($certData);

        return [
            'certificate' => $certData,
            'private_key' => $keyData,
            'valid_from' => $parsedCert['validFrom_time_t'],
            'valid_to' => $parsedCert['validTo_time_t'],
        ];
    }

    /**
     * @param string $certFile
     *
     * @return string
     */
    private function readCertificate($certFile)
    {
        // strip whitespace before and after actual certificate
        return trim(FileIO::readFile($certFile));
    }

    /**
     * @param string $keyFile
     *
     * @return string
     */
    private function readKey($keyFile)
    {
        // strip whitespace before and after actual key
        return trim(FileIO::readFile($keyFile));
    }

    /**
     * @param string $inKey
     * @param string $outKey
     *
     * @return void
     */
    private function convertKey($inKey, $outKey)
    {
        $command = sprintf(
            $this->openSslPath.' rsa -in %s -out %s',
            $inKey,
            $outKey
        );

        self::exec($command);
    }

    /**
     * @param string $cmdArgs
     *
     * @return void
     */
    private function execVpnCa($cmdArgs)
    {
        $command = sprintf(
            $this->vpnCaPath.' --ca-dir %s %s',
            $this->caDir,
            $cmdArgs
        );

        self::exec($command);
    }

    /**
     * @param string $execCmd
     *
     * @return void
     */
    private static function exec($execCmd)
    {
        exec(
            $execCmd,
            $commandOutput,
            $returnValue
        );

        if (0 !== $returnValue) {
            throw new RuntimeException(
                sprintf('command "%s" did not complete successfully: "%s"', $execCmd, implode(PHP_EOL, $commandOutput))
            );
        }
    }

    /**
     * @param string $srcFile
     * @param string $dstFile
     *
     * @return void
     */
    private static function copy($srcFile, $dstFile)
    {
        if (false === @copy($srcFile, $dstFile)) {
            throw new RuntimeException(sprintf('unable to copy "%s" to "%s"', $srcFile, $dstFile));
        }
    }
}