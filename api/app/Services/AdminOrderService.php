<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\AdminOrderReadRepository;

final class AdminOrderService
{
    public function __construct(private AdminOrderReadRepository $repo) {}

    public function listOrders(int $page, int $perPage, array $filters): array
    {
        $total  = $this->repo->count($filters);
        $offset = ($page - 1) * $perPage;
        $items  = $this->repo->list($perPage, $offset, $filters);

        return [
            'items' => $items,
            'meta'  => [
                'total'    => $total,
                'page'     => $page,
                'per_page' => $perPage,
                'pages'    => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
            ],
        ];
    }

    public function getOrder(int $id): array
    {
        $order = $this->repo->findById($id);
        if ($order === null) {
            throw new HttpException(404, 'not_found', 'Order not found.');
        }

        $order['items']    = $this->repo->getOrderItems($id);
        $packages          = $this->repo->getOrderPackages($id);

        foreach ($packages as &$pkg) {
            $pkg['delivery_events'] = $this->repo->getDeliveryEvents((int) $pkg['id']);
        }
        unset($pkg);

        $order['packages'] = $packages;

        return $order;
    }

    public function getMarketplaces(): array
    {
        return $this->repo->getMarketplaces();
    }
}
