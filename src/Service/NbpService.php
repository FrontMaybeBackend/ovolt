<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\CurrencyEnum;
use App\Dto\NbpRateDto;
use App\Infrastructure\NbpApiClient;

/**
 * Serwis obsługujący pobieranie i walidację kursów walut NBP.
 */
class NbpService
{
    /** Maksymalny dozwolony zakres dat w dniach */
    public const int MAX_DATE_RANGE_DAYS = 7;

    /**
     * @param NbpApiClient $nbpClient Klient API NBP
     */
    public function __construct(
        private readonly NbpApiClient $nbpClient,
    ) {
    }

    /**
     * Zwraca kursy waluty dla podanego zakresu dat wraz z różnicami dziennymi.
     *
     * @param CurrencyEnum       $currencyCode Kod waluty
     * @param \DateTimeImmutable $dateFrom     Data początkowa
     * @param \DateTimeImmutable $dateTo       Data końcowa
     *
     * @return NbpRateDto[]
     *
     * @throws \InvalidArgumentException Gdy zakres dat jest nieprawidłowy lub przekracza limit
     * @throws \RuntimeException         Gdy API NBP zwróci błąd
     */
    public function getRates(
        CurrencyEnum $currencyCode,
        \DateTimeImmutable $dateFrom,
        \DateTimeImmutable $dateTo,
    ): array {
        $this->validateDateRange($dateFrom, $dateTo);

        return $this->nbpClient->fetchRates($currencyCode, $dateFrom, $dateTo);
    }

    /**
     * Waliduje zakres dat – kolejność i maksymalną rozpiętość.
     *
     * @throws \InvalidArgumentException
     */
    private function validateDateRange(\DateTimeImmutable $dateFrom, \DateTimeImmutable $dateTo): void
    {
        if ($dateTo < $dateFrom) {
            throw new \InvalidArgumentException('dateTo must be greater than or equal to dateFrom');
        }

        if ($dateFrom->diff($dateTo)->days > self::MAX_DATE_RANGE_DAYS) {
            throw new \InvalidArgumentException(
                sprintf('Date range cannot exceed %d days', self::MAX_DATE_RANGE_DAYS)
            );
        }
    }
}
