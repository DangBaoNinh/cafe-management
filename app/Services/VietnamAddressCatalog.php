<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

/**
 * @phpstan-type District array{name: string, wards: array<int|string, string>}
 * @phpstan-type Province array{name: string, districts: array<int|string, District>}
 */
class VietnamAddressCatalog
{
    /** @var array<int|string, Province> */
    private array $provinces;

    public function __construct()
    {
        /** @var array<int|string, Province> $provinces */
        $provinces = File::json(resource_path('data/vietnam-legacy-addresses.json'), JSON_THROW_ON_ERROR);
        $this->provinces = $provinces;
    }

    /** @return array<int|string, string> */
    public function provinces(): array
    {
        return array_map(fn (array $province): string => $province['name'], $this->provinces);
    }

    /** @return array<int|string, string> */
    public function districts(string $province): array
    {
        return array_map(
            fn (array $district): string => $district['name'],
            $this->provinces[$province]['districts'] ?? [],
        );
    }

    /** @return array<int|string, string> */
    public function wards(string $province, string $district): array
    {
        return $this->provinces[$province]['districts'][$district]['wards'] ?? [];
    }
}
