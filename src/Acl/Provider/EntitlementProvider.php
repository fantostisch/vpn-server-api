<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace SURFnet\VPN\Server\Acl\Provider;

use SURFnet\VPN\Server\Acl\ProviderInterface;
use SURFnet\VPN\Server\Storage;

class EntitlementProvider implements ProviderInterface
{
    /** @var \SURFnet\VPN\Server\Storage */
    private $storage;

    public function __construct(Storage $storage)
    {
        $this->storage = $storage;
    }

    /**
     * @param string $userId
     *
     * @return array<string>
     */
    public function getGroups($userId)
    {
        return $this->storage->getEntitlementList($userId);
    }
}