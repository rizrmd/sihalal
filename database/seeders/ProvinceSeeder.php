<?php

namespace Database\Seeders;

use App\Models\Province;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProvinceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $provinces = [
            ["code" => "11", "name" => "ACEH"],
            ["code" => "51", "name" => "BALI"],
            ["code" => "17", "name" => "BENGKULU"],
            ["code" => "36", "name" => "Banten"],
            ["code" => "34", "name" => "DI YOGYAKARTA"],
            ["code" => "31", "name" => "DKI Jakarta"],
            ["code" => "75", "name" => "GORONTALO"],
            ["code" => "15", "name" => "JAMBI"],
            ["code" => "33", "name" => "JAWA TENGAH"],
            ["code" => "35", "name" => "JAWA TIMUR"],
            ["code" => "32", "name" => "Jawa Barat"],
            ["code" => "61", "name" => "KALIMANTAN BARAT"],
            ["code" => "63", "name" => "KALIMANTAN SELATAN"],
            ["code" => "62", "name" => "KALIMANTAN TENGAH"],
            ["code" => "64", "name" => "KALIMANTAN TIMUR"],
            ["code" => "65", "name" => "KALIMANTAN UTARA"],
            ["code" => "19", "name" => "KEPULAUAN BANGKA BELITUNG"],
            ["code" => "21", "name" => "KEPULAUAN RIAU"],
            ["code" => "18", "name" => "LAMPUNG"],
            ["code" => "81", "name" => "MALUKU"],
            ["code" => "82", "name" => "MALUKU UTARA"],
            ["code" => "52", "name" => "NUSA TENGGARA BARAT"],
            ["code" => "53", "name" => "NUSA TENGGARA TIMUR"],
            ["code" => "91", "name" => "PAPUA"],
            ["code" => "92", "name" => "PAPUA BARAT"],
            ["code" => "96", "name" => "PAPUA BARAT DAYA"],
            ["code" => "95", "name" => "PAPUA PEGUNUNGAN"],
            ["code" => "93", "name" => "PAPUA SELATAN"],
            ["code" => "94", "name" => "PAPUA TENGAH"],
            ["code" => "00", "name" => "PUSAT"],
            ["code" => "14", "name" => "RIAU"],
            ["code" => "76", "name" => "SULAWESI BARAT"],
            ["code" => "73", "name" => "SULAWESI SELATAN"],
            ["code" => "72", "name" => "SULAWESI TENGAH"],
            ["code" => "74", "name" => "SULAWESI TENGGARA"],
            ["code" => "71", "name" => "SULAWESI UTARA"],
            ["code" => "13", "name" => "SUMATERA BARAT"],
            ["code" => "16", "name" => "SUMATERA SELATAN"],
            ["code" => "12", "name" => "Sumatera Utara"],
        ];

        foreach ($provinces as $province) {
            Province::updateOrCreate(
                ['code' => $province['code']],
                ['name' => $province['name']]
            );
        }
    }
}
