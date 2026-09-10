<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportService
{
    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return [
            'startDate' => ['required', 'date_format:Y-m-d'],
            'endDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:startDate'],
            'cashierId' => ['nullable', 'integer', 'exists:users,id'],
            'paymentMethod' => ['nullable', Rule::in(['CASH', 'QRIS'])],
            'status' => ['nullable', Rule::in(['PAID', 'DRAFT', 'CANCELLED'])],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'startDate.required' => 'Tanggal mulai wajib diisi.',
            'endDate.required' => 'Tanggal selesai wajib diisi.',
            'startDate.date_format' => 'Tanggal mulai tidak valid.',
            'endDate.date_format' => 'Tanggal selesai tidak valid.',
            'endDate.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
            'cashierId.exists' => 'Kasir tidak ditemukan.',
            'cashierId.integer' => 'Kasir tidak valid.',
            'paymentMethod.in' => 'Metode pembayaran tidak valid.',
            'status.in' => 'Status transaksi tidak valid.',
        ];
    }

    /** @return array{startDate: string, endDate: string, cashierId: string, paymentMethod: string, status: string} */
    public function defaults(): array
    {
        return ['startDate' => now()->startOfMonth()->toDateString(), 'endDate' => now()->toDateString(), 'cashierId' => '', 'paymentMethod' => '', 'status' => 'PAID'];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Sale>
     */
    public function query(array $filters): Builder
    {
        return Sale::query()
            ->where('transaction_date', '>=', $filters['startDate'])
            ->where('transaction_date', '<', CarbonImmutable::parse($filters['endDate'])->addDay()->toDateString())
            ->when($filters['cashierId'] ?? null, fn (Builder $query, mixed $id) => $query->where('user_id', $id))
            ->when($filters['paymentMethod'] ?? null, fn (Builder $query, string $method) => $query->whereHas('payments', fn (Builder $payments) => $payments->where('method', $method)))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Sale>
     */
    public function details(array $filters): Builder
    {
        return $this->query($filters)->with(['cashier:id,name', 'payments:id,sale_id,method'])
            ->withSum('items', 'quantity')->orderByDesc('transaction_date')->orderByDesc('id');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{transactions: int, revenue: string, items: int}
     */
    public function summary(array $filters): array
    {
        $query = $this->query($filters);

        return [
            'transactions' => (clone $query)->count(),
            'revenue' => (string) (clone $query)->where('status', Sale::STATUS_PAID)->sum('total'),
            'items' => (int) SaleItem::query()->whereIn('sale_id', (clone $query)->select('sales.id'))->sum('quantity'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    public function labels(array $filters): array
    {
        return [
            'Periode' => $filters['startDate'].' s/d '.$filters['endDate'],
            'Kasir' => empty($filters['cashierId']) ? 'Semua kasir' : (User::find($filters['cashierId'])?->name ?? '—'),
            'Pembayaran' => self::paymentLabel($filters['paymentMethod'] ?? '') ?: 'Semua metode',
            'Status' => self::statusLabel($filters['status'] ?? '') ?: 'Semua status',
        ];
    }

    public static function paymentLabel(string $method): string
    {
        return $method === 'CASH' ? 'Tunai' : $method;
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'PAID' => 'Lunas',
            'DRAFT' => 'Draf',
            'CANCELLED' => 'Dibatalkan',
            default => $status,
        };
    }

    /** @return list<string|int|float> */
    public function row(Sale $sale): array
    {
        return [$sale->invoice_number, $sale->transaction_date->format('d/m/Y'), $sale->cashier?->name ?? '—', (int) $sale->items_sum_quantity, (float) $sale->total, $sale->payments->pluck('method')->unique()->map(fn (string $method) => self::paymentLabel($method))->implode(', ') ?: '—', self::statusLabel($sale->status)];
    }

    /** @param array<string, mixed> $filters */
    public function pdf(array $filters): string
    {
        $pdf = new Dompdf(new Options([
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'fontCache' => storage_path('framework/cache'),
        ]));
        $pdf->loadHtml(view('pages.owner.report-pdf', [
            'labels' => $this->labels($filters),
            'summary' => $this->summary($filters),
            'sales' => $this->details($filters)->lazy(500),
            'reports' => $this,
        ])->render());
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();
        $pdf->getCanvas()->page_text(730, 570, '{PAGE_NUM} / {PAGE_COUNT}', null, 8);

        return $pdf->output();
    }

    /** @param array<string, mixed> $filters */
    public function excel(array $filters): void
    {
        $spreadsheet = new Spreadsheet;

        try {
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Laporan Penjualan');
            $sheet->setCellValue('A1', 'Laporan Penjualan — Toko Murah Rezeki');
            $sheet->mergeCells('A1:G1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $rowNumber = 3;
            foreach ($this->labels($filters) as $label => $value) {
                $sheet->setCellValue('A'.$rowNumber, $label);
                $sheet->setCellValueExplicit('B'.$rowNumber, $value, DataType::TYPE_STRING);
                $rowNumber++;
            }

            $summary = $this->summary($filters);
            $sheet->fromArray([
                ['Total Transaksi', $summary['transactions']],
                ['Total Penjualan Lunas', (float) $summary['revenue']],
                ['Jumlah Unit Barang', $summary['items']],
            ], null, 'A8');
            $sheet->getStyle('B9')->getNumberFormat()->setFormatCode('"Rp" #,##0.00');
            $sheet->setCellValue('A12', 'Omzet hanya transaksi lunas; jumlah transaksi dan unit mengikuti filter.');
            $sheet->mergeCells('A12:G12');
            $sheet->fromArray(['Invoice', 'Tanggal', 'Kasir', 'Jumlah Unit', 'Total', 'Pembayaran', 'Status'], null, 'A14');
            $sheet->getStyle('A14:G14')->getFont()->setBold(true);
            $sheet->getStyle('A14:G14')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2E8F0');

            $rowNumber = 15;
            foreach ($this->details($filters)->lazy(500) as $sale) {
                foreach ($this->row($sale) as $index => $value) {
                    $sheet->setCellValueExplicit([$index + 1, $rowNumber], $value, in_array($index, [3, 4], true) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
                }
                $rowNumber++;
            }

            if ($rowNumber > 15) {
                $sheet->getStyle('E15:E'.($rowNumber - 1))->getNumberFormat()->setFormatCode('"Rp" #,##0.00');
            } else {
                $sheet->setCellValue('A15', 'Tidak ada transaksi pada filter ini.');
            }
            $sheet->setAutoFilter('A14:G'.max(14, $rowNumber - 1));
            $sheet->freezePane('A15');
            foreach (['A' => 32, 'B' => 24, 'C' => 28, 'D' => 15, 'E' => 22, 'F' => 20, 'G' => 18] as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }
            (new Xlsx($spreadsheet))->save('php://output');
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }
}
