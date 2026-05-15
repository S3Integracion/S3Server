<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\AdminOrderService;
use App\Validation\Validator;

final class AdminOrdersController
{
    public function __construct(private AdminOrderService $service) {}

    public function index(Request $request): void
    {
        $query      = $request->allQuery();
        $pagination = Validator::pagination($query);

        $filters = [];
        if (!empty($query['search']))              $filters['search']              = trim((string) $query['search']);
        if (!empty($query['marketplace_country'])) $filters['marketplace_country'] = trim((string) $query['marketplace_country']);
        if (!empty($query['date_from']))           $filters['date_from']           = trim((string) $query['date_from']);
        if (!empty($query['date_to']))             $filters['date_to']             = trim((string) $query['date_to']);
        if (!empty($query['delivery_status']))     $filters['delivery_status']     = trim((string) $query['delivery_status']);
        if (isset($query['has_refund']) && $query['has_refund'] !== '') $filters['has_refund'] = trim((string) $query['has_refund']);

        $result = $this->service->listOrders($pagination['page'], $pagination['per_page'], $filters);
        Response::success($result['items'], 'Orders fetched.', 200, $result['meta']);
    }

    public function show(Request $request): void
    {
        $id    = Validator::routeInt($request->routeParam('id'), 'id');
        $order = $this->service->getOrder($id);
        Response::success($order, 'Order fetched.');
    }

    public function marketplaces(Request $request): void
    {
        Response::success($this->service->getMarketplaces(), 'Marketplaces fetched.');
    }
}
