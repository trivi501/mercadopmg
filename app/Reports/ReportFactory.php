<?php

namespace App\Reports;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReportFactory
{
    /** @var array<string, class-string<Report>> */
    public const array REPORTS = [
        'merchants' => MerchantsReport::class,
        'revenue' => RevenueReport::class,
        'tickets' => TicketsReport::class,
        'annualities' => AnnualitiesReport::class,
        'reprints' => ReprintsReport::class,
    ];

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function make(string $key, array $filters = []): Report
    {
        $class = self::REPORTS[$key] ?? throw new NotFoundHttpException("Reporte [{$key}] no existe.");

        return new $class($filters);
    }
}
