<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Dto\NbpRateDto;
use App\Enum\CurrencyEnum;
use App\Infrastructure\NbpApiClient;
use App\Service\NbpService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Testy jednostkowe NbpRatesService.
 * Weryfikuje walidację zakresu dat oraz delegowanie do NbpApiClient.
 *
 * @covers \App\Service\NbpRatesService
 */
#[AllowMockObjectsWithoutExpectations]
class NbpServiceTest extends TestCase
{
    /** @var NbpApiClient&MockObject */
    private NbpApiClient $nbpClient;

    private NbpService $service;

    protected function setUp(): void
    {
        $this->nbpClient = $this->createMock(NbpApiClient::class);
        $this->service   = new NbpService($this->nbpClient);
    }

    /**
     * Poprawny zakres – deleguje wywołanie do klienta i zwraca jego wynik.
     */
    #[Test]
    public function getRatesDelegatesToClientAndReturnsResult(): void
    {
        $dateFrom = new \DateTimeImmutable('2024-01-02');
        $dateTo   = new \DateTimeImmutable('2024-01-05');
        $expected = [new NbpRateDto('2024-01-02', 4.25, 4.34, null, null)];

        $this->nbpClient
            ->expects($this->once())
            ->method('fetchRates')
            ->with(CurrencyEnum::EUR, $dateFrom, $dateTo)
            ->willReturn($expected);

        $result = $this->service->getRates(CurrencyEnum::EUR, $dateFrom, $dateTo);

        $this->assertSame($expected, $result);
    }

    /**
     * dateTo wcześniejsza niż dateFrom rzuca InvalidArgumentException.
     */
    #[Test]
    public function getRatesThrowsWhenDateToBeforeDateFrom(): void
    {
        $this->nbpClient->expects($this->never())->method('fetchRates');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/dateTo must be/');

        $this->service->getRates(
            CurrencyEnum::EUR,
            new \DateTimeImmutable('2024-01-07'),
            new \DateTimeImmutable('2024-01-01'),
        );
    }

    /**
     * Zakres 8 dni rzuca InvalidArgumentException.
     */
    #[Test]
    public function getRatesThrowsWhenRangeExceeds7Days(): void
    {
        $this->nbpClient->expects($this->never())->method('fetchRates');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/cannot exceed 7 days/');

        $this->service->getRates(
            CurrencyEnum::EUR,
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-01-10'),
        );
    }

    /**
     * Dokładnie 7 dni jest dozwolone (wartość graniczna).
     */
    #[Test]
    public function getRatesAllowsExactly7DayRange(): void
    {
        $this->nbpClient->method('fetchRates')->willReturn([]);

        $result = $this->service->getRates(
            CurrencyEnum::EUR,
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-01-08'),
        );

        $this->assertIsArray($result);
    }

    /**
     * Ten sam dzień jako dateFrom i dateTo (zakres 0 dni) jest dozwolony.
     */
    #[Test]
    public function getRatesAllowsSameDay(): void
    {
        $this->nbpClient->method('fetchRates')->willReturn([]);

        $result = $this->service->getRates(
            CurrencyEnum::USD,
            new \DateTimeImmutable('2024-01-02'),
            new \DateTimeImmutable('2024-01-02'),
        );

        $this->assertIsArray($result);
    }

    /**
     * Zakres 6 dni jest dozwolony.
     */
    #[Test]
    public function getRatesAllows6DayRange(): void
    {
        $this->nbpClient->method('fetchRates')->willReturn([]);

        $result = $this->service->getRates(
            CurrencyEnum::CHF,
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-01-07'),
        );

        $this->assertIsArray($result);
    }

}
