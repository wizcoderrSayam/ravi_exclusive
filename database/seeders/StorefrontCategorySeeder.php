<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\StoresMediaLibraryFiles;
use Illuminate\Database\Seeder;
use Peer\CMS\Models\Category;
use Peer\Ecommerce\Http\Controllers\Api\CategoryController;
use Peer\Ecommerce\Models\CategoryMedia;

class StorefrontCategorySeeder extends Seeder
{
    use StoresMediaLibraryFiles;

    public function run(): void
    {
        foreach ($this->categories() as $parentDefinition) {
            $parent = Category::query()->firstOrCreate(['name' => $parentDefinition['name'], 'parent_id' => null]);
            $this->addPhoto($parent, $parentDefinition['slug'].'.jpg');

            foreach ($parentDefinition['children'] as $child) {
                $category = Category::query()->firstOrCreate(['name' => $child['name'], 'parent_id' => $parent->id]);
                $this->addPhoto($category, $child['slug'].'.webp');
            }
        }
    }

    /**
     * Replaces only a missing photo, never one chosen in the admin.
     */
    private function addPhoto(Category $category, string $file): void
    {
        if (CategoryMedia::query()->where('category_id', $category->id)->exists()) {
            return;
        }

        CategoryMedia::syncCategory($category->id, [
            $this->storeInMediaLibrary(CategoryController::IMAGE_FOLDER, public_path('assets/images/categories/'.$file)),
        ]);
    }

    /**
     * The hierarchy comes from the 17 Oct 2026 database backup; each slug names the category's photo.
     *
     * @return list<array{slug: string, name: string, children: list<array{slug: string, name: string}>}>
     */
    private function categories(): array
    {
        return [
            [
                'slug' => 'girls',
                'name' => 'Girls',
                'children' => [
                    ['slug' => 'girls-lehenga', 'name' => 'Lehenga'],
                    ['slug' => 'girls-sharara-palazzo', 'name' => 'Sharara / Palazzo'],
                    ['slug' => 'girls-patiala-farshi-salwar', 'name' => 'Patiala Salwar Suits / Farshi Salwar'],
                    ['slug' => 'girls-frocks-gowns', 'name' => 'Frocks & Gowns'],
                ],
            ],
            [
                'slug' => 'boys',
                'name' => 'Boys',
                'children' => [
                    ['slug' => 'boys-sherwani', 'name' => 'Sherwani'],
                    ['slug' => 'boys-indo-westerns', 'name' => 'Indo-Westerns'],
                    ['slug' => 'boys-coat-suits-tuxedos', 'name' => 'Coat Suits / Tuxedos'],
                    ['slug' => 'boys-jodhpuri', 'name' => 'Jodhpuri'],
                    ['slug' => 'boys-kurta-pajama', 'name' => 'Kurta Pajama'],
                    ['slug' => 'boys-waistcoat-sets', 'name' => 'Waistcoat Sets'],
                ],
            ],
            [
                'slug' => 'men',
                'name' => 'Men',
                'children' => [
                    ['slug' => 'men-kurta-pajamas', 'name' => 'Kurta Pajamas'],
                    ['slug' => 'men-waistcoat-sets', 'name' => 'Waistcoat Sets'],
                    ['slug' => 'men-indo-westerns', 'name' => 'Indo-Westerns'],
                ],
            ],
            [
                'slug' => 'women',
                'name' => 'Women',
                'children' => [
                    ['slug' => 'women-co-ord-sets', 'name' => 'Co-ord Sets'],
                ],
            ],
        ];
    }
}
