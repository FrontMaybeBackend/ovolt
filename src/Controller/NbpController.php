<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\NbpRequestDto;
use App\Service\NbpService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/nbp', name: 'api_nbp_')]
#[OA\Tag(name: 'NBP Rates')]
final readonly class NbpController
{
    public function __construct(
        private NbpService $ratesService,
    ) {
    }

    #[Route('/rates', name: 'rates', methods: ['GET'])]
    #[OA\Get(
        path: '/api/nbp/rates',
        description: 'Zwraca kursy kupna/sprzedaży z tabeli C NBP dla wybranej waluty i zakresu dat (max 7 dni).',
        summary: 'Pobierz kursy walut NBP',
    )]
    public function getRates(#[MapQueryString] NbpRequestDto $query): JsonResponse
    {
        try {
            $rates = $this->ratesService->getRates(
                $query->currencyCode,
                $query->dateFrom,
                $query->dateTo,
            );

        } catch (\InvalidArgumentException $e) {

            return new JsonResponse(
                ['error' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );

        } catch (\RuntimeException $e) {

            return new JsonResponse(
                [
                    'error'  => 'Failed to fetch rates from NBP API',
                    'detail' => $e->getMessage()
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return new JsonResponse($rates, Response::HTTP_OK);
    }
}
