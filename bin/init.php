<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use LC\Common\FileIO;
use LC\Server\CA\EasyRsaCa;
use LC\Server\Storage;
use LC\Server\TlsCrypt;

try {
    $easyRsaDir = sprintf('%s/easy-rsa', $baseDir);
    $easyRsaDataDir = sprintf('%s/data/easy-rsa', $baseDir);

    $ca = new EasyRsaCa($easyRsaDir, $easyRsaDataDir);

    $dataDir = sprintf('%s/data', $baseDir);
    $storage = new Storage(
        new PDO(
            sprintf('sqlite://%s/db.sqlite', $dataDir)
        ),
        sprintf('%s/schema', $baseDir)
    );
    $storage->init();

    $tlsCrypt = TlsCrypt::generate();
    FileIO::writeFile(sprintf('%s/ta.key', $dataDir), $tlsCrypt->raw());
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
