<?php

namespace Database\Factories;

use App\Models\QrisSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QrisSetting>
 */
class QrisSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_name' => 'Toko Murah Rezeki',
            'qr_image' => null,
            'is_active' => false,
        ];
    }
}
