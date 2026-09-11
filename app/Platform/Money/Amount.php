<?php

declare(strict_types=1);

namespace App\Platform\Money;

use Brick\Math\RoundingMode;
use Brick\Money\Money;

final readonly class Amount implements \JsonSerializable
{
    private const string CURRENCY = 'XAF';

    private function __construct(private Money $money)
    {
    }

    public static function fromInt(int $francs): self
    {
        return new self(Money::of($francs, self::CURRENCY));
    }

    public function toInt(): int
    {
        return (int) $this->money->getAmount()->toInt();
    }

    public function add(self $other): self
    {
        return new self($this->money->plus($other->money, RoundingMode::Unnecessary));
    }

    public function subtract(self $other): self
    {
        return new self($this->money->minus($other->money, RoundingMode::Unnecessary));
    }

    public function multiplyBy(int $factor): self
    {
        return new self($this->money->multipliedBy($factor, RoundingMode::Unnecessary));
    }

    public function jsonSerialize(): int
    {
        return $this->toInt();
    }

    public function __toString(): string
    {
        return $this->money->__toString();
    }
}
