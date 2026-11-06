<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Peer\CMS\Models\Category;
use Peer\Ecommerce\Models\Product;
use Peer\Ecommerce\Services\Catalog\ProductCatalogService;
use Peer\Ecommerce\Services\Catalog\ProductSpreadsheetService;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class ProductSpreadsheetCategoryTest extends TestCase
{
    use RefreshDatabase;

    private Category $menIndoWesterns;

    private Category $boysIndoWesterns;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $men = Category::create(['name' => 'Men']);
        $boys = Category::create(['name' => 'Boys']);
        $this->menIndoWesterns = Category::create(['name' => 'Indo-Westerns', 'parent_id' => $men->id]);
        $this->boysIndoWesterns = Category::create(['name' => 'Indo-Westerns', 'parent_id' => $boys->id]);

        $this->product = app(ProductCatalogService::class)->save(new Product, [
            'category_ids' => [$this->menIndoWesterns->id],
            'name' => 'Gota Patti Indo-Western',
            'slug' => 'gota-patti-indo-western',
            'sku' => 'RE-0047',
            'price' => 9999,
            'stock' => 5,
            'is_active' => true,
        ]);
    }

    public function test_export_names_a_subcategory_with_its_parent(): void
    {
        $sheet = $this->exportedSheet();

        $this->assertSame('Men › Indo-Westerns', $this->categoryCell($sheet)->getValue());
    }

    public function test_import_moves_a_product_to_the_subcategory_named_by_its_path(): void
    {
        $result = $this->importWithCategory('Boys > Indo-Westerns');

        $this->assertSame([], $result['errors']);
        $this->assertSame([$this->boysIndoWesterns->id], $this->product->fresh()->categories->modelKeys());
    }

    public function test_import_rejects_a_subcategory_name_shared_by_several_parents(): void
    {
        $result = $this->importWithCategory('Indo-Westerns');

        $this->assertNotSame([], $result['errors']);
        $this->assertStringContainsString('matches more than one category', implode(' ', array_map('json_encode', $result['errors'])));
        $this->assertSame([$this->menIndoWesterns->id], $this->product->fresh()->categories->modelKeys());
    }

    public function test_import_accepts_a_category_slug(): void
    {
        $result = $this->importWithCategory($this->boysIndoWesterns->slug);

        $this->assertSame([], $result['errors']);
        $this->assertSame([$this->boysIndoWesterns->id], $this->product->fresh()->categories->modelKeys());
    }

    public function test_an_export_can_be_imported_again_without_moving_any_product(): void
    {
        $result = $this->importWithCategory('Men › Indo-Westerns');

        $this->assertSame([], $result['errors']);
        $this->assertSame([$this->menIndoWesterns->id], $this->product->fresh()->categories->modelKeys());
    }

    private function exportedSheet(): Spreadsheet
    {
        return app(ProductSpreadsheetService::class)->export();
    }

    /**
     * @return array{errors: array<int, mixed>}
     */
    private function importWithCategory(string $category): array
    {
        $spreadsheet = $this->exportedSheet();
        $this->categoryCell($spreadsheet)->setValue($category);

        $path = tempnam(sys_get_temp_dir(), 'products').'.xlsx';
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

        try {
            return app(ProductSpreadsheetService::class)->import(new UploadedFile($path, 'products.xlsx', null, null, true));
        } finally {
            @unlink($path);
        }
    }

    private function categoryCell(Spreadsheet $spreadsheet): Cell
    {
        $sheet = $spreadsheet->getActiveSheet();
        $headers = $sheet->rangeToArray('A1:'.$sheet->getHighestColumn().'1')[0];
        $column = array_search('Category', $headers, true) + 1;

        return $sheet->getCell([$column, 2]);
    }
}
