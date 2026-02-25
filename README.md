# NBP Exchange Rates API

REST API pobierające kursy walut z [API NBP](https://api.nbp.pl/) (tabela C).  
Zbudowane na **Symfony 7.4 i PHP 8.4**, zwraca kursy kupna i sprzedaży dla EUR, USD i CHF wraz z dzienną różnicą kursów.

---

## Wymagania

- PHP 8.3+
- Composer
- Symfony CLI _(opcjonalnie)_

---

## Instalacja

Skopiuj plik środowiskowy:

```bash
git clone <repo-url>
cd nbp_api
cp .env.example .env
composer install
```




| Zmienna          | Opis | Przykład |
|------------------|---|---|
| `NBP_URL`        | Bazowy URL API NBP | `https://api.nbp.pl/api` |
| `X_SYSTEM_TOKEN` | Token wymagany w nagłówku `X-TOKEN-SYSTEM` | `change-me` |

---

## Uruchomienie

```bash
symfony server:start
```

---

## Endpoint

### `GET /api/nbp/rates`

Zwraca kursy kupna i sprzedaży dla wybranej waluty w podanym zakresie dat.

#### Parametry query

| Parametr | Typ | Wymagany | Opis |
|---|---|---|---|
| `currencyCode` | `string` | ✅ | Kod waluty ISO 4217: `EUR`, `USD`, `CHF` |
| `dateFrom` | `date (Y-m-d)` | ✅ | Data początkowa zakresu |
| `dateTo` | `date (Y-m-d)` | ✅ | Data końcowa zakresu (max 7 dni od `dateFrom`) |

#### Wymagany nagłówek

```
X-TOKEN-SYSTEM: <wartość z X_SYSTEM_TOKEN>
```

#### Przykładowe zapytanie

```bash
curl -X GET "http://localhost:8000/api/nbp/rates?currencyCode=EUR&dateFrom=2024-01-02&dateTo=2024-01-05" \
  -H "X-TOKEN-SYSTEM: change-me"
```

#### Przykładowa odpowiedź `200 OK`

```json
[
  {
    "date": "2024-01-02",
    "bid": 4.2531,
    "ask": 4.3387,
    "bidDiff": null,
    "askDiff": null
  },
  {
    "date": "2024-01-03",
    "bid": 4.2612,
    "ask": 4.3471,
    "bidDiff": 0.0081,
    "askDiff": 0.0084
  }
]
```

> `bidDiff` i `askDiff` dla pierwszego dnia w zakresie są zawsze `null` — brak poprzedniego dnia do porównania.

#### Kody odpowiedzi

| Kod | Opis |
|---|---|
| `200` | Sukces |
| `400` | Błędny zakres dat (odwrócone daty lub więcej niż 7 dni) |
| `401` | Brak lub nieprawidłowy nagłówek `X-TOKEN-SYSTEM` |
| `422` | Błąd deserializacji parametrów (np. nieznana waluta, zły format daty) |
| `500` | Błąd komunikacji z API NBP |

---

## Dokumentacja OpenAPI

Po uruchomieniu serwera dokumentacja Swagger UI dostępna jest pod adresem:

```
http://localhost:8000/api/doc
```

---

## Struktura projektu

```
src/
├── Controller/
│   └── NbpController.php          # Endpoint GET /api/nbp/rates
│ ─ Enum/
│       └── CurrencyEnum.php       # EUR | USD | CHF
├── Dto/
│   ├── NbpRequestDto.php          # Parametry zapytania
│   └── NbpRateDto.php             # Pojedynczy kurs (odpowiedź)
├── EventListener/
│   └── TokenAuthListener.php      # Weryfikacja X-TOKEN-SYSTEM
├── Infrastructure/
│   ├── NbpApiClient.php           # Komunikacja HTTP z API NBP
│   └── NbpXmlParser.php           # Parsowanie XML → NbpRateDto[]
└── Service/
    └── NbpRatesService.php        # Walidacja dat, orkiestracja

tests/
└── Unit/
    ├── Controller/
    │   └── NbpControllerTest.php
    ├── EventListener/
    │   └── TokenAuthListenerTest.php
    ├── Infrastructure/
    │   ├── NbpApiClientTest.php
    │   └── NbpXmlParserTest.php
    └── Service/
        └── NbpRatesServiceTest.php
```

---

## Testy

```bash
php bin/phpunit --testdox
```

---
