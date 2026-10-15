<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'code' => 'STNK',
                'name' => 'STNK',
                'description' => 'Surat Tanda Nomor Kendaraan',
                'is_required' => true,
                'has_expiry_date' => true,
            ],
            [
                'code' => 'KEUR',
                'name' => 'KEUR',
                'description' => 'Surat Keterangan Uji Kendaraan Bermotor',
                'is_required' => true,
                'has_expiry_date' => true,
            ],
            [
                'code' => 'TERA',
                'name' => 'TERA',
                'description' => 'Surat Tera Tangki',
                'is_required' => true,
                'has_expiry_date' => true,
            ],
            [
                'code' => 'PTO',
                'name' => 'PTO',
                'description' => 'Surat Izin Pengoperasian (bila dipersyaratkan)',
                'is_required' => false,
                'has_expiry_date' => true,
            ],
        ];

        foreach ($types as $type) {
            DocumentType::updateOrCreate(['code' => $type['code']], $type);
        }
    }
}
