<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\EventListener\TokenListener;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Testy jednostkowe TokenAuthListener.
 * Weryfikuje weryfikację nagłówka X-TOKEN-SYSTEM dla tras /api/nbp*.
 *
 * @covers \App\EventListener\TokenAuthListener
 */
#[AllowMockObjectsWithoutExpectations]
class TokenListenerTest extends TestCase
{
    private const string VALID_TOKEN = 'test-secret-token';

    private TokenListener $listener;

    protected function setUp(): void
    {
        $this->listener = new TokenListener(self::VALID_TOKEN, new NullLogger());
    }

    /**
     * Pomocnik tworzący RequestEvent.
     */
    private function makeEvent(Request $request, bool $main = true): RequestEvent
    {
        return new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            $main ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST,
        );
    }

    /**
     * Poprawny token – żądanie przepuszczone (brak odpowiedzi w evencie).
     */
    #[Test]
    public function validTokenAllowsRequest(): void
    {
        $request = Request::create('/api/nbp/rates', 'GET');
        $request->headers->set('X-TOKEN-SYSTEM', self::VALID_TOKEN);

        $event = $this->makeEvent($request);
        $this->listener->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    /**
     * Brak nagłówka zwraca HTTP 401.
     */
    #[Test]
    public function missingTokenReturns401(): void
    {
        $request = Request::create('/api/nbp/rates', 'GET');

        $event = $this->makeEvent($request);
        $this->listener->onKernelRequest($event);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $event->getResponse()->getStatusCode());
    }

    /**
     * Brak nagłówka – odpowiedź zawiera informację o brakującym headerze.
     */
    #[Test]
    public function missingTokenResponseBodyMentionsHeaderName(): void
    {
        $request = Request::create('/api/nbp/rates', 'GET');

        $event = $this->makeEvent($request);
        $this->listener->onKernelRequest($event);

        $body = json_decode((string) $event->getResponse()->getContent(), true);
        $this->assertStringContainsString('X-TOKEN-SYSTEM', $body['error']);
    }

    /**
     * Nieprawidłowy token zwraca HTTP 401.
     */
    #[Test]
    public function invalidTokenReturns401(): void
    {
        $request = Request::create('/api/nbp/rates', 'GET');
        $request->headers->set('X-TOKEN-SYSTEM', 'wrong-token');

        $event = $this->makeEvent($request);
        $this->listener->onKernelRequest($event);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $event->getResponse()->getStatusCode());
    }

    /**
     * Pusty string jako token zwraca HTTP 401.
     */
    #[Test]
    public function emptyTokenReturns401(): void
    {
        $request = Request::create('/api/nbp/rates', 'GET');
        $request->headers->set('X-TOKEN-SYSTEM', '');

        $event = $this->makeEvent($request);
        $this->listener->onKernelRequest($event);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $event->getResponse()->getStatusCode());
    }

    /**
     * Ścieżki poza /api/ nie są sprawdzane – brak tokenu nie blokuje żądania.
     */
    #[Test]
    public function nonApiPathIsNotChecked(): void
    {
        foreach (['/health', '/', '/docs'] as $path) {
            $request = Request::create($path, 'GET');
            $event   = $this->makeEvent($request);
            $this->listener->onKernelRequest($event);

            $this->assertNull($event->getResponse(), "Path $path should not be blocked");
        }
    }

    /**
     * Sub-requesty są ignorowane niezależnie od tokenu.
     */
    #[Test]
    public function subRequestIsIgnored(): void
    {
        $request = Request::create('/api/nbp/rates', 'GET');
        // brak tokenu

        $event = $this->makeEvent($request, main: false);
        $this->listener->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    /**
     * Odpowiedź jest JSON z kluczem 'error'.
     */
    #[Test]
    public function unauthorizedResponseIsJson(): void
    {
        $request = Request::create('/api/nbp/rates', 'GET');

        $event = $this->makeEvent($request);
        $this->listener->onKernelRequest($event);

        $response = $event->getResponse();
        $this->assertSame('application/json', $response->headers->get('Content-Type'));

        $body = json_decode((string) $response->getContent(), true);
        $this->assertArrayHasKey('error', $body);
    }
}
