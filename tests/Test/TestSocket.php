<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SURFnet\VPN\Server\Test;

use SURFnet\VPN\Server\OpenVpn\Exception\ManagementSocketException;
use SURFnet\VPN\Server\OpenVpn\ManagementSocketInterface;

/**
 * Abstraction to use the OpenVPN management interface using a socket
 * connection.
 */
class TestSocket implements ManagementSocketInterface
{
    /** @var bool */
    private $connectFail;

    /** @var string|null */
    private $socketAddress;

    public function __construct($connectFail = false)
    {
        $this->connectFail = $connectFail;
        $this->socketAddress = null;
    }

    /**
     * Open the socket.
     *
     * @param string $socketAddress the socket to connect to, e.g.:
     *                              "tcp://localhost:7505"
     * @param int    $timeOut       the amount of time to wait before
     *                              giving up on trying to connect
     *
     * @throws \SURFnet\VPN\Server\OpenVpn\Exception\ServerSocketException if the socket cannot be opened
     *                                                                     within timeout
     */
    public function open($socketAddress, $timeOut = 5)
    {
        $this->socketAddress = $socketAddress;
        if ($this->connectFail) {
            throw new ManagementSocketException('unable to connect to socket');
        }
    }

    /**
     * Send an OpenVPN command and get the response.
     *
     * @param string $command a OpenVPN management command and parameters
     *
     * @return array the response lines as array values
     *
     * @throws \SURFnet\VPN\Server\OpenVpn\Exception\ServerSocketException in case read/write fails or
     *                                                                     socket is not open
     */
    public function command($command)
    {
        if ('status 2' === $command) {
            if ('tcp://10.42.101.101:11940' === $this->socketAddress) {
                // send back the returnData as an array
                return explode("\n", file_get_contents(dirname(__DIR__).'/data/socket/openvpn_23_status.txt'));
            } else {
                return explode("\n", file_get_contents(dirname(__DIR__).'/data/socket/openvpn_23_status_no_clients.txt'));
            }
        } elseif ('kill' === $command) {
            if ('tcp://10.42.101.101:11940' === $this->socketAddress) {
                return explode("\n", file_get_contents(dirname(__DIR__).'/data/socket/openvpn_23_kill_success.txt'));
            } else {
                return explode("\n", file_get_contents(dirname(__DIR__).'/data/socket/openvpn_23_kill_error.txt'));
            }
        }
    }

    /**
     * Close the socket connection.
     *
     * @throws \SURFnet\VPN\Server\OpenVpn\Exception\ServerSocketException if socket is not open
     */
    public function close()
    {
        $this->socketAddress = null;
    }
}
