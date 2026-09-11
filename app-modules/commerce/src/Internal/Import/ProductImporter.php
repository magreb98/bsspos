<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Import;

use Illuminate\Support\Facades\DB;
use Modules\Commerce\Internal\Enums\Granularity;
use Modules\Commerce\Internal\Models\Family;
use Ramsey\Uuid\Uuid;

final class ProductImporter
{
    private const int BATCH_SIZE = 100;

    public function import(string $csvPath): ImportReport
    {
        $handle = fopen($csvPath, 'r');

        if ($handle === false) {
            return new ImportReport(0, 0, [
                new ImportError(0, '', 'Cannot open file'),
            ]);
        }

        $rawHeaders = fgetcsv($handle);
        $headers    = array_map(fn ($v) => trim((string) $v), $rawHeaders !== false ? $rawHeaders : []);
        $errors   = [];
        $batch    = [];
        $imported = 0;
        $seen     = [];
        $lineNum  = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNum++;
            $data = array_combine($headers, array_map(fn ($v) => trim((string) $v), $row));

            $error = $this->validate($data, $lineNum, $seen);

            if ($error !== null) {
                $errors[] = $error;
                continue;
            }

            $reference        = $data['reference'];
            $seen[$reference] = true;
            $familyId         = $this->findOrCreateFamily($data['family_name']);
            $vatRate          = isset($data['vat_rate']) && $data['vat_rate'] !== ''
                ? (string) $data['vat_rate']
                : '19.25';

            $batch[] = [
                'id'            => Uuid::uuid7()->toString(),
                'reference'     => $reference,
                'label'         => $data['label'],
                'family_id'     => $familyId,
                'selling_price' => (int) $data['selling_price'],
                'vat_rate'      => $vatRate,
                'granularity'   => $data['granularity'],
                'active'        => 1,
                'created_at'    => now(),
                'updated_at'    => now(),
            ];

            if (count($batch) >= self::BATCH_SIZE) {
                DB::table('products')->insert($batch);
                $imported += count($batch);
                $batch = [];
            }
        }

        fclose($handle);

        if ($batch !== []) {
            DB::table('products')->insert($batch);
            $imported += count($batch);
        }

        return new ImportReport($imported, 0, $errors);
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, bool>    $seen
     */
    private function validate(array $data, int $line, array $seen): ?ImportError
    {
        $ref = $data['reference'] ?? '';

        if ($ref === '') {
            return new ImportError($line, '', 'Reference is required');
        }

        if (($data['label'] ?? '') === '') {
            return new ImportError($line, $ref, 'Label is required');
        }

        if (($data['family_name'] ?? '') === '') {
            return new ImportError($line, $ref, 'Family is required');
        }

        $price = $data['selling_price'] ?? '';
        if (! ctype_digit($price) && ! (is_numeric($price) && (int) $price >= 0)) {
            return new ImportError($line, $ref, 'Selling price must be a non-negative integer');
        }

        $granularity = $data['granularity'] ?? '';
        if (Granularity::tryFrom($granularity) === null) {
            return new ImportError($line, $ref, "Invalid granularity: {$granularity}");
        }

        if (isset($seen[$ref])) {
            return new ImportError($line, $ref, "Duplicate reference: {$ref}");
        }

        if (DB::table('products')->where('reference', $ref)->exists()) {
            return new ImportError($line, $ref, "Duplicate reference: {$ref}");
        }

        return null;
    }

    private function findOrCreateFamily(string $name): string
    {
        $family = Family::firstOrCreate(['name' => $name]);

        return $family->id;
    }
}
