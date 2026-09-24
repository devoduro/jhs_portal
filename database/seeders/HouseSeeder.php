<?php

namespace Database\Seeders;

use App\Models\House;
use Illuminate\Database\Seeder;

class HouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $houses = [
            'Aggrey House',
            'Nkrumah House',
            'Azikiwe House',
            'Danquah House',
        ];

        foreach ($houses as $name) {
            House::firstOrCreate(['name' => $name]);
        }
    }
}
