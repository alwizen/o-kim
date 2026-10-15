<?php

namespace Database\Seeders;

use App\Models\InspectionChecklist;
use Illuminate\Database\Seeder;

class InspectionChecklistSeeder extends Seeder
{
    /**
     * Butir checklist awal. Sebagian diambil dari contoh pada `tambahan.md`.
     * Butir final wajib dikonfirmasi dengan masing-masing fungsi (PRD bagian 15).
     */
    public function run(): void
    {
        $checklists = [
            'hsse' => [
                'title' => 'Checklist HSSE',
                'items' => [
                    [
                        'item_code' => 'HSSE-01',
                        'label' => 'Sakelar Keamanan / Safety Switch',
                        'points' => [
                            'Dipasang pada dashboard di dalam kabin',
                            'Dipasang pada bagian luar mobil tangki sisi pengemudi dekat accu',
                            'Berfungsi memutus aliran listrik saat kondisi darurat',
                        ],
                    ],
                    [
                        'item_code' => 'HSSE-02',
                        'label' => 'Kelengkapan APAR pada kendaraan',
                        'points' => [
                            'Tersedia minimal 1 unit APAR bersertifikat',
                            'Tekanan APAR dalam batas normal dan belum kedaluwarsa',
                            'Ditempatkan pada tempat yang mudah dijangkau',
                        ],
                    ],
                    [
                        'item_code' => 'HSSE-03',
                        'label' => 'Kelengkapan rambu keselamatan dan PPE',
                        'points' => [
                            'Rambu tangki berisi bahan mudah terbakar terpasang',
                            'Tersedia alat pelindung diri untuk pengemudi',
                        ],
                    ],
                ],
            ],
            'distribusi' => [
                'title' => 'Checklist Distribusi',
                'items' => [
                    [
                        'item_code' => 'DIST-01',
                        'label' => 'Konstruksi Sisi Depan & Belakang',
                        'points' => [
                            'Tiap kotak memiliki buangan air (drainase)',
                            'Mur-baut high tensile dengan self-locking nut',
                            'Tidak ada keretakan atau korosi pada rangka',
                        ],
                    ],
                    [
                        'item_code' => 'DIST-02',
                        'label' => 'Kesesuaian dokumen angkutan',
                        'points' => [
                            'Surat jalan sesuai dengan muatan',
                            'Dokumen kendaraan tersedia dan berlaku',
                        ],
                    ],
                    [
                        'item_code' => 'DIST-03',
                        'label' => 'Kondisi tangki dan segel',
                        'points' => [
                            'Segel dalam kondisi utuh dan bernomor resmi',
                            'Tidak ada kebocoran pada sambungan',
                        ],
                    ],
                ],
            ],
            'qq' => [
                'title' => 'Checklist QQ (Quantity & Quality)',
                'items' => [
                    [
                        'item_code' => 'QQ-01',
                        'label' => 'Kalibrasi dan tera tangki',
                        'points' => [
                            'Sertifikat tera masih berlaku',
                            'Segel tera terpasang dan tidak rusak',
                        ],
                    ],
                    [
                        'item_code' => 'QQ-02',
                        'label' => 'Kondisi kompartemen dan valve',
                        'points' => [
                            'Valve tiap kompartemen berfungsi baik',
                            'Kebersihan bagian dalam tangki terjaga',
                        ],
                    ],
                ],
            ],
        ];

        foreach ($checklists as $department => $data) {
            $checklist = InspectionChecklist::updateOrCreate(
                ['code' => strtoupper($department).'-STD'],
                [
                    'department' => $department,
                    'title' => $data['title'],
                    'is_required' => true,
                    'is_active' => true,
                ]
            );

            foreach ($data['items'] as $index => $item) {
                $record = $checklist->items()->updateOrCreate(
                    ['item_code' => $item['item_code']],
                    [
                        'label' => $item['label'],
                        'sort_order' => $index + 1,
                        'is_required' => true,
                        'is_active' => true,
                    ]
                );

                foreach ($item['points'] as $pointIndex => $pointLabel) {
                    $record->points()->updateOrCreate(
                        ['label' => $pointLabel],
                        [
                            'sort_order' => $pointIndex + 1,
                            'is_active' => true,
                        ]
                    );
                }
            }
        }
    }
}
