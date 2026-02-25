<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Dto\NbpRateDto;
use Symfony\Component\Serializer\Encoder\DecoderInterface;

/**
 * Parsuje odpowiedź XML z API NBP i buduje tablicę NbpRateDto.
 */
readonly class NbpXmlParser
{

    public function __construct(
        private DecoderInterface $decoder,
    ) {
    }

    /**
     * Parsuje surowy XML z API NBP i zwraca tablicę DTO z kursami.
     * Wylicza różnicę kursu kupna i sprzedaży względem dnia poprzedniego.
     *
     * @param string $xmlContent Surowa odpowiedź XML z API NBP
     *
     * @return NbpRateDto[]
     *
     * @throws \RuntimeException Gdy odpowiedź nie zawiera żadnych kursów
     */
    public function parse(string $xmlContent): array
    {
        $data = $this->decoder->decode($xmlContent, 'xml');

        $rawRates = $data['Rates']['Rate'] ?? [];

        if (isset($rawRates['EffectiveDate'])) {
            $rawRates = [$rawRates];
        }

        if (empty($rawRates)) {
            throw new \RuntimeException('No rates found in NBP XML response');
        }

        return $this->buildRateDtos($rawRates);
    }

    /**
     * Buduje tablicę NbpRateDto z surowych danych, wyliczając różnice między dniami.
     *
     * @param array<int, array<string, string>> $rawRates Surowe dane z XML
     *
     * @return NbpRateDto[]
     */
    private function buildRateDtos(array $rawRates): array
    {
        $rates       = [];
        $previousBid = null;
        $previousAsk = null;

        foreach ($rawRates as $raw) {
            $bid = (float) $raw['Bid'];
            $ask = (float) $raw['Ask'];

            $rates[] = new NbpRateDto(
                date: $raw['EffectiveDate'],
                bid: $bid,
                ask: $ask,
                bidDiff: $previousBid !== null ? round($bid - $previousBid, 4) : null,
                askDiff: $previousAsk !== null ? round($ask - $previousAsk, 4) : null,
            );

            $previousBid = $bid;
            $previousAsk = $ask;
        }

        return $rates;
    }
}
