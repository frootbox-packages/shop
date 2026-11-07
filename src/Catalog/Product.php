<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Catalog;

use DateTimeImmutable;
use Frootbox\Database\Entity;
use Frootbox\Shop\Provider\Stock;

/**
 * A product in the mirror of the catalog (decision 0311). Kept by the
 * sync; changes in the backend do not last.
 */
final readonly class Product extends Entity
{
    /**
     * @param array<mixed>|null $description rich text of the standard schema
     * @param array<mixed>      $images      ids of media
     */
    public function __construct(
        public string $id,
        public string $providerId,
        public string $sku,
        public ?string $gtin,
        public string $slug,
        public string $title,
        public ?string $shortDescription,
        public ?array $description,
        public array $images,
        public string $stock,
        public bool $orderable,
        public ?string $deliveryTime,
        public string $unit,
        public int $minQuantity,
        public int $quantityStep,
        /** The run of a sync of the whole catalog that saw it last */
        public ?string $syncedIn = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
    ) {}

    public function stock(): Stock
    {
        return Stock::tryFrom($this->stock) ?? Stock::Unknown;
    }

    /**
     * @return list<string>
     */
    public function media(): array
    {
        return array_values(array_filter($this->images, is_string(...)));
    }

    /**
     * The quantity a customer may order nearest to $wanted: at least the
     * minimum, a multiple of the step.
     */
    public function quantity(int $wanted): int
    {
        $step = max(1, $this->quantityStep);
        $quantity = max($this->minQuantity, $wanted);

        return (int) (ceil($quantity / $step) * $step);
    }
}
