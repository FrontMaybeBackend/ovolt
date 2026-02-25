<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\CurrencyEnum;
use OpenApi\Attributes as OA;

/**
 * DTO reprezentujące parametry zapytania do endpointu kursów NBP.
 */
#[OA\Schema(
    schema: 'NbpRequestDto',
    description: 'Parametry zapytania o kursy walut NBP',
    required: ['currencyCode', 'dateFrom', 'dateTo'],
)]
class NbpRequestDto
{
    /**
     * Kod waluty (EUR, USD, CHF).
     */
    #[OA\Property(
        description: 'Kod waluty',
        enum: CurrencyEnum::class,
    )]

    public CurrencyEnum $currencyCode;

    /**
     * Data początkowa zakresu .
     */
    #[OA\Property(
        description: 'Data początkowa (Y-m-d)',
        type: 'string',
        format: 'date',
    )]

    public \DateTimeImmutable $dateFrom;

    /**
     * Data końcowa zakresu.
     */
    #[OA\Property(
        description: 'Data końcowa (Y-m-d)',
        type: 'string',
        format: 'date',
    )]

    public \DateTimeImmutable $dateTo;

}
