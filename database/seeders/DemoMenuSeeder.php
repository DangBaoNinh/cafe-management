<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Topping;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoMenuSeeder extends Seeder
{
    private const TOPPINGS = [
        'espresso-shot' => ['Thêm shot espresso', 10000],
        'kem-sua' => ['Kem sữa', 10000],
        'tran-chau-den' => ['Trân châu đen', 8000],
        'tran-chau-trang' => ['Trân châu trắng', 8000],
        'thach-dua' => ['Thạch dừa', 8000],
        'thach-ca-phe' => ['Thạch cà phê', 8000],
        'pudding-trung' => ['Pudding trứng', 10000],
        'dao-mieng' => ['Đào miếng', 12000],
    ];

    // Mỗi món gồm tên, mô tả, giá size M, giá size L và mã topping được chọn.
    private const MENU = [
        'Cà phê Việt Nam' => [
            ['Cà phê đen đá', 'Cà phê phin đậm vị, phục vụ với đá.', 29000, 35000, []],
            ['Cà phê sữa đá', 'Cà phê phin hòa cùng sữa đặc.', 35000, 42000, ['thach-ca-phe']],
            ['Bạc xỉu đá', 'Sữa tươi và sữa đặc với một lớp cà phê phin.', 39000, 46000, ['thach-ca-phe']],
            ['Cà phê muối', 'Cà phê phin với lớp kem muối béo nhẹ.', 45000, 52000, ['thach-ca-phe']],
        ],
        'Cà phê pha máy' => [
            ['Espresso', 'Cà phê chiết xuất đậm, size L là hai shot.', 35000, 45000, []],
            ['Americano nóng', 'Espresso pha cùng nước nóng.', 39000, 46000, ['espresso-shot']],
            ['Latte nóng', 'Espresso với sữa đánh nóng và lớp bọt mỏng.', 49000, 57000, ['espresso-shot']],
            ['Cappuccino nóng', 'Espresso với sữa đánh nóng và lớp bọt dày.', 49000, 57000, ['espresso-shot']],
        ],
        'Trà trái cây' => [
            ['Trà đào cam sả', 'Trà đen với đào, cam và sả thơm.', 45000, 53000, ['dao-mieng', 'thach-dua']],
            ['Trà vải', 'Trà nhài thanh nhẹ với vải.', 45000, 53000, ['thach-dua', 'tran-chau-trang']],
            ['Trà tắc mật ong', 'Trà xanh với nước tắc và mật ong.', 39000, 47000, ['thach-dua']],
            ['Trà dâu hibiscus', 'Trà hoa bụp giấm với dâu, vị chua nhẹ.', 49000, 57000, ['thach-dua', 'tran-chau-trang']],
        ],
        'Trà sữa' => [
            ['Trà sữa truyền thống', 'Trà đen đậm vị pha cùng sữa.', 42000, 50000, ['tran-chau-den', 'pudding-trung', 'thach-ca-phe']],
            ['Trà sữa ô long', 'Trà ô long thơm nhẹ pha cùng sữa.', 47000, 55000, ['tran-chau-den', 'tran-chau-trang', 'kem-sua']],
            ['Trà sữa lài', 'Trà xanh ướp hoa lài pha cùng sữa.', 45000, 53000, ['tran-chau-trang', 'thach-dua', 'pudding-trung']],
            ['Trà sữa khoai môn', 'Trà sữa vị khoai môn béo nhẹ.', 48000, 56000, ['tran-chau-den', 'pudding-trung']],
        ],
        'Matcha và ca cao' => [
            ['Matcha latte đá', 'Matcha đánh mịn với sữa tươi và đá.', 52000, 60000, ['kem-sua', 'tran-chau-trang']],
            ['Matcha dâu sữa', 'Matcha và sữa tươi trên lớp dâu nghiền.', 59000, 67000, ['thach-dua', 'tran-chau-trang']],
            ['Ca cao sữa đá', 'Ca cao đậm vị pha cùng sữa và đá.', 45000, 53000, ['kem-sua', 'pudding-trung']],
            ['Mocha đá', 'Espresso, ca cao và sữa tươi phục vụ với đá.', 55000, 63000, ['espresso-shot', 'kem-sua']],
        ],
        'Sinh tố và nước ép' => [
            ['Sinh tố bơ', 'Bơ xay cùng sữa, vị béo mịn.', 55000, 65000, []],
            ['Sinh tố xoài', 'Xoài chín xay với sữa chua.', 49000, 59000, ['thach-dua']],
            ['Nước ép cam', 'Cam tươi ép, vị chua ngọt tự nhiên.', 45000, 53000, []],
            ['Nước ép ổi', 'Ổi tươi ép, thơm nhẹ.', 42000, 50000, []],
        ],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $toppingIds = [];
            foreach (self::TOPPINGS as $code => [$name, $price]) {
                $toppingIds[$code] = Topping::firstOrCreate(
                    ['code' => $code],
                    ['name' => $name, 'price_vnd' => $price],
                )->id;
            }

            $sortOrder = 0;
            foreach (self::MENU as $categoryName => $drinks) {
                $category = Category::firstOrCreate(
                    ['slug' => Str::slug($categoryName)],
                    ['name' => $categoryName, 'sort_order' => ++$sortOrder],
                );

                foreach ($drinks as [$name, $description, $mediumPrice, $largePrice, $toppingCodes]) {
                    $product = Product::firstOrCreate(
                        ['slug' => Str::slug($name)],
                        [
                            'category_id' => $category->id,
                            'name' => $name,
                            'description' => $description,
                            'allows_sugar' => in_array($categoryName, ['Trà trái cây', 'Trà sữa'], true),
                            'allows_ice' => $categoryName !== 'Cà phê pha máy',
                        ],
                    );

                    if (! $product->wasRecentlyCreated) {
                        continue;
                    }

                    $product->sizes()->createMany([
                        ['code' => 'M', 'label' => 'Vừa (M)', 'price_vnd' => $mediumPrice],
                        ['code' => 'L', 'label' => 'Lớn (L)', 'price_vnd' => $largePrice],
                    ]);
                    $product->toppings()->attach(array_map(fn (string $code) => $toppingIds[$code], $toppingCodes));
                }
            }
        });
    }
}
