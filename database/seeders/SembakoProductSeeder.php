<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SembakoProductSeeder extends Seeder
{
    /**
     * Seed sembako products (100+ items)
     */
    public function run(): void
    {
        // Get or create Sembako category
        $category = Category::firstOrCreate(
            ['name' => 'Sembako'],
            ['description' => 'Sembako dan kebutuhan pokok']
        );

        // Get unit (use first available unit or create default)
        $unit = Unit::first();
        if (!$unit) {
            $unit = Unit::firstOrCreate(
                ['name' => 'Pcs'],
                ['description' => 'Piece']
            );
        }

        $outlets = Outlet::all();
        if ($outlets->isEmpty()) {
            $this->command?->warn('SembakoProductSeeder: No outlets found. Creating products without stock.');
        }

        // Sembako products data
        $sembakoProducts = [
            // Beras
            ['Beras Premium 5kg', 65000, 75000, 70000, '8991234567001', 'BRSP-5KG'],
            ['Beras Medium 5kg', 55000, 65000, 60000, '8991234567002', 'BRSM-5KG'],
            ['Beras Premium 10kg', 120000, 140000, 130000, '8991234567003', 'BRSP-10KG'],
            ['Beras Medium 10kg', 100000, 120000, 110000, '8991234567004', 'BRSM-10KG'],
            ['Beras Premium 25kg', 280000, 320000, 300000, '8991234567005', 'BRSP-25KG'],
            ['Beras Medium 25kg', 240000, 280000, 260000, '8991234567006', 'BRSM-25KG'],

            // Minyak Goreng
            ['Minyak Goreng 1L', 18000, 22000, 20000, '8991234567010', 'MYK-1L'],
            ['Minyak Goreng 2L', 34000, 40000, 37000, '8991234567011', 'MYK-2L'],
            ['Minyak Goreng 5L', 80000, 95000, 87000, '8991234567012', 'MYK-5L'],
            ['Minyak Goreng Premium 1L', 22000, 28000, 25000, '8991234567013', 'MYKP-1L'],
            ['Minyak Goreng Premium 2L', 42000, 50000, 46000, '8991234567014', 'MYKP-2L'],

            // Gula
            ['Gula Pasir 1kg', 15000, 18000, 16500, '8991234567020', 'GUL-1KG'],
            ['Gula Pasir 500gr', 8000, 10000, 9000, '8991234567021', 'GUL-500G'],
            ['Gula Pasir 2kg', 28000, 34000, 31000, '8991234567022', 'GUL-2KG'],
            ['Gula Merah 500gr', 12000, 15000, 13500, '8991234567023', 'GULM-500G'],
            ['Gula Merah 1kg', 22000, 28000, 25000, '8991234567024', 'GULM-1KG'],

            // Garam
            ['Garam Halus 500gr', 5000, 7000, 6000, '8991234567030', 'GRM-500G'],
            ['Garam Halus 1kg', 9000, 12000, 10500, '8991234567031', 'GRM-1KG'],
            ['Garam Kasar 1kg', 8000, 11000, 9500, '8991234567032', 'GRMK-1KG'],

            // Tepung
            ['Tepung Terigu 1kg', 12000, 15000, 13500, '8991234567040', 'TPG-1KG'],
            ['Tepung Terigu 500gr', 6500, 8000, 7250, '8991234567041', 'TPG-500G'],
            ['Tepung Beras 500gr', 8000, 10000, 9000, '8991234567042', 'TPGB-500G'],
            ['Tepung Tapioka 500gr', 7000, 9000, 8000, '8991234567043', 'TPGT-500G'],
            ['Tepung Maizena 200gr', 6000, 8000, 7000, '8991234567044', 'TPGM-200G'],

            // Mie Instan
            ['Mie Instan Rasa Ayam Bawang', 3000, 4000, 3500, '8991234567050', 'MIE-AYB'],
            ['Mie Instan Rasa Soto', 3000, 4000, 3500, '8991234567051', 'MIE-SOTO'],
            ['Mie Instan Rasa Kari Ayam', 3000, 4000, 3500, '8991234567052', 'MIE-KARI'],
            ['Mie Instan Rasa Rendang', 3500, 4500, 4000, '8991234567053', 'MIE-RDG'],
            ['Mie Instan Rasa Baso', 3000, 4000, 3500, '8991234567054', 'MIE-BASO'],
            ['Mie Instan Cup Rasa Ayam', 5000, 6500, 5750, '8991234567055', 'MIEC-AYB'],
            ['Mie Instan Cup Rasa Soto', 5000, 6500, 5750, '8991234567056', 'MIEC-SOTO'],

            // Telur
            ['Telur Ayam Ras 1kg', 28000, 35000, 31500, '8991234567060', 'TLR-1KG'],
            ['Telur Ayam Kampung 1kg', 35000, 45000, 40000, '8991234567061', 'TLRK-1KG'],
            ['Telur Bebek 1kg', 40000, 50000, 45000, '8991234567062', 'TLRB-1KG'],

            // Susu
            ['Susu UHT 1L', 12000, 15000, 13500, '8991234567070', 'SUSU-1L'],
            ['Susu UHT 200ml', 3000, 4000, 3500, '8991234567071', 'SUSU-200ML'],
            ['Susu Kental Manis 370gr', 12000, 15000, 13500, '8991234567072', 'SUSUK-370G'],
            ['Susu Bubuk 400gr', 25000, 32000, 28500, '8991234567073', 'SUSUB-400G'],
            ['Susu Bubuk 1kg', 55000, 70000, 62500, '8991234567074', 'SUSUB-1KG'],

            // Bumbu Dapur
            ['Bawang Merah 1kg', 30000, 40000, 35000, '8991234567080', 'BWM-1KG'],
            ['Bawang Putih 1kg', 25000, 35000, 30000, '8991234567081', 'BWP-1KG'],
            ['Bawang Merah 500gr', 16000, 21000, 18500, '8991234567082', 'BWM-500G'],
            ['Bawang Putih 500gr', 13000, 18000, 15500, '8991234567083', 'BWP-500G'],
            ['Cabe Merah 1kg', 35000, 50000, 42500, '8991234567084', 'CABM-1KG'],
            ['Cabe Rawit 1kg', 40000, 55000, 47500, '8991234567085', 'CABR-1KG'],
            ['Cabe Merah 500gr', 18000, 26000, 22000, '8991234567086', 'CABM-500G'],
            ['Cabe Rawit 500gr', 21000, 28000, 24500, '8991234567087', 'CABR-500G'],
            ['Kunyit 500gr', 15000, 20000, 17500, '8991234567088', 'KNY-500G'],
            ['Jahe 500gr', 12000, 18000, 15000, '8991234567089', 'JHE-500G'],
            ['Lengkuas 500gr', 10000, 15000, 12500, '8991234567090', 'LKG-500G'],
            ['Kencur 500gr', 15000, 20000, 17500, '8991234567091', 'KNC-500G'],

            // Kecap & Saus
            ['Kecap Manis 275ml', 8000, 11000, 9500, '8991234567100', 'KCP-275ML'],
            ['Kecap Manis 620ml', 15000, 20000, 17500, '8991234567101', 'KCP-620ML'],
            ['Kecap Asin 275ml', 7000, 10000, 8500, '8991234567102', 'KCPA-275ML'],
            ['Saus Tomat 275ml', 8000, 11000, 9500, '8991234567103', 'SAUS-275ML'],
            ['Saus Sambal 275ml', 8000, 11000, 9500, '8991234567104', 'SAUSS-275ML'],
            ['Saus Tiram 135ml', 12000, 16000, 14000, '8991234567105', 'SAUST-135ML'],

            // Bumbu Instan
            ['Bumbu Rendang Instan', 5000, 7000, 6000, '8991234567110', 'BMB-RDG'],
            ['Bumbu Rawon Instan', 5000, 7000, 6000, '8991234567111', 'BMB-RWN'],
            ['Bumbu Soto Instan', 5000, 7000, 6000, '8991234567112', 'BMB-SOTO'],
            ['Bumbu Gado-gado Instan', 5000, 7000, 6000, '8991234567113', 'BMB-GDG'],
            ['Bumbu Opor Instan', 5000, 7000, 6000, '8991234567114', 'BMB-OPR'],

            // Santan
            ['Santan Instan 65ml', 2000, 3000, 2500, '8991234567120', 'SNT-65ML'],
            ['Santan Instan 200ml', 5000, 7000, 6000, '8991234567121', 'SNT-200ML'],
            ['Santan Instan 400ml', 9000, 12000, 10500, '8991234567122', 'SNT-400ML'],

            // Minuman
            ['Kopi Instan 200gr', 15000, 20000, 17500, '8991234567130', 'KOPI-200G'],
            ['Kopi Instan 100gr', 8000, 11000, 9500, '8991234567131', 'KOPI-100G'],
            ['Teh Celup 25s', 8000, 12000, 10000, '8991234567132', 'TEH-25S'],
            ['Teh Celup 50s', 15000, 22000, 18500, '8991234567133', 'TEH-50S'],
            ['Sirup Jeruk 500ml', 12000, 18000, 15000, '8991234567134', 'SRP-JRK'],
            ['Sirup Marjan 500ml', 12000, 18000, 15000, '8991234567135', 'SRP-MRJ'],

            // Snack & Cemilan
            ['Kerupuk Udang 200gr', 8000, 12000, 10000, '8991234567140', 'KRP-200G'],
            ['Kerupuk Putih 200gr', 6000, 9000, 7500, '8991234567141', 'KRPP-200G'],
            ['Kerupuk Bawang 200gr', 7000, 10000, 8500, '8991234567142', 'KRPB-200G'],
            ['Emping Melinjo 200gr', 10000, 15000, 12500, '8991234567143', 'EMP-200G'],
            ['Kacang Tanah 500gr', 15000, 20000, 17500, '8991234567144', 'KCG-500G'],
            ['Kacang Hijau 500gr', 18000, 25000, 21500, '8991234567145', 'KCGH-500G'],

            // Sarden & Kaleng
            ['Sarden Kaleng 155gr', 8000, 12000, 10000, '8991234567150', 'SRD-155G'],
            ['Sarden Kaleng 425gr', 18000, 25000, 21500, '8991234567151', 'SRD-425G'],
            ['Kornet Kaleng 198gr', 12000, 18000, 15000, '8991234567152', 'KRN-198G'],

            // Minyak & Margarin
            ['Margarin 250gr', 12000, 16000, 14000, '8991234567160', 'MRG-250G'],
            ['Margarin 500gr', 22000, 30000, 26000, '8991234567161', 'MRG-500G'],
            ['Mentega 200gr', 15000, 20000, 17500, '8991234567162', 'MTG-200G'],

            // Lain-lain
            ['Mie Kering 500gr', 8000, 12000, 10000, '8991234567170', 'MIEK-500G'],
            ['Bihun 200gr', 5000, 8000, 6500, '8991234567171', 'BHN-200G'],
            ['Soun 200gr', 5000, 8000, 6500, '8991234567172', 'SOUN-200G'],
            ['Tahu 10pcs', 8000, 12000, 10000, '8991234567173', 'TAHU-10PC'],
            ['Tempe 5pcs', 5000, 8000, 6500, '8991234567174', 'TMP-5PC'],
            ['Oncom 5pcs', 6000, 9000, 7500, '8991234567175', 'ONC-5PC'],
            ['Tape Ketan 500gr', 8000, 12000, 10000, '8991234567176', 'TPE-500G'],
            ['Tape Singkong 500gr', 7000, 10000, 8500, '8991234567177', 'TPES-500G'],

            // Tambahan untuk mencapai 100+
            ['Beras Ketan 1kg', 18000, 25000, 21500, '8991234567180', 'BRKT-1KG'],
            ['Beras Merah 1kg', 20000, 28000, 24000, '8991234567181', 'BRMR-1KG'],
            ['Minyak Kelapa 500ml', 15000, 20000, 17500, '8991234567182', 'MYKK-500ML'],
            ['Gula Aren 500gr', 10000, 15000, 12500, '8991234567183', 'GULR-500G'],
            ['Garam Iodized 1kg', 10000, 14000, 12000, '8991234567184', 'GRMI-1KG'],
            ['Tepung Sagu 500gr', 12000, 18000, 15000, '8991234567185', 'TPGS-500G'],
            ['Mie Instan Rasa Baso Sapi', 3500, 4500, 4000, '8991234567186', 'MIE-BSS'],
            ['Mie Instan Rasa Ayam Spesial', 3500, 4500, 4000, '8991234567187', 'MIE-ASP'],
            ['Telur Puyuh 1kg', 25000, 35000, 30000, '8991234567188', 'TLRP-1KG'],
            ['Susu UHT Coklat 1L', 13000, 16000, 14500, '8991234567189', 'SUSUC-1L'],
            ['Susu UHT Stroberi 1L', 13000, 16000, 14500, '8991234567190', 'SUSUS-1L'],
            ['Bawang Bombay 1kg', 20000, 30000, 25000, '8991234567191', 'BWGB-1KG'],
            ['Cabe Hijau 1kg', 30000, 45000, 37500, '8991234567192', 'CABH-1KG'],
            ['Kemiri 500gr', 18000, 25000, 21500, '8991234567193', 'KMR-500G'],
            ['Ketumbar 200gr', 8000, 12000, 10000, '8991234567194', 'KTB-200G'],
            ['Merica 200gr', 10000, 15000, 12500, '8991234567195', 'MRC-200G'],
            ['Kecap Manis Premium 275ml', 10000, 14000, 12000, '8991234567196', 'KCPP-275ML'],
            ['Saus Tomat Premium 275ml', 10000, 14000, 12000, '8991234567197', 'SAUSP-275ML'],
            ['Bumbu Nasi Goreng Instan', 5000, 7000, 6000, '8991234567198', 'BMB-NGR'],
            ['Bumbu Ayam Goreng Instan', 5000, 7000, 6000, '8991234567199', 'BMB-AGR'],
        ];

        $this->command?->info('Creating ' . count($sembakoProducts) . ' Sembako products...');

        $created = 0;
        $updated = 0;

        foreach ($sembakoProducts as $index => $productData) {
            [$name, $purchasePrice, $sellingPrice, $wholesalePrice, $barcode, $sku] = $productData;

            // Generate description
            $description = "Produk sembako {$name} - kebutuhan pokok sehari-hari";

            // Calculate min_stock (random between 5-20)
            $minStock = rand(5, 20);

            $product = Product::updateOrCreate(
                ['sku' => $sku],
                [
                    'name' => $name,
                    'barcode' => $barcode,
                    'description' => $description,
                    'category_id' => $category->id,
                    'unit_id' => $unit->id,
                    'purchase_price' => $purchasePrice,
                    'selling_price' => $sellingPrice,
                    'wholesale_price' => $wholesalePrice,
                    'min_stock' => $minStock,
                    'is_active' => true,
                ]
            );

            if ($product->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }

            // Create stock for all outlets
            if (!$outlets->isEmpty()) {
                foreach ($outlets as $outletIndex => $outlet) {
                    // Random stock quantity between 20-100
                    $quantity = rand(20, 100) + ($outletIndex * 10);

                    ProductStock::updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'outlet_id' => $outlet->id,
                        ],
                        [
                            'quantity' => $quantity,
                        ]
                    );
                }
            }
        }

        $this->command?->info("✅ Created: {$created} products");
        $this->command?->info("✅ Updated: {$updated} products");
        $this->command?->info("✅ Total: " . count($sembakoProducts) . " Sembako products seeded!");
    }
}

