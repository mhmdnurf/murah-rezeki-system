<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Struk {{ $sale->invoice_number }}</title>
        @vite(['resources/css/receipt.css', 'resources/js/receipt.js'])
    </head>
    <body>
        <main class="receipt-page">
            <nav class="receipt-toolbar" aria-label="Aksi struk">
                <a href="{{ route($backRoute) }}">Kembali</a>
                <button type="button" data-print-receipt>Cetak Struk</button>
            </nav>

            <article class="receipt" aria-label="Struk transaksi {{ $sale->invoice_number }}">
                <header class="receipt-header">
                    <h1>Toko Murah Rezeki</h1>
                    <p>Jl. Brigjen Katamso, Tanjungpinang</p>
                </header>

                <section class="receipt-meta">
                    <div><span>Invoice</span><strong>{{ $sale->invoice_number }}</strong></div>
                    <div><span>Tanggal</span><strong>{{ $sale->created_at->format('d/m/Y H:i') }}</strong></div>
                    <div><span>Kasir</span><strong>{{ $sale->cashier->name }}</strong></div>
                </section>

                <section class="receipt-items" aria-label="Daftar barang">
                    @foreach ($sale->items as $item)
                        <div class="receipt-item">
                            <p>{{ $item->product?->name ?? 'Produk tidak tersedia' }}</p>
                            <div>
                                <span>{{ $item->quantity }} × Rp{{ number_format((int) $item->unit_price, 0, ',', '.') }}</span>
                                <strong>Rp{{ number_format((int) $item->subtotal, 0, ',', '.') }}</strong>
                            </div>
                        </div>
                    @endforeach
                </section>

                <section class="receipt-totals">
                    <div><span>Subtotal</span><strong>Rp{{ number_format((int) $sale->subtotal, 0, ',', '.') }}</strong></div>
                    @if ((int) $sale->discount > 0)
                        <div><span>Diskon</span><strong>-Rp{{ number_format((int) $sale->discount, 0, ',', '.') }}</strong></div>
                    @endif
                    <div class="receipt-grand-total"><span>Total</span><strong>Rp{{ number_format((int) $sale->total, 0, ',', '.') }}</strong></div>
                    <div><span>Pembayaran</span><strong>{{ $payment?->method === \App\Models\Payment::METHOD_QRIS ? 'QRIS' : 'Tunai' }}</strong></div>
                    @if ($payment?->method === \App\Models\Payment::METHOD_CASH)
                        <div><span>Uang diterima</span><strong>Rp{{ number_format((int) $payment->received_amount, 0, ',', '.') }}</strong></div>
                        <div><span>Kembalian</span><strong>Rp{{ number_format((int) $payment->change_amount, 0, ',', '.') }}</strong></div>
                    @else
                        <div><span>Status QRIS</span><strong>Dikonfirmasi kasir</strong></div>
                    @endif
                </section>

                <footer>
                    <p>Terima kasih telah berbelanja.</p>
                    <p>Barang yang sudah dibeli harap diperiksa kembali.</p>
                </footer>
            </article>
        </main>
    </body>
</html>
