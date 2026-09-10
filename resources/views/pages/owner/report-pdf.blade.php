<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Penjualan</title>
    <style>
        @page { margin: 32px 28px 42px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #334155; }
        h1 { font-size: 20px; color: #0f172a; margin-bottom: 4px; }
        h2 { font-size: 12px; font-weight: normal; margin-top: 0; }
        .filters { margin: 16px 0; line-height: 1.8; }
        .summary { background: #f1f5f9; padding: 12px; margin-bottom: 18px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th { background: #e2e8f0; text-align: left; }
        th, td { padding: 8px 5px; border-bottom: 1px solid #e2e8f0; overflow-wrap: break-word; }
        tr { page-break-inside: avoid; }
        thead { display: table-header-group; }
        .number { text-align: right; }
        .note { font-size: 8px; color: #64748b; }
        footer { position: fixed; bottom: -25px; font-size: 8px; color: #64748b; }
    </style>
</head>
<body>
    <h1>Laporan Penjualan</h1>
    <h2>Toko Murah Rezeki</h2>
    <div class="filters">
        @foreach ($labels as $label => $value)
            <div><strong>{{ $label }}:</strong> {{ $value }}</div>
        @endforeach
    </div>
    <div class="summary">
        Total Transaksi: <strong>{{ number_format($summary['transactions'], 0, ',', '.') }}</strong>
        &nbsp; | &nbsp; Total Penjualan Lunas: <strong>Rp{{ number_format((float) $summary['revenue'], 0, ',', '.') }}</strong>
        &nbsp; | &nbsp; Jumlah Unit Barang: <strong>{{ number_format($summary['items'], 0, ',', '.') }}</strong>
    </div>
    <p class="note">Total penjualan hanya menghitung transaksi lunas. Jumlah transaksi dan unit barang mengikuti filter yang dipilih.</p>
    <table>
        <thead><tr><th style="width: 23%">Invoice</th><th style="width: 11%">Tanggal</th><th style="width: 18%">Kasir</th><th style="width: 7%" class="number">Unit</th><th style="width: 16%" class="number">Total</th><th style="width: 13%">Pembayaran</th><th style="width: 12%">Status</th></tr></thead>
        <tbody>
            @forelse ($sales as $sale)
                @php($row = $reports->row($sale))
                <tr>
                    @foreach ($row as $index => $value)
                        <td @class(['number' => in_array($index, [3, 4])])>{{ $index === 4 ? 'Rp'.number_format($value, 0, ',', '.') : $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="7">Tidak ada transaksi pada filter ini.</td></tr>
            @endforelse
        </tbody>
    </table>
    <footer>Dicetak pada {{ now()->format('d/m/Y H:i') }} · Toko Murah Rezeki</footer>
</body>
</html>
