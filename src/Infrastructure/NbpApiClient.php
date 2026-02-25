<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Dto\NbpRateDto;
use App\Enum\CurrencyEnum;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Klient HTTP do pobierania kursów walut z API NBP (tabela C, format XML).
 * Odpowiada wyłącznie za komunikację HTTP — parsowanie deleguje do NbpXmlParser.
 */
class NbpApiClient
{
    /** Tabela kursów walut NBP – tabela C zawiera kursy kupna i sprzedaży */
    private const string TABLE = 'C';

    /** Format odpowiedzi API */
    private const string FORMAT = 'xml';

    /**
     * @param string              $nbpUrl     Bazowy URL API NBP (z env APP_NBP_URL)
     * @param LoggerInterface     $logger     Logger
     * @param HttpClientInterface $httpClient Klient HTTP Symfony
     * @param NbpXmlParser        $parser     Parser odpowiedzi XML
     */
    public function __construct(
        private readonly string $nbpUrl,
        private readonly LoggerInterface $logger,
        private readonly HttpClientInterface $httpClient,
        private readonly NbpXmlParser $parser,
    ) {
    }

    /**
     * Pobiera kursy waluty z API NBP dla podanego zakresu dat.
     *
     * @param CurrencyEnum       $currencyCode Kod waluty (EUR, USD, CHF)
     * @param \DateTimeImmutable $dateFrom     Data początkowa zakresu
     * @param \DateTimeImmutable $dateTo       Data końcowa zakresu
     *
     * @return NbpRateDto[]
     *
     * @throws \RuntimeException Gdy API NBP zwróci błąd lub brak danych
     */
    public function fetchRates(CurrencyEnum $currencyCode, \DateTimeImmutable $dateFrom, \DateTimeImmutable $dateTo): array
    {
        $url = $this->buildUrl($currencyCode, $dateFrom, $dateTo);

        $this->logger->info('Fetching NBP exchange rates', [
            'url'      => $url,
            'currency' => $currencyCode->value,
            'from'     => $dateFrom->format('Y-m-d'),
            'to'       => $dateTo->format('Y-m-d'),
        ]);

        $content = $this->doRequest($url, $currencyCode);
        $rates   = $this->parser->parse($content);

        $this->logger->info('NBP rates fetched successfully', ['count' => count($rates)]);

        return $rates;
    }

    /**
     * Wykonuje żądanie HTTP GET i zwraca treść odpowiedzi.
     *
     * @throws \RuntimeException Gdy API zwróci status inny niż 200
     */
    private function doRequest(string $url, CurrencyEnum $currencyCode): string
    {
        $response   = $this->httpClient->request('GET', $url);
        $statusCode = $response->getStatusCode();

        if ($statusCode !== 200) {
            $this->logger->error('NBP API error', ['status_code' => $statusCode, 'url' => $url]);

            throw new \RuntimeException(
                sprintf('NBP API returned HTTP %d for currency %s', $statusCode, $currencyCode->value)
            );
        }

        return $response->getContent();
    }

    /**
     * Buduje URL do API NBP dla podanej waluty i zakresu dat.
     */
    private function buildUrl(CurrencyEnum $currencyCode, \DateTimeImmutable $dateFrom, \DateTimeImmutable $dateTo): string
    {
        return sprintf(
            '%s/%s/%s/%s/%s/?format=%s',
            rtrim($this->nbpUrl, '/'),
            self::TABLE,
            strtolower($currencyCode->value),
            $dateFrom->format('Y-m-d'),
            $dateTo->format('Y-m-d'),
            self::FORMAT,
        );
    }
}
