<?php

declare(strict_types=1);

namespace App\Console;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ArchitectureChecker
{
    private const array BUSINESS_TERMS = ['produit', 'vente', 'salaire', 'vehicule'];

    private const string FLOAT_PATTERN = '/(:\s*float\b|\bfloat\s+\$|\(float\))/';

    public function __construct(private readonly string $basePath)
    {
    }

    /** @return list<string> */
    public function checkBusinessTermsInPlatform(): array
    {
        $platformDir = $this->basePath . '/app/Platform';

        if (! is_dir($platformDir)) {
            return [];
        }

        $violations = [];

        foreach ($this->phpFiles($platformDir) as $file) {
            $content = file_get_contents($file->getRealPath()) ?: '';

            foreach (self::BUSINESS_TERMS as $term) {
                if (stripos($content, $term) !== false) {
                    $violations[] = sprintf('[C1] %s contains "%s"', $file->getRealPath(), $term);
                }
            }
        }

        return $violations;
    }

    /** @return list<string> */
    public function checkNoFloatForAmounts(): array
    {
        $appDir = $this->basePath . '/app';

        if (! is_dir($appDir)) {
            return [];
        }

        $self       = realpath(__FILE__);
        $violations = [];

        foreach ($this->phpFiles($appDir) as $file) {
            $realPath = $file->getRealPath();

            if ($realPath === $self) {
                continue;
            }

            $content = file_get_contents($realPath) ?: '';

            if (preg_match(self::FLOAT_PATTERN, $content)) {
                $violations[] = sprintf('[C4] %s uses float for an amount', $realPath);
            }
        }

        return $violations;
    }

    /** @return list<string> */
    public function checkMigrationsHaveDown(): array
    {
        $dirs = [
            $this->basePath . '/database/migrations',
            $this->basePath . '/database/migrations/tenant',
        ];

        $violations = [];

        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            foreach ($this->phpFiles($dir, recursive: false) as $file) {
                $content = file_get_contents($file->getRealPath()) ?: '';

                if (! $this->hasNonEmptyDown($content)) {
                    $violations[] = sprintf('[C5] %s has no non-empty down()', $file->getRealPath());
                }
            }
        }

        return $violations;
    }

    /** @return list<string> */
    public function checkDiscountAmountIsInteger(): array
    {
        $violations = [];
        $dirs       = [
            $this->basePath . '/database/migrations',
            $this->basePath . '/app-modules',
        ];

        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            foreach ($this->phpFiles($dir) as $file) {
                $content = file_get_contents($file->getRealPath()) ?: '';

                // Flag any migration that defines discount_amount as decimal/float/double
                if (preg_match('/->(decimal|float|double)\s*\(\s*[\'"]discount_amount[\'"]/', $content)) {
                    $violations[] = sprintf('[C6] %s: discount_amount must be bigInteger, not decimal/float', $file->getRealPath());
                }
            }
        }

        return $violations;
    }

    private function hasNonEmptyDown(string $content): bool
    {
        if (! preg_match('/function\s+down\s*\(\s*\)[^{]*\{([\s\S]*?)\}/m', $content, $matches)) {
            return false;
        }

        return trim($matches[1]) !== '';
    }

    /** @return iterable<SplFileInfo> */
    private function phpFiles(string $dir, bool $recursive = true): iterable
    {
        if ($recursive) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        } else {
            $iterator = new RecursiveDirectoryIterator($dir);
        }

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                yield $file;
            }
        }
    }
}
