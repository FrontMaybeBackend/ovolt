<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\NbpController;
use App\Dto\NbpRateDto;
use App\Dto\NbpRequestDto;
use App\Enum\CurrencyEnum;
use App\Service\NbpService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Testy jednostkowe NbpController.
 * Weryfikuje wyłącznie mapowanie wyjątków na kody HTTP
 * oraz strukturę odpowiedzi JSON – logika biznesowa jest w serwisie.
 *
 * @covers \App\Controller\NbpController
 */
#[AllowMockObjectsWithoutExpectations]
class NbpControllerTest extends TestCase
{
    /** @var NbpService&MockObject */
    private NbpService $ratesService;

    private NbpController $controller;

    protected function setUp(): void
    {
        $this->ratesService = $this->createMock(NbpService::class);
        $this->controller   = new NbpController($this->ratesService);
    }

    /**
     * Buduje NbpRequestDto bez potrzeby deserializatora.
     */
    private function makeQuery(CurrencyEnum $currency, string $from, string $to): NbpRequestDto
    {
        $dto               = new NbpRequestDto();
        $dto->currencyCode = $currency;
        $dto->dateFrom     = new \DateTimeImmutable($from);
        $dto->dateTo       = new \DateTimeImmutable($to);

        return $dto;
    }

    /**
     * Serwis zwraca dane – kontroler odpowiada HTTP 200.
     */
    #[Test]
    public function getRatesReturns200WhenServiceSucceeds(): void
    {
        $query = $this->makeQuery(CurrencyEnum::EUR, '2024-01-02', '2024-01-05');

        $this->ratesService->method('getRates')->willReturn([
            new NbpRateDto('2024-01-02', 4.25, 4.34, null, null),
            new NbpRateDto('2024-01-03', 4.26, 4.33, 0.01, -0.01),
        ]);

        $response = $this->controller->getRates($query);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    /**
     * Odpowiedź zawiera tablicę z poprawnymi polami DTO.
     */
    #[Test]
    public function getRatesResponseContainsExpectedFields(): void
    {
        $query = $this->makeQuery(CurrencyEnum::EUR, '2024-01-02', '2024-01-05');

        $this->ratesService->method('getRates')->willReturn([
            new NbpRateDto('2024-01-02', 4.25, 4.34, null, null),
            new NbpRateDto('2024-01-03', 4.26, 4.33, 0.01, -0.01),
        ]);

        $data = json_decode((string) $this->controller->getRates($query)->getContent(), true);

        $this->assertCount(2, $data);

        $this->assertArrayHasKey('date', $data[0]);
        $this->assertArrayHasKey('bid', $data[0]);
        $this->assertArrayHasKey('ask', $data[0]);
        $this->assertArrayHasKey('bidDiff', $data[0]);
        $this->assertArrayHasKey('askDiff', $data[0]);
    }

    /**
     * Pierwszy rekord ma null w polach diff.
     */
    #[Test]
    public function getRatesFirstRecordHasNullDiffs(): void
    {
        $query = $this->makeQuery(CurrencyEnum::EUR, '2024-01-02', '2024-01-05');

        $this->ratesService->method('getRates')->willReturn([
            new NbpRateDto('2024-01-02', 4.25, 4.34, null, null),
        ]);

        $data = json_decode((string) $this->controller->getRates($query)->getContent(), true);

        $this->assertNull($data[0]['bidDiff']);
        $this->assertNull($data[0]['askDiff']);
    }

    /**
     * InvalidArgumentException z serwisu (błąd walidacji dat) – HTTP 400.
     */
    #[Test]
    public function getRatesReturns400OnInvalidArgument(): void
    {
        $query = $this->makeQuery(CurrencyEnum::USD, '2024-01-01', '2024-01-10');

        $this->ratesService
            ->method('getRates')
            ->willThrowException(new \InvalidArgumentException('Date range cannot exceed 7 days'));

        $response = $this->controller->getRates($query);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    /**
     * InvalidArgumentException – treść błędu trafia do odpowiedzi.
     */
    #[Test]
    public function getRates400ResponseContainsErrorMessage(): void
    {
        $query = $this->makeQuery(CurrencyEnum::USD, '2024-01-01', '2024-01-10');

        $this->ratesService
            ->method('getRates')
            ->willThrowException(new \InvalidArgumentException('Date range cannot exceed 7 days'));

        $data = json_decode((string) $this->controller->getRates($query)->getContent(), true);

        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('7 days', $data['error']);
    }

    /**
     * RuntimeException z serwisu (błąd API NBP) – HTTP 500.
     */
    #[Test]
    public function getRatesReturns500OnRuntimeException(): void
    {
        $query = $this->makeQuery(CurrencyEnum::CHF, '2024-01-02', '2024-01-05');

        $this->ratesService
            ->method('getRates')
            ->willThrowException(new \RuntimeException('NBP API returned HTTP 404'));

        $response = $this->controller->getRates($query);

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    /**
     * RuntimeException – odpowiedź zawiera klucze 'error' i 'detail'.
     */
    #[Test]
    public function getRates500ResponseContainsErrorAndDetail(): void
    {
        $query = $this->makeQuery(CurrencyEnum::CHF, '2024-01-02', '2024-01-05');

        $this->ratesService
            ->method('getRates')
            ->willThrowException(new \RuntimeException('NBP API returned HTTP 404 for currency CHF'));

        $data = json_decode((string) $this->controller->getRates($query)->getContent(), true);

        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('detail', $data);
        $this->assertStringContainsString('CHF', $data['detail']);
    }

    /**
     * Serwis jest wołany z dokładnie tymi parametrami co w query.
     */
    #[Test]
    public function getRatesPassesQueryParamsToService(): void
    {
        $query = $this->makeQuery(CurrencyEnum::USD, '2024-01-02', '2024-01-06');

        $this->ratesService
            ->expects($this->once())
            ->method('getRates')
            ->with(CurrencyEnum::USD, $query->dateFrom, $query->dateTo)
            ->willReturn([]);

        $this->controller->getRates($query);
    }

    /**
     * Pusta tablica z serwisu – HTTP 200 z pustą tablicą JSON.
     */
    #[Test]
    public function getRatesReturns200WithEmptyArray(): void
    {
        $query = $this->makeQuery(CurrencyEnum::EUR, '2024-01-02', '2024-01-05');

        $this->ratesService->method('getRates')->willReturn([]);

        $response = $this->controller->getRates($query);
        $data     = json_decode((string) $response->getContent(), true);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame([], $data);
    }
}
