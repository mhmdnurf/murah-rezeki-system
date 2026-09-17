<?php

use App\Models\QrisSetting;
use App\Models\Role;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Pengaturan QRIS')] class extends Component
{
    use WithFileUploads;

    public string $merchantName = '';

    public bool $isActive = false;

    public $qrImage;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasRole(Role::OWNER), 403);
        $setting = $this->setting;
        $this->merchantName = $setting?->merchant_name ?? 'Toko Murah Rezeki';
        $this->isActive = $setting?->is_active ?? false;
    }

    #[Computed]
    public function setting(): ?QrisSetting
    {
        return QrisSetting::query()->find(QrisSetting::STORE_ID);
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->hasRole(Role::OWNER), 403);
        $setting = QrisSetting::query()->find(QrisSetting::STORE_ID);
        $hasImage = $setting?->qr_image && Storage::disk('public')->exists($setting->qr_image);

        $this->validate([
            'merchantName' => ['required', 'string', 'max:255'],
            'isActive' => ['boolean'],
            'qrImage' => [Rule::requiredIf($this->isActive && ! $hasImage), 'nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ], [
            'qrImage.required' => 'Unggah gambar QRIS sebelum mengaktifkannya.',
            'qrImage.image' => 'File harus berupa gambar QRIS.',
            'qrImage.mimes' => 'Gunakan gambar PNG, JPG, atau WebP.',
            'qrImage.max' => 'Ukuran gambar maksimal 2 MB.',
        ]);

        $path = $this->qrImage?->store('qris', 'public');

        if ($this->qrImage && ! $path) {
            $this->addError('qrImage', 'Gambar gagal disimpan. Silakan coba kembali.');

            return;
        }

        try {
            $setting ??= new QrisSetting;
            $setting->id = QrisSetting::STORE_ID;
            $setting->fill([
                'merchant_name' => $this->merchantName,
                'is_active' => $this->isActive,
                'qr_image' => $path ?: $setting->qr_image,
            ])->save();
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }

            throw $exception;
        }

        $this->reset('qrImage');
        unset($this->setting);
        Flux::toast(variant: 'success', text: 'Pengaturan QRIS berhasil disimpan.');
    }
};
?>

<section class="space-y-5">
    <header class="border-b border-slate-200 pb-5">
        <flux:heading size="xl">Pengaturan QRIS</flux:heading>
        <flux:text>Pemilik / Pengaturan QRIS</flux:text>
    </header>
    <form wire:submit="save" class="max-w-xl space-y-5 rounded-xl border border-slate-200 bg-white p-5">
        <flux:input wire:model="merchantName" label="Nama Merchant" />
        <flux:input wire:model="qrImage" type="file" accept="image/png,image/jpeg,image/webp" label="Gambar QRIS" />
        <flux:text>PNG, JPG, atau WebP, maksimal 2 MB. Unggah QRIS resmi milik toko dan pastikan nama penerimanya sesuai.</flux:text>
        @if ($this->setting?->qr_image)
            <div class="space-y-2">
                <flux:text>Gambar tersimpan</flux:text>
                <img src="{{ Storage::disk('public')->url($this->setting->qr_image) }}" alt="QRIS {{ $this->setting->merchant_name }}" class="max-h-80 max-w-full rounded-lg object-contain" />
            </div>
        @endif
        <flux:checkbox wire:model="isActive" label="Tampilkan QRIS ini pada pembayaran kasir" />
        <flux:text>Jika tidak aktif, kasir tetap dapat menggunakan QRIS fisik toko dan mengonfirmasi pembayaran secara manual.</flux:text>
        <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save,qrImage">Simpan Pengaturan</flux:button>
        <flux:text wire:loading wire:target="qrImage">Mengunggah gambar…</flux:text>
    </form>
</section>
