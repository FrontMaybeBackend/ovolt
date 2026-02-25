<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure;

use App\Dto\NbpRateDto;
use App\Infrastructure\NbpXmlParser;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\DecoderInterface;


/**
 * Testy jednostkowe NbpXmlParser.
 * Weryfikuje parsowanie XML, normalizację pojedynczego rekordu
 * oraz poprawność wyliczania różnic kursów między dniami.
 *
 * @covers \App\Infrastructure\NbpXmlParser
 */
#[AllowMockObjectsWithoutExpectations]
class NbpXmlParserTest extends TestCase
{

    private DecoderInterface $decoder;

    private NbpXmlParser $parser;

    protected function setUp(): void
    {
        $this->decoder = $this->createMock(DecoderInterface::class);
        $this->parser     = new NbpXmlParser($this->decoder);
    }

    /**
     * Wiele rekordów – zwraca poprawną liczbę DTO.
     */
    #[Test]
    public function parseReturnsCorrectNumberOfRates(): void
    {
        $this->decoder->method('decode')->willReturn([
            'Rates' => [
                'Rate' => [
                    ['EffectiveDate' => '2024-01-02', 'Bid' => '4.2500', 'Ask' => '4.3400'],
                    ['EffectiveDate' => '2024-01-03', 'Bid' => '4.2600', 'Ask' => '4.3300'],
                    ['EffectiveDate' => '2024-01-04', 'Bid' => '4.2400', 'Ask' => '4.3500'],
                ],
            ],
        ]);

        $rates = $this->parser->parse('<xml/>');

        $this->assertCount(3, $rates);
    }

    /**
     * Pierwszy rekord nie ma różnic (brak poprzedniego dnia).
     */
    #[Test]
    public function firstRateHasNullDiffs(): void
    {
        $this->decoder->method('decode')->willReturn([
            'Rates' => [
                'Rate' => [
                    ['EffectiveDate' => '2024-01-02', 'Bid' => '4.2500', 'Ask' => '4.3400'],
                    ['EffectiveDate' => '2024-01-03', 'Bid' => '4.2600', 'Ask' => '4.3300'],
                ],
            ],
        ]);

        $rates = $this->parser->parse('<xml/>');

        $this->assertNull($rates[0]->bidDiff);
        $this->assertNull($rates[0]->askDiff);
    }

    /**
     * Kolejne rekordy mają poprawnie wyliczone różnice względem poprzedniego dnia.
     */
    #[Test]
    public function subsequentRatesHaveCorrectDiffs(): void
    {
        $this->decoder->method('decode')->willReturn([
            'Rates' => [
                'Rate' => [
                    ['EffectiveDate' => '2024-01-02', 'Bid' => '4.2500', 'Ask' => '4.3400'],
                    ['EffectiveDate' => '2024-01-03', 'Bid' => '4.2600', 'Ask' => '4.3300'],
                    ['EffectiveDate' => '2024-01-04', 'Bid' => '4.2400', 'Ask' => '4.3500'],
                ],
            ],
        ]);

        $rates = $this->parser->parse('<xml/>');

        $this->assertEqualsWithDelta(0.01, $rates[1]->bidDiff, 0.0001);
        $this->assertEqualsWithDelta(-0.01, $rates[1]->askDiff, 0.0001);

        $this->assertEqualsWithDelta(-0.02, $rates[2]->bidDiff, 0.0001);
        $this->assertEqualsWithDelta(0.02, $rates[2]->askDiff, 0.0001);
    }

    /**
     * Zwraca instancje NbpRateDto z poprawnymi polami date/bid/ask.
     */
    #[Test]
    public function parseReturnsNbpRateDtoInstances(): void
    {
        $this->decoder->method('decode')->willReturn([
            'Rates' => [
                'Rate' => [
                    ['EffectiveDate' => '2024-01-02', 'Bid' => '4.2531', 'Ask' => '4.3387'],
                ],
            ],
        ]);

        $rates = $this->parser->parse('<xml/>');

        $this->assertInstanceOf(NbpRateDto::class, $rates[0]);
        $this->assertSame('2024-01-02', $rates[0]->date);
        $this->assertEqualsWithDelta(4.2531, $rates[0]->bid, 0.0001);
        $this->assertEqualsWithDelta(4.3387, $rates[0]->ask, 0.0001);
    }

    /**
     * Pojedynczy rekord (Symfony Serializer zwraca array asocjacyjny zamiast array of arrays)
     * – parser normalizuje go do tablicy i nie rzuca wyjątku.
     */
    #[Test]
    public function parseSingleRecordIsNormalizedToArray(): void
    {
        $this->decoder->method('decode')->willReturn([
            'Rates' => [
                'Rate' => ['EffectiveDate' => '2024-01-02', 'Bid' => '4.2500', 'Ask' => '4.3400'],
            ],
        ]);

        $rates = $this->parser->parse('<xml/>');

        $this->assertCount(1, $rates);
        $this->assertSame('2024-01-02', $rates[0]->date);
    }

    /**
     * Brak rekordów w odpowiedzi rzuca RuntimeException.
     */
    #[Test]
    public function parseThrowsWhenNoRatesReturned(): void
    {
        $this->decoder->method('decode')->willReturn(['Rates' => []]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/No rates found/');

        $this->parser->parse('<xml/>');
    }

    /**
     * Różnice są zaokrąglane do 4 miejsc po przecinku.
     */
    #[Test]
    public function diffsAreRoundedTo4DecimalPlaces(): void
    {
        $this->decoder->method('decode')->willReturn([
            'Rates' => [
                'Rate' => [
                    ['EffectiveDate' => '2024-01-02', 'Bid' => '4.25001', 'Ask' => '4.34001'],
                    ['EffectiveDate' => '2024-01-03', 'Bid' => '4.25006', 'Ask' => '4.34006'],
                ],
            ],
        ]);

        $rates = $this->parser->parse('<xml/>');

        $this->assertSame(round(4.25006 - 4.25001, 4), $rates[1]->bidDiff);
        $this->assertSame(round(4.34006 - 4.34001, 4), $rates[1]->askDiff);
    }
}
