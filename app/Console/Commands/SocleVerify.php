<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\ArchitectureChecker;
use Illuminate\Console\Command;

final class SocleVerify extends Command
{
    protected $signature = 'socle:verifier';

    protected $description = 'Verify architecture invariants (C1, C4, C5)';

    public function handle(): int
    {
        $checker = new ArchitectureChecker(base_path());

        $checks = [
            'C1 — No business terms in Platform'   => $checker->checkBusinessTermsInPlatform(),
            'C4 — No float for amounts'             => $checker->checkNoFloatForAmounts(),
            'C5 — All migrations have down()'       => $checker->checkMigrationsHaveDown(),
            'C6 — discount_amount is bigInteger'    => $checker->checkDiscountAmountIsInteger(),
        ];

        foreach ($checks as $label => $violations) {
            if ($violations === []) {
                $this->line(" ✓ {$label}");
            } else {
                $this->line(" ✗ {$label}");
                foreach ($violations as $violation) {
                    $this->line("   {$violation}");
                }

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
