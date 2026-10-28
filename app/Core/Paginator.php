<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Simple paginator value object.
 */
final class Paginator
{
    /** @param array<int, array<string, mixed>> $items */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage
    ) {
    }

    public static function fromQuery(string $countSql, string $dataSql, array $params, int $page, int $perPage = 10): self
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));
        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare($dataSql . ' LIMIT ' . $perPage . ' OFFSET ' . $offset);
        $stmt->execute($params);

        return new self($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function from(): int
    {
        return $this->total === 0 ? 0 : (($this->page - 1) * $this->perPage) + 1;
    }

    public function to(): int
    {
        return min($this->total, $this->page * $this->perPage);
    }

    public function hasPrevious(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->lastPage();
    }
}
