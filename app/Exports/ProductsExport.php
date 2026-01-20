<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ProductsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected $params;
    protected $isTemplate;

    public function __construct(array $params = [])
    {
        $this->params = $params;
        $this->isTemplate = isset($params['template']) && $params['template'] === true;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        if ($this->isTemplate) {
            // Return sample data for template
            return collect([
                [
                    'name' => 'Contoh Produk',
                    'sku' => 'PROD001',
                    'barcode' => '123456789',
                    'category' => 'Makanan', // Just example string, assuming import handles name lookup or requires ID
                    'unit' => 'Pcs',
                    'purchase_price' => 10000,
                    'selling_price' => 15000,
                    'wholesale_price' => 14000,
                    'min_stock' => 10,
                    'description' => 'Contoh deskripsi produk',
                    'is_active' => 'Aktif',
                ]
            ]);
        }

        $query = Product::with(['category', 'unit']);

        // Filter by tenant
        if (isset($this->params['tenant_id'])) {
            $query->where('tenant_id', $this->params['tenant_id']);
        }

        // Apply filters from params
        if (isset($this->params['outlet_id'])) {
            // Note: Products are not outlet-specific, but stock is
            // We can include stock info if needed in mapping
        }

        if (isset($this->params['category_id'])) {
            $query->where('category_id', $this->params['category_id']);
        }

        if (isset($this->params['is_active'])) {
            $query->where('is_active', $this->params['is_active']);
        }

        if (isset($this->params['search'])) {
            $search = $this->params['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('name')->get();
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Nama Produk',
            'SKU',
            'Barcode',
            'Kategori',
            'Satuan',
            'Harga Beli',
            'Harga Jual',
            'Harga Grosir',
            'Min Stok',
            'Deskripsi',
            'Status Aktif',
        ];
    }

    /**
     * @param Product $product
     * @return array
     */
    public function map($product): array
    {
        // Use data_get for robust access to both Array and Object properties
        $name = data_get($product, 'name', '');
        $sku = data_get($product, 'sku', '');
        $barcode = data_get($product, 'barcode', '');
        
        // Handle relationships safely
        $category = '';
        if (is_object($product) && isset($product->category)) {
             $category = $product->category->name ?? '';
        } else {
             $category = data_get($product, 'category', ''); 
        }

        $unit = '';
        if (is_object($product) && isset($product->unit)) {
             $unit = $product->unit->name ?? '';
        } else {
             $unit = data_get($product, 'unit', '');
        }
        
        $purchasePrice = data_get($product, 'purchase_price', 0);
        $sellingPrice = data_get($product, 'selling_price', 0);
        $wholesalePrice = data_get($product, 'wholesale_price', 0);
        $minStock = data_get($product, 'min_stock', 0);
        $description = data_get($product, 'description', '');
        
        $isActive = data_get($product, 'is_active');
        // If boolean (Model), convert. If string (Template), keep/default.
        $status = ($isActive === true || $isActive === 1) ? 'Aktif' : 
                 (($isActive === false || $isActive === 0) ? 'Tidak Aktif' : ($isActive ?: 'Aktif'));

        return [
            $name,
            $sku,
            $barcode,
            $category,
            $unit,
            number_format((float)$purchasePrice, 2, '.', ''),
            number_format((float)$sellingPrice, 2, '.', ''),
            number_format((float)$wholesalePrice, 2, '.', ''),
            $minStock,
            $description,
            $status,
        ];
    }

    /**
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E3F2FD']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return $this->isTemplate ? 'Template Import Produk' : 'Daftar Produk';
    }
}

