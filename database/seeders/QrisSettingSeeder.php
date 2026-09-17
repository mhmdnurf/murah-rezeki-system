<?php

namespace Database\Seeders;

use App\Models\QrisSetting;
use Illuminate\Database\Seeder;

class QrisSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $setting = QrisSetting::query()->find(QrisSetting::STORE_ID);

        if ($setting === null) {
            $setting = new QrisSetting(['merchant_name' => 'Toko Murah Rezeki']);
            $setting->id = QrisSetting::STORE_ID;
            $setting->save();
        }
    }
}
