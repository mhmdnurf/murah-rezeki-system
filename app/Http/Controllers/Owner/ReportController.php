<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalesReportRequest;
use App\Services\ReportService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function pdf(SalesReportRequest $request): Response
    {
        $filters = $request->validated();

        return response($this->reports->pdf($filters), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="laporan-penjualan-'.$filters['startDate'].'-'.$filters['endDate'].'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function excel(SalesReportRequest $request): StreamedResponse
    {
        $filters = $request->validated();

        return response()->streamDownload(function () use ($filters): void {
            $this->reports->excel($filters);
        }, 'laporan-penjualan-'.$filters['startDate'].'-'.$filters['endDate'].'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
