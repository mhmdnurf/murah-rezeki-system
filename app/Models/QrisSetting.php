<?php

namespace App\Models;

use Database\Factories\QrisSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['merchant_name', 'qr_image', 'is_active'])]
class QrisSetting extends Model
{
    public const STORE_ID = 1;

    /** @use HasFactory<QrisSettingFactory> */
    use HasFactory;

    protected $attributes = ['is_active' => false];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
