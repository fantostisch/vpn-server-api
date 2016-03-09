<?php

namespace fkooman\VPN\Server\Log;

use fkooman\Http\Request;
use fkooman\Rest\Service;
use fkooman\Rest\ServiceModuleInterface;
use fkooman\Http\Exception\BadRequestException;
use fkooman\IO\IO;
use fkooman\Http\JsonResponse;
use fkooman\VPN\Server\InputValidation;
use fkooman\Http\Exception\ForbiddenException;
use fkooman\Rest\Plugin\Authentication\Bearer\TokenInfo;

class LogModule implements ServiceModuleInterface
{
    /** @var ConnectionLog */
    private $connectionLog;

    /** @var \fkooman\IO\IO */
    private $io;

    public function __construct(ConnectionLog $connectionLog, IO $io = null)
    {
        $this->connectionLog = $connectionLog;
        if (is_null($io)) {
            $io = new IO();
        }
        $this->io = $io;
    }

    public function init(Service $service)
    {
        $service->get(
            '/log/:showDate',
            function (Request $request, TokenInfo $tokenInfo, $showDate) {
                self::requireScope($tokenInfo, 'log_get');

                InputValidation::date($showDate);

                $showDateUnix = strtotime($showDate);
                $minDate = strtotime('today -31 days', $this->io->getTime());
                $maxDate = strtotime('tomorrow', $this->io->getTime());

                if ($showDateUnix < $minDate || $showDateUnix >= $maxDate) {
                    throw new BadRequestException('invalid date range');
                }

                $showDateUnixMin = strtotime('today', $showDateUnix);
                $showDateUnixMax = strtotime('tomorrow', $showDateUnix);

                $response = new JsonResponse();
                if (is_null($this->connectionLog)) {
                    $responseData = array(
                        'ok' => false,
                        'error' => 'unable to connect to log database',
                    );
                } else {
                    $responseData = array(
                        'ok' => true,
                        'history' => $this->connectionLog->getConnectionHistory($showDateUnixMin, $showDateUnixMax),
                    );
                }
                $response->setBody($responseData);

                return $response;
            }
        );
    }

    private static function requireScope(TokenInfo $tokenInfo, $requiredScope)
    {
        if (!$tokenInfo->getScope()->hasScope($requiredScope)) {
            throw new ForbiddenException('insufficient_scope', sprintf('"%s" scope required', $requiredScope));
        }
    }
}