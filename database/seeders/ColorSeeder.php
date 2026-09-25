<?php

namespace Database\Seeders;

use App\Models\Color;
use Illuminate\Database\Seeder;

class ColorSeeder extends Seeder
{
    public function run(): void
    {
        $colors = [

            ['name' => 'Black',        'code' => '#000000'],
            ['name' => 'White',        'code' => '#FFFFFF'],
            ['name' => 'Gray',         'code' => '#808080'],
            ['name' => 'Light Gray',   'code' => '#D3D3D3'],
            ['name' => 'Silver',       'code' => '#C0C0C0'],

            ['name' => 'Red',          'code' => '#FF0000'],
            ['name' => 'Dark Red',     'code' => '#8B0000'],
            ['name' => 'Blue',         'code' => '#0000FF'],
            ['name' => 'Navy',         'code' => '#000080'],
            ['name' => 'Sky Blue',     'code' => '#87CEEB'],

            ['name' => 'Green',        'code' => '#008000'],
            ['name' => 'Dark Green',   'code' => '#006400'],
            ['name' => 'Olive',        'code' => '#808000'],
            ['name' => 'Lime',         'code' => '#00FF00'],

            ['name' => 'Yellow',       'code' => '#FFFF00'],
            ['name' => 'Orange',       'code' => '#FFA500'],

            ['name' => 'Purple',       'code' => '#800080'],
            ['name' => 'Violet',       'code' => '#8A2BE2'],
            ['name' => 'Pink',         'code' => '#FFC0CB'],

            ['name' => 'Brown',        'code' => '#8B4513'],
            ['name' => 'Beige',        'code' => '#F5F5DC'],
            ['name' => 'Cream',        'code' => '#FFFDD0'],

            ['name' => 'Gold',         'code' => '#FFD700'],
            ['name' => 'Rose Gold',    'code' => '#B76E79'],
            ['name' => 'Bronze',       'code' => '#CD7F32'],

            ['name' => 'Transparent',  'code' => null],
            ['name' => 'Multicolor',   'code' => null],

        ];

        foreach ($colors as $color) {

            Color::firstOrCreate(
                ['name' => $color['name']],
                [
                    'code' => $color['code'],
                    'status' => true,
                ]
            );

        }
    }
}