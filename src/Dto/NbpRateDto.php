<?php

declare(strict_types=1);

namespace App\Dto;

use OpenApi\Attributes as OA;

/**
 * DTO reprezentujące pojedynczy kurs waluty wraz z różnicami względem dnia poprzedniego.
 */
#[OA\Schema(
    schema: 'NbpRateDto',
    description: 'Kurs waluty dla pojedynczego dnia',
)]
class NbpRateDto implements \JsonSerializable
{
    /**
     * @param string   $date      Data kursu (Y-m-d)
     * @param float    $bid       Kurs kupna
     * @param float    $ask       Kurs sprzedaży
     * @param float|null $bidDiff Różnica kursu kupna względem dnia poprzedniego
     * @param float|null $askDiff Różnica kursu sprzedaży względem dnia poprzedniego
     */
    public function __construct(
        #[OA\Property(description: 'Data kursu', example: '2024-01-01')]
        public readonly string $date,

        #[OA\Property(description: 'Kurs kupna', example: 4.2531)]
        public readonly float $bid,

        #[OA\Property(description: 'Kurs sprzedaży', example: 4.3387)]
        public readonly float $ask,

        #[OA\Property(description: 'Różnica kursu kupna względem poprzedniego dnia (null dla pierwszego dnia)', example: 0.0012, nullable: true)]
        public readonly ?float $bidDiff,

        #[OA\Property(description: 'Różnica kursu sprzedaży względem poprzedniego dnia (null dla pierwszego dnia)', example: -0.0008, nullable: true)]
        public readonly ?float $askDiff,
    ) {
    }

    /**
     * Serializuje DTO do tablicy JSON.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'date'    => $this->date,
            'bid'     => $this->bid,
            'ask'     => $this->ask,
            'bidDiff' => $this->bidDiff,
            'askDiff' => $this->askDiff,
        ];
    }
}
