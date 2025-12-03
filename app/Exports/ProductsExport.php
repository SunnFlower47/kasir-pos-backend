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
            // Return empty collection for template
            return collect([]);
        }

        $query = Product::with(['category', 'unit']);

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
        return [
            $product->name ?? '',
            $product->sku ?? '',
            $product->barcode ?? '',
            $product->category->name ?? '',
            $product->unit->name ?? '',
            number_format($product->purchase_price ?? 0, 2, '.', ''),
            number_format($product->selling_price ?? 0, 2, '.', ''),
            number_format($product->wholesale_price ?? 0, 2, '.', ''),
            $product->min_stock ?? 0,
            $product->description ?? '',
            $product->is_active ? 'Aktif' : 'Tidak Aktif',
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

