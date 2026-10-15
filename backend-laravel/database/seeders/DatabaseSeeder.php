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

        // 2. Master Regions (Wilayah Asal Lahan Indonesia)
        $regionsData = [
            // Jawa Barat
            ['name' => 'Garut', 'province' => 'Jawa Barat', 'lat' => -7.2279, 'lng' => 107.9087],
            ['name' => 'Bandung', 'province' => 'Jawa Barat', 'lat' => -6.9175, 'lng' => 107.6191],
            ['name' => 'Cianjur', 'province' => 'Jawa Barat', 'lat' => -6.8222, 'lng' => 107.1394],
            ['name' => 'Sukabumi', 'province' => 'Jawa Barat', 'lat' => -6.9277, 'lng' => 106.9300],
            ['name' => 'Bogor', 'province' => 'Jawa Barat', 'lat' => -6.5971, 'lng' => 106.8060],
            ['name' => 'Kuningan', 'province' => 'Jawa Barat', 'lat' => -6.9765, 'lng' => 108.4839],
            ['name' => 'Subang', 'province' => 'Jawa Barat', 'lat' => -6.5716, 'lng' => 107.7587],
            ['name' => 'Indramayu', 'province' => 'Jawa Barat', 'lat' => -6.3276, 'lng' => 108.3200],
            ['name' => 'Tasikmalaya', 'province' => 'Jawa Barat', 'lat' => -7.3274, 'lng' => 108.2207],
            ['name' => 'Majalengka', 'province' => 'Jawa Barat', 'lat' => -6.8361, 'lng' => 108.2275],
            ['name' => 'Ciamis', 'province' => 'Jawa Barat', 'lat' => -7.3256, 'lng' => 108.3533],

            // Jawa Tengah & DIY
            ['name' => 'Brebes', 'province' => 'Jawa Tengah', 'lat' => -6.8703, 'lng' => 109.0435],
            ['name' => 'Wonosobo (Dieng)', 'province' => 'Jawa Tengah', 'lat' => -7.3633, 'lng' => 109.9000],
            ['name' => 'Magelang', 'province' => 'Jawa Tengah', 'lat' => -7.4797, 'lng' => 110.2177],
            ['name' => 'Boyolali', 'province' => 'Jawa Tengah', 'lat' => -7.5361, 'lng' => 110.5947],
            ['name' => 'Temanggung', 'province' => 'Jawa Tengah', 'lat' => -7.3167, 'lng' => 110.1667],
            ['name' => 'Banyumas', 'province' => 'Jawa Tengah', 'lat' => -7.5144, 'lng' => 109.2942],
            ['name' => 'Klaten', 'province' => 'Jawa Tengah', 'lat' => -7.7058, 'lng' => 110.6014],
            ['name' => 'Semarang', 'province' => 'Jawa Tengah', 'lat' => -7.0051, 'lng' => 110.4381],
            ['name' => 'Pati', 'province' => 'Jawa Tengah', 'lat' => -6.7558, 'lng' => 111.0378],
            ['name' => 'Sleman', 'province' => 'DI Yogyakarta', 'lat' => -7.7167, 'lng' => 110.3556],
            ['name' => 'Bantul', 'province' => 'DI Yogyakarta', 'lat' => -7.8897, 'lng' => 110.3289],
            ['name' => 'Kulon Progo', 'province' => 'DI Yogyakarta', 'lat' => -7.7713, 'lng' => 110.1706],

            // Jawa Timur
            ['name' => 'Malang', 'province' => 'Jawa Timur', 'lat' => -7.9666, 'lng' => 112.6326],
            ['name' => 'Batu', 'province' => 'Jawa Timur', 'lat' => -7.8712, 'lng' => 112.5270],
            ['name' => 'Banyuwangi', 'province' => 'Jawa Timur', 'lat' => -8.2192, 'lng' => 114.3692],
            ['name' => 'Kediri', 'province' => 'Jawa Timur', 'lat' => -7.8480, 'lng' => 112.0178],
            ['name' => 'Probolinggo', 'province' => 'Jawa Timur', 'lat' => -7.7543, 'lng' => 113.2159],
            ['name' => 'Jember', 'province' => 'Jawa Timur', 'lat' => -8.1845, 'lng' => 113.6681],
            ['name' => 'Blitar', 'province' => 'Jawa Timur', 'lat' => -8.0983, 'lng' => 112.1681],
            ['name' => 'Pasuruan', 'province' => 'Jawa Timur', 'lat' => -7.6453, 'lng' => 112.9075],
            ['name' => 'Surabaya', 'province' => 'Jawa Timur', 'lat' => -7.2575, 'lng' => 112.7521],
            ['name' => 'Nganjuk', 'province' => 'Jawa Timur', 'lat' => -7.6053, 'lng' => 111.9036],

            // DKI & Banten
            ['name' => 'Jakarta', 'province' => 'DKI Jakarta', 'lat' => -6.2088, 'lng' => 106.8456],
            ['name' => 'Lebak', 'province' => 'Banten', 'lat' => -6.5591, 'lng' => 106.2505],
            ['name' => 'Pandeglang', 'province' => 'Banten', 'lat' => -6.3087, 'lng' => 106.1065],
            ['name' => 'Serang', 'province' => 'Banten', 'lat' => -6.1104, 'lng' => 106.1640],

            // Sumatera
            ['name' => 'Karo (Berastagi)', 'province' => 'Sumatera Utara', 'lat' => 3.1873, 'lng' => 98.5088],
            ['name' => 'Simalungun', 'province' => 'Sumatera Utara', 'lat' => 2.9667, 'lng' => 99.0667],
            ['name' => 'Deli Serdang', 'province' => 'Sumatera Utara', 'lat' => 3.5186, 'lng' => 98.7107],
            ['name' => 'Medan', 'province' => 'Sumatera Utara', 'lat' => 3.5952, 'lng' => 98.6722],
            ['name' => 'Agam (Bukittinggi)', 'province' => 'Sumatera Barat', 'lat' => -0.2520, 'lng' => 100.1667],
            ['name' => 'Tanah Datar', 'province' => 'Sumatera Barat', 'lat' => -0.4578, 'lng' => 100.5847],
            ['name' => 'Solok (Alahan Panjang)', 'province' => 'Sumatera Barat', 'lat' => -0.9833, 'lng' => 100.7500],
            ['name' => 'Padang', 'province' => 'Sumatera Barat', 'lat' => -0.9471, 'lng' => 100.4172],
            ['name' => 'Aceh Tengah (Gayo)', 'province' => 'Aceh', 'lat' => 4.5424, 'lng' => 96.8465],
            ['name' => 'Bener Meriah', 'province' => 'Aceh', 'lat' => 4.7333, 'lng' => 96.8833],
            ['name' => 'Kerinci', 'province' => 'Jambi', 'lat' => -2.0583, 'lng' => 101.5583],
            ['name' => 'Pagar Alam', 'province' => 'Sumatera Selatan', 'lat' => -4.0278, 'lng' => 103.2667],
            ['name' => 'Palembang', 'province' => 'Sumatera Selatan', 'lat' => -2.9761, 'lng' => 104.7754],
            ['name' => 'Lampung Barat', 'province' => 'Lampung', 'lat' => -5.1436, 'lng' => 104.1925],
            ['name' => 'Bandar Lampung', 'province' => 'Lampung', 'lat' => -5.4292, 'lng' => 105.2625],

            // Bali & Nusa Tenggara
            ['name' => 'Tabanan', 'province' => 'Bali', 'lat' => -8.5414, 'lng' => 115.1247],
            ['name' => 'Bangli (Kintamani)', 'province' => 'Bali', 'lat' => -8.2436, 'lng' => 115.3347],
            ['name' => 'Buleleng (Bedugul)', 'province' => 'Bali', 'lat' => -8.2753, 'lng' => 115.1633],
            ['name' => 'Denpasar', 'province' => 'Bali', 'lat' => -8.6705, 'lng' => 115.2126],
            ['name' => 'Lombok Timur (Sembalun)', 'province' => 'Nusa Tenggara Barat', 'lat' => -8.3583, 'lng' => 116.5333],
            ['name' => 'Bima', 'province' => 'Nusa Tenggara Barat', 'lat' => -8.4536, 'lng' => 118.7275],
            ['name' => 'Manggarai (Ruteng)', 'province' => 'Nusa Tenggara Timur', 'lat' => -8.6136, 'lng' => 120.4686],
            ['name' => 'Kupang', 'province' => 'Nusa Tenggara Timur', 'lat' => -10.1772, 'lng' => 123.6070],

            // Sulawesi
            ['name' => 'Enrekang', 'province' => 'Sulawesi Selatan', 'lat' => -3.5647, 'lng' => 119.7744],
            ['name' => 'Gowa (Malino)', 'province' => 'Sulawesi Selatan', 'lat' => -5.2500, 'lng' => 119.8500],
            ['name' => 'Tana Toraja', 'province' => 'Sulawesi Selatan', 'lat' => -3.0906, 'lng' => 119.8667],
            ['name' => 'Makassar', 'province' => 'Sulawesi Selatan', 'lat' => -5.1477, 'lng' => 119.4327],
            ['name' => 'Tomohon', 'province' => 'Sulawesi Utara', 'lat' => 1.3283, 'lng' => 124.8419],
            ['name' => 'Minahasa', 'province' => 'Sulawesi Utara', 'lat' => 1.2500, 'lng' => 124.9167],
            ['name' => 'Sigi', 'province' => 'Sulawesi Tengah', 'lat' => -1.3850, 'lng' => 119.9639],

            // Kalimantan
            ['name' => 'Pontianak', 'province' => 'Kalimantan Barat', 'lat' => -0.0263, 'lng' => 109.3425],
            ['name' => 'Banjarbaru', 'province' => 'Kalimantan Selatan', 'lat' => -3.4406, 'lng' => 114.8306],
            ['name' => 'Tanah Laut', 'province' => 'Kalimantan Selatan', 'lat' => -3.7942, 'lng' => 114.7739],
            ['name' => 'Kutai Kartanegara', 'province' => 'Kalimantan Timur', 'lat' => -0.4408, 'lng' => 116.9897],
            ['name' => 'Samarinda', 'province' => 'Kalimantan Timur', 'lat' => -0.5022, 'lng' => 117.1536],

            // Maluku & Papua
            ['name' => 'Ambon', 'province' => 'Maluku', 'lat' => -3.6547, 'lng' => 128.1906],
            ['name' => 'Jayapura', 'province' => 'Papua', 'lat' => -2.5916, 'lng' => 140.6690],
            ['name' => 'Jayawijaya (Wamena)', 'province' => 'Papua Pegunungan', 'lat' => -4.0983, 'lng' => 138.9439],
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
