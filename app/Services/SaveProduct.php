<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class SaveProduct
{
    /** @param array<string, mixed> $attributes */
    public function save(Product $product, array $attributes, ?UploadedFile $image): void
    {
        $newPath = $image?->store('products', 'public');

        if ($newPath === false) {
            throw ValidationException::withMessages(['image' => 'Không thể lưu ảnh. Vui lòng thử lại.']);
        }

        try {
            $previousPath = $product->getConnection()->transaction(function () use ($product, $attributes, $newPath): ?string {
                if ($product->exists) {
                    $product = $product->newQuery()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
                }

                $previousPath = $product->image_path;
                $product->fill($attributes);

                if ($newPath !== null) {
                    $product->image_path = $newPath;
                }

                if (! $product->save()) {
                    throw new RuntimeException('Product save was cancelled.');
                }

                return $previousPath;
            });
        } catch (Throwable $exception) {
            if ($newPath !== null) {
                $this->deleteImage($newPath);
            }

            throw $exception;
        }

        if ($newPath !== null && $previousPath !== null) {
            $this->deleteImage($previousPath);
        }
    }

    private function deleteImage(string $path): void
    {
        try {
            if (! Storage::disk('public')->delete($path)) {
                report(new RuntimeException("Could not delete product image: {$path}"));
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
