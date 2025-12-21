<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\Category;
use App\Models\Unit;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Validators\Failure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductsImport implements ToModel, WithHeadingRow, WithValidation, WithBatchInserts, WithChunkReading, SkipsOnFailure
{
    use SkipsFailures;

    protected $rowCount = 0;
    protected $successCount = 0;
    protected $failedCount = 0;
    protected $errors = [];
    protected $previewMode = false;
    protected $previewData = [];
    protected $validRows = [];
    protected $invalidRows = [];

    public function setPreviewMode(bool $previewMode): void
    {
        $this->previewMode = $previewMode;
    }

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $this->rowCount++;

        try {
            // Normalize column names (handle Indonesian column names)
            $name = $this->getValue($row, ['nama_produk', 'name', 'nama', 'product_name']);
            $sku = $this->getValue($row, ['sku', 'SKU']);
            $barcode = $this->getValue($row, ['barcode', 'Barcode']);
            $categoryName = $this->getValue($row, ['kategori', 'category', 'category_name']);
            $unitName = $this->getValue($row, ['satuan', 'unit', 'unit_name']);
            $purchasePrice = $this->getValue($row, ['harga_beli', 'purchase_price', 'harga_beli']);
            $sellingPrice = $this->getValue($row, ['harga_jual', 'selling_price']);
            $wholesalePrice = $this->getValue($row, ['harga_grosir', 'wholesale_price']);
            $minStock = $this->getValue($row, ['min_stok', 'min_stock', 'minimum_stock']);
            $description = $this->getValue($row, ['deskripsi', 'description', 'desc']);
            $isActive = $this->getValue($row, ['status_aktif', 'is_active', 'status', 'aktif']);

            // Validate required fields
            if (empty($name)) {
                throw new \Exception("Row {$this->rowCount}: Nama produk wajib diisi");
            }

            if (empty($categoryName)) {
                throw new \Exception("Row {$this->rowCount}: Kategori wajib diisi");
            }

            if (empty($unitName)) {
                throw new \Exception("Row {$this->rowCount}: Satuan wajib diisi");
            }

            if (empty($purchasePrice) || !is_numeric($purchasePrice)) {
                throw new \Exception("Row {$this->rowCount}: Harga beli harus berupa angka");
            }

            if (empty($sellingPrice) || !is_numeric($sellingPrice)) {
                throw new \Exception("Row {$this->rowCount}: Harga jual harus berupa angka");
            }

            // Get or create category
            $category = Category::firstOrCreate(
                ['name' => trim($categoryName)],
                ['is_active' => true, 'description' => 'Imported from Excel']
            );

            // Get or create unit
            $unit = Unit::firstOrCreate(
                ['name' => trim($unitName)],
                ['symbol' => Str::upper(substr(trim($unitName), 0, 3)), 'description' => 'Imported from Excel']
            );

            // Generate SKU if not provided
            if (empty($sku)) {
                $sku = 'PRD' . str_pad(Product::count() + 1, 6, '0', STR_PAD_LEFT);
            }

            // Check for duplicate SKU
            if (Product::where('sku', $sku)->exists()) {
                throw new \Exception("Row {$this->rowCount}: SKU '{$sku}' sudah ada");
            }

            // Check for duplicate barcode if provided
            if (!empty($barcode) && Product::where('barcode', $barcode)->exists()) {
                throw new \Exception("Row {$this->rowCount}: Barcode '{$barcode}' sudah ada");
            }

            // Parse active status
            $isActiveValue = true;
            if (!empty($isActive)) {
                $isActiveStr = strtolower(trim($isActive));
                $isActiveValue = in_array($isActiveStr, ['aktif', 'active', '1', 'true', 'yes', 'ya']);
            }

            // Prepare product data
            $productData = [
                'name' => trim($name),
                'sku' => trim($sku),
                'barcode' => !empty($barcode) ? trim($barcode) : null,
                'category_id' => $category->id,
                'unit_id' => $unit->id,
                'purchase_price' => (float) $purchasePrice,
                'selling_price' => (float) $sellingPrice,
                'wholesale_price' => !empty($wholesalePrice) && is_numeric($wholesalePrice) ? (float) $wholesalePrice : 0,
                'min_stock' => !empty($minStock) && is_numeric($minStock) ? (float) $minStock : 0,
                'description' => !empty($description) ? trim($description) : null,
                'is_active' => $isActiveValue,
            ];

            if ($this->previewMode) {
                // In preview mode, just collect data without saving
                $this->previewData[] = $productData;
                $this->validRows[] = $productData;
                return null;
            }

            // Create product
            $product = new Product($productData);
            $this->successCount++;

            return $product;

        } catch (\Exception $e) {
            $this->failedCount++;
            $this->errors[] = $e->getMessage();

            if ($this->previewMode) {
                $this->invalidRows[] = [
                    'row' => $this->rowCount,
                    'error' => $e->getMessage(),
                    'data' => $row
                ];
            }

            // Skip this row
            return null;
        }
    }

    /**
     * Get value from row with multiple possible keys
     */
    protected function getValue(array $row, array $keys)
    {
        foreach ($keys as $key) {
            if (isset($row[$key])) {
                return $row[$key];
            }
            // Try case-insensitive match
            foreach ($row as $rowKey => $value) {
                if (strtolower($rowKey) === strtolower($key)) {
                    return $value;
                }
            }
        }
        return null;
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            // Validation rules are handled in model() method
            // This method is required by WithValidation interface
        ];
    }

    /**
     * @return int
     */
    public function batchSize(): int
    {
        return 100;
    }

    /**
     * @return int
     */
    public function chunkSize(): int
    {
        return 100;
    }

    /**
     * Get row count
     */
    public function getRowCount(): int
    {
        return $this->rowCount;
    }

    /**
     * Get success count
     */
    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    /**
     * Get failed count
     */
    public function getFailedCount(): int
    {
        return $this->failedCount;
    }

    /**
     * Get errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get preview data
     */
    public function getPreviewData(): array
    {
        return $this->previewData;
    }

    /**
     * Get valid rows
     */
    public function getValidRows(): array
    {
        return $this->validRows;
    }

    /**
     * Get invalid rows
     */
    public function getInvalidRows(): array
    {
        return $this->invalidRows;
    }
}

