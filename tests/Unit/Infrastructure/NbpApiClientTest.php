<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure;

use App\Dto\NbpRateDto;
use App\Enum\CurrencyEnum;
use App\Infrastructure\NbpApiClient;
use App\Infrastructure\NbpXmlParser;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Testy jednostkowe NbpApiClient.
 * Weryfikuje wyłącznie logikę HTTP: budowanie URL, obsługę statusów,
 * oraz delegowanie parsowania do NbpXmlParser.
 *
 * @covers \App\Infrastructure\NbpApiClient
 */
#[AllowMockObjectsWithoutExpectations]
class NbpApiClientTest extends KernelTestCase
{
    /** @var HttpClientInterface&MockObject */
    private HttpClientInterface $httpClient;

    /** @var NbpXmlParser&MockObject */
    private NbpXmlParser $parser;

    /** @var ResponseInterface&MockObject */
    private ResponseInterface $response;

    private NbpApiClient $client;

    private string $nbpUrl;

    protected function setUp(): void
    {
        $container = self::getContainer();
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->parser     = $this->createMock(NbpXmlParser::class);
        $this->response   = $this->createMock(ResponseInterface::class);
        $this->nbpUrl = $container->getParameter('nbp_url');
        $this->client = new NbpApiClient(
            nbpUrl: $this->nbpUrl,
            logger: new NullLogger(),
            httpClient: $this->httpClient,
            parser: $this->parser,
        );
    }

    /**
     * Poprawna odpowiedź 200 – content trafia do parsera, wynik parsera jest zwracany.
     */
    #[Test]
    public function fetchRatesDelegatesToParserAndReturnsResult(): void
    {
        $xmlContent = '<xml/>';
        $expected   = [new NbpRateDto('2024-01-02', 4.25, 4.34, null, null)];

        $this->response->method('getStatusCode')->willReturn(200);
        $this->response->method('getContent')->willReturn($xmlContent);
        $this->httpClient->method('request')->willReturn($this->response);

        $this->parser
            ->expects($this->once())
            ->method('parse')
            ->with($xmlContent)
            ->willReturn($expected);

        $result = $this->client->fetchRates(
            CurrencyEnum::EUR,
            new \DateTimeImmutable('2024-01-02'),
            new \DateTimeImmutable('2024-01-05'),
        );

        $this->assertSame($expected, $result);
    }

    /**
     * HTTP 404 rzuca RuntimeException, parser nie jest wywoływany.
     */
    #[Test]
    public function fetchRatesThrowsOnHttpError(): void
    {
        $this->response->method('getStatusCode')->willReturn(404);
        $this->httpClient->method('request')->willReturn($this->response);

        $this->parser->expects($this->never())->method('parse');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/HTTP 404/');

        $this->client->fetchRates(
            CurrencyEnum::EUR,
            new \DateTimeImmutable('2024-01-02'),
            new \DateTimeImmutable('2024-01-05'),
        );
    }

    /**
     * HTTP 400 rzuca RuntimeException z kodem waluty w komunikacie.
     */
    #[Test]
    public function fetchRatesExceptionContainsCurrencyCode(): void
    {
        $this->response->method('getStatusCode')->willReturn(400);
        $this->httpClient->method('request')->willReturn($this->response);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/CHF/');

        $this->client->fetchRates(
            CurrencyEnum::CHF,
            new \DateTimeImmutable('2024-01-02'),
            new \DateTimeImmutable('2024-01-05'),
        );
    }

    /**
     * URL zawiera tabelę C, kod waluty małymi literami i zakres dat.
     */
    #[Test]
    public function fetchRatesBuildsUrlWithCorrectSegments(): void
    {
        $this->response->method('getStatusCode')->willReturn(200);
        $this->response->method('getContent')->willReturn('<xml/>');
        $this->parser->method('parse')->willReturn([]);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with('GET', $this->logicalAnd(
                $this->stringContains('rates/C/eur'),
                $this->stringContains('2024-01-02'),
                $this->stringContains('2024-01-05'),
                $this->stringContains('format=xml'),
            ))
            ->willReturn($this->response);

        $this->client->fetchRates(
            CurrencyEnum::EUR,
            new \DateTimeImmutable('2024-01-02'),
            new \DateTimeImmutable('2024-01-05'),
        );
    }


}
