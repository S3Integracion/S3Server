<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class MarketplaceRepository
{
    private PDO $pdo;

    /**
     * @var array<string, int|null>
     */
    private array $cache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Resolves an Amazon Seller Central host (e.g. "sellercentral.amazon.com.mx")
     * to the catalog id in amazon_marketplaces. Returns null when the host is
     * unknown — the import service treats this as a soft warning and stores the
     * order without marketplace_id.
     */
    public function findIdByHost(?string $host): ?int
    {
        if ($host === null) {
            return null;
        }

        $normalized = strtolower(trim($host));
        if ($normalized === '') {
            return null;
        }

        if (array_key_exists($normalized, $this->cache)) {
            return $this->cache[$normalized];
        }

        $sql = 'SELECT id FROM amazon_marketplaces WHERE host_pattern = :host AND is_active = 1 LIMIT 1';
        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':host', $normalized, PDO::PARAM_STR);
        $statement->execute();
        $row = $statement->fetch();

        $id = is_array($row) && isset($row['id']) ? (int) $row['id'] : null;
        $this->cache[$normalized] = $id;
        return $id;
    }
}
