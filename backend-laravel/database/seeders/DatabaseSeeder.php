<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Category;
use App\Models\Commodity;
use App\Models\Region;
use App\Models\Route;
use App\Models\StockMovement;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Variant;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. System Settings
        $settings = [
            'tax_rate' => '0.11',
            'admin_fee_rate' => '0.02',
            'admin_fee_min' => '2500',
            'ship_base_fee' => '15000',
            'ship_fee_per_kg_km' => '0.35',
            'payment_ttl_minutes' => '60',
        ];
        foreach ($settings as $key => $val) {
            SystemSetting::updateOrCreate(['key' => $key], ['value' => $val]);
        }

        // 2. Master Regions
        $regionsData = [
            ['name' => 'Bandung', 'province' => 'Jawa Barat', 'lat' => -6.9175, 'lng' => 107.6191],
            ['name' => 'Garut', 'province' => 'Jawa Barat', 'lat' => -7.2279, 'lng' => 107.9087],
            ['name' => 'Malang', 'province' => 'Jawa Timur', 'lat' => -7.9666, 'lng' => 112.6326],
            ['name' => 'Magelang', 'province' => 'Jawa Tengah', 'lat' => -7.4797, 'lng' => 110.2177],
            ['name' => 'Jakarta', 'province' => 'DKI Jakarta', 'lat' => -6.2088, 'lng' => 106.8456],
            ['name' => 'Surabaya', 'province' => 'Jawa Timur', 'lat' => -7.2575, 'lng' => 112.7521],
        ];
        $regions = [];
        foreach ($regionsData as $rd) {
            $regions[$rd['name']] = Region::updateOrCreate(['name' => $rd['name']], $rd);
        }

        // 3. Master Categories
        $categoriesData = [
            ['name' => 'Sayuran', 'slug' => 'sayuran'],
            ['name' => 'Buah-buahan', 'slug' => 'buah-buahan'],
            ['name' => 'Biji-bijian & Beras', 'slug' => 'biji-bijian-beras'],
            ['name' => 'Rempah-rempah', 'slug' => 'rempah-rempah'],
            ['name' => 'Umbi-umbian', 'slug' => 'umbi-umbian'],
        ];
        $categories = [];
        foreach ($categoriesData as $cd) {
            $categories[$cd['slug']] = Category::updateOrCreate(['slug' => $cd['slug']], $cd);
        }

        // 4. Default Users for 5-Level RBAC
        $password = Hash::make('Password123');

        $superAdmin = User::updateOrCreate(
            ['email' => 'admin@rumahtani.id'],
            [
                'name' => 'Super Administrator Ekosistem',
                'password' => $password,
                'role' => 'super_admin',
                'phone' => '081200000001',
                'kyc_status' => 'approved',
                'is_active' => true,
            ]
        );

        $tengkulak = User::updateOrCreate(
            ['email' => 'tengkulak@rumahtani.id'],
            [
                'name' => 'Distributor Wilayah Priangan (Tengkulak)',
                'password' => $password,
                'role' => 'admin_tengkulak',
                'region_id' => $regions['Bandung']->id,
                'company_name' => 'CV Logistik Tani Mandiri',
                'phone' => '081200000002',
                'kyc_status' => 'approved',
                'is_active' => true,
            ]
        );

        $petani = User::updateOrCreate(
            ['email' => 'petani@rumahtani.id'],
            [
                'name' => 'Pak Asep Sunandar (Kelompok Tani Garut)',
                'password' => $password,
                'role' => 'petani',
                'region_id' => $regions['Garut']->id,
                'phone' => '081200000003',
                'address' => 'Desa Cibatu, Garut, Jawa Barat',
                'kyc_status' => 'approved',
                'is_active' => true,
            ]
        );

        $buyerB2b = User::updateOrCreate(
            ['email' => 'hotel@rumahtani.id'],
            [
                'name' => 'PT Hotel & Restoran Nusantara',
                'password' => $password,
                'role' => 'pembeli',
                'buyer_type' => 'b2b',
                'company_name' => 'PT Hotel & Restoran Nusantara',
                'region_id' => $regions['Jakarta']->id,
                'phone' => '081200000004',
                'address' => 'Jl. MH Thamrin No. 45, Jakarta Pusat',
                'kyc_status' => 'approved',
                'is_active' => true,
            ]
        );

        $buyerB2c = User::updateOrCreate(
            ['email' => 'umum@rumahtani.id'],
            [
                'name' => 'Budi Santoso',
                'password' => $password,
                'role' => 'pembeli',
                'buyer_type' => 'b2c',
                'region_id' => $regions['Bandung']->id,
                'phone' => '081200000005',
                'address' => 'Jl. Dago Asri No. 12, Bandung',
                'kyc_status' => 'approved',
                'is_active' => true,
            ]
        );

        $supportAdmin = User::updateOrCreate(
            ['email' => 'support@rumahtani.id'],
            [
                'name' => 'Admin Support L3 Operasional',
                'password' => $password,
                'role' => 'admin',
                'phone' => '081200000006',
                'address' => 'Kantor Pusat Operasional Rumah Tani',
                'kyc_status' => 'approved',
                'is_active' => true,
            ]
        );

        // 5. Commodities & Variants
        $c1 = Commodity::updateOrCreate(
            ['slug' => 'cabai-rawit-merah-garut'],
            [
                'farmer_id' => $petani->id,
                'region_id' => $regions['Garut']->id,
                'category_id' => $categories['sayuran']->id,
                'name' => 'Cabai Rawit Merah Segar Garut',
                'description' => 'Cabai rawit merah petik langsung dari kebun dataran tinggi Garut. Pedas alami, segar, tanpa pengawet pestisida berlebih.',
                'image_url' => 'https://images.unsplash.com/photo-1588252303782-cb80119abd6d?w=800',
                'curation' => 'approved',
                'curated_by' => $tengkulak->id,
                'is_active' => true,
            ]
        );

        $v1Data = [
            ['grade' => 'A', 'variant_name' => 'Cabai Super Grade A', 'price' => 65000, 'stock_qty' => 500, 'min_order' => 5],
            ['grade' => 'B', 'variant_name' => 'Cabai Standar Grade B', 'price' => 54000, 'stock_qty' => 800, 'min_order' => 10],
            ['grade' => 'C', 'variant_name' => 'Cabai Olahan Grade C', 'price' => 42000, 'stock_qty' => 300, 'min_order' => 20],
        ];
        foreach ($v1Data as $vd) {
            $v = Variant::updateOrCreate(
                ['commodity_id' => $c1->id, 'grade' => $vd['grade'], 'variant_name' => $vd['variant_name']],
                array_merge($vd, ['unit' => 'kg', 'weight_per_unit_kg' => 1.0, 'reserved_qty' => 0])
            );
            StockMovement::create([
                'variant_id' => $v->id,
                'delta' => $v->stock_qty,
                'reason' => 'harvest',
                'actor_id' => $petani->id,
            ]);
        }

        $c2 = Commodity::updateOrCreate(
            ['slug' => 'beras-pandan-wangi-garut'],
            [
                'farmer_id' => $petani->id,
                'region_id' => $regions['Garut']->id,
                'category_id' => $categories['biji-bijian-beras']->id,
                'name' => 'Beras Pandan Wangi Organik Garut',
                'description' => 'Beras pulen beraroma pandan alami, dipanen dari sawah irigasi pegunungan Garut.',
                'image_url' => 'https://images.unsplash.com/photo-1586201375761-83865001e31c?w=800',
                'curation' => 'approved',
                'curated_by' => $tengkulak->id,
                'is_active' => true,
            ]
        );

        $v2Data = [
            ['grade' => 'A', 'variant_name' => 'Beras Premium Grade A', 'price' => 18500, 'stock_qty' => 2000, 'min_order' => 25],
            ['grade' => 'B', 'variant_name' => 'Beras Medium Grade B', 'price' => 15500, 'stock_qty' => 3500, 'min_order' => 50],
        ];
        foreach ($v2Data as $vd) {
            $v = Variant::updateOrCreate(
                ['commodity_id' => $c2->id, 'grade' => $vd['grade'], 'variant_name' => $vd['variant_name']],
                array_merge($vd, ['unit' => 'kg', 'weight_per_unit_kg' => 1.0, 'reserved_qty' => 0])
            );
            StockMovement::create([
                'variant_id' => $v->id,
                'delta' => $v->stock_qty,
                'reason' => 'harvest',
                'actor_id' => $petani->id,
            ]);
        }

        // 6. Fleet Vehicles for Tengkulak
        Vehicle::updateOrCreate(
            ['plate' => 'D 8912 ABC'],
            [
                'owner_id' => $tengkulak->id,
                'type' => 'Pickup Mitsubishi L300',
                'capacity_kg' => 1500,
                'driver_name' => 'Kang Maman',
                'is_available' => true,
            ]
        );

        Vehicle::updateOrCreate(
            ['plate' => 'D 7102 XYZ'],
            [
                'owner_id' => $tengkulak->id,
                'type' => 'Truk Engkel Isuzu Elf Box Pendingin',
                'capacity_kg' => 4500,
                'driver_name' => 'Pak Dedi',
                'is_available' => true,
            ]
        );

        // 7. Regional Routes
        Route::updateOrCreate(
            [
                'owner_id' => $tengkulak->id,
                'origin_region_id' => $regions['Garut']->id,
                'dest_region_id' => $regions['Jakarta']->id,
            ],
            [
                'base_fee' => 35000,
                'fee_per_kg_km' => 0.40,
            ]
        );

        // 8. Articles (CMS Pertanian)
        Article::updateOrCreate(
            ['slug' => 'selamat-datang-di-rumah-tani'],
            [
                'title' => 'Selamat Datang di Rumah Tani: Solusi Rantai Pasok Hulu ke Hilir',
                'excerpt' => 'Digitalisasi rantai pasok pertanian Indonesia menghubungkan produsen, distributor, dan konsumen secara adil dan transparan.',
                'body' => "Rumah Tani hadir untuk memotong inefisiensi rantai distribusi pertanian konvensional.\n\nDengan sistem multi-peran (Petani, Tengkulak/Distributor, Pembeli B2B/B2C, dan Super Admin), transparansi harga serta ketersediaan stok dapat dipantau secara real-time.",
                'author_id' => $superAdmin->id,
                'published' => true,
                'published_at' => now(),
            ]
        );

        Article::updateOrCreate(
            ['slug' => 'panduan-klasifikasi-grade-hasil-bumi'],
            [
                'title' => 'Panduan Klasifikasi Grade Hasil Bumi: Grade A, B, dan C',
                'excerpt' => 'Pelajari standar mutu komoditas sayuran dan beras untuk memenuhi spesifikasi pasar hotel, restoran, dan katering.',
                'body' => "Untuk memastikan transparansi transaksi, Rumah Tani menetapkan 3 klasifikasi kualitas:\n- Grade A: Kualitas premium (bebas cacat fisik, seragam, kadar air optimal).\n- Grade B: Kualitas standar pasar basah dan konsumsi rumah tangga.\n- Grade C: Kualitas industri olahan dan saus pasta.",
                'author_id' => $superAdmin->id,
                'published' => true,
                'published_at' => now(),
            ]
        );
    }
}
