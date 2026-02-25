<?php

declare(strict_types=1);

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Listener weryfikujący obecność i poprawność nagłówka X-TOKEN-SYSTEM
 * dla wszystkich zapytań do tras /api/nbp*.
 */
class TokenListener
{
    /** Nazwa wymaganego nagłówka HTTP */
    private const string HEADER_NAME = 'X-TOKEN-SYSTEM';

    /**
     * @param string          $systemToken Wymagany token (z env APP_SYSTEM_TOKEN)
     * @param LoggerInterface $logger      Logger
     */
    public function __construct(
        private readonly string $systemToken,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Sprawdza nagłówek X-TOKEN-SYSTEM dla zapytań do /api/nbp*.
     * Jeśli nagłówek jest nieobecny lub nieprawidłowy, zwraca HTTP 401.
     *
     * @param RequestEvent $event Zdarzenie żądania HTTP
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();


        if (!str_starts_with($request->getPathInfo(), '/api/nbp')) {
            return;
        }

        $token = $request->headers->get(self::HEADER_NAME);

        if ($token === null) {
            $this->logger->warning('Missing X-TOKEN-SYSTEM header', [
                'path' => $request->getPathInfo(),
            ]);

            $event->setResponse(new JsonResponse(
                ['error' => sprintf('Missing required header: %s', self::HEADER_NAME)],
                Response::HTTP_UNAUTHORIZED,
            ));

            return;
        }

        if (!hash_equals($this->systemToken, $token)) {
            $this->logger->warning('Invalid X-TOKEN-SYSTEM token', [
                'path' => $request->getPathInfo(),
            ]);

            $event->setResponse(new JsonResponse(
                ['error' => 'Invalid system token'],
                Response::HTTP_UNAUTHORIZED,
            ));
        }
    }
}
