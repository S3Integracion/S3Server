<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use PDOStatement;

/**
 * Writes Amazon order data into the OrderDetails schema with idempotent
 * UPSERTs. The history triggers in OrderDetails.sql read @current_user_id
 * and @current_run_id; setSessionContext() must be called before any UPDATE.
 */
final class OrderRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function setSessionContext(int $userId, int $runId): void
    {
        // Two separate SETs — MySQL accepts them but emulated prepares are off,
        // so we use exec() with sanitized integers.
        $this->pdo->exec(sprintf('SET @current_user_id = %d', $userId));
        $this->pdo->exec(sprintf('SET @current_run_id = %d', $runId));
    }

    private const VALID_DELIVERY_STATUSES = [
        'Entregado', 'Tránsito', 'No entregado', 'Sin movimiento', 'Cancelado',
    ];

    public function sanitizeDeliveryStatus(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $trimmed = trim($value);
        return in_array($trimmed, self::VALID_DELIVERY_STATUSES, true) ? $trimmed : null;
    }

    /**
     * @return array{id:int,inserted:bool,updated:bool}
     */
    public function upsertOrder(array $data, int $runId, int $userId, ?int $marketplaceId): array
    {
        $sql = 'INSERT INTO orders (
                    order_id, marketplace_id,
                    purchase_datetime, ship_by_date, delivery_from_date, delivery_to_date,
                    shipping_service, fulfillment_channel, payment_method,
                    recipient_name, contact_buyer_name, phone_number,
                    address_line_1, address_line_2, address_line_3,
                    city, state_region, postal_code, country_code,
                    items_total, tax_total, grand_total,
                    package_count, order_item_count, total_quantity,
                    delivery_status, has_refund, refund_amount,
                    first_import_run_id, last_import_run_id,
                    first_imported_by, last_imported_by
                ) VALUES (
                    :order_id, :marketplace_id,
                    :purchase_datetime, :ship_by_date, :delivery_from_date, :delivery_to_date,
                    :shipping_service, :fulfillment_channel, :payment_method,
                    :recipient_name, :contact_buyer_name, :phone_number,
                    :address_line_1, :address_line_2, :address_line_3,
                    :city, :state_region, :postal_code, :country_code,
                    :items_total, :tax_total, :grand_total,
                    :package_count, :order_item_count, :total_quantity,
                    :delivery_status, :has_refund, :refund_amount,
                    :run_id, :run_id_dup,
                    :user_id, :user_id_dup
                )
                ON DUPLICATE KEY UPDATE
                    id = LAST_INSERT_ID(id),
                    marketplace_id = COALESCE(VALUES(marketplace_id), marketplace_id),
                    purchase_datetime = COALESCE(VALUES(purchase_datetime), purchase_datetime),
                    ship_by_date = COALESCE(VALUES(ship_by_date), ship_by_date),
                    delivery_from_date = COALESCE(VALUES(delivery_from_date), delivery_from_date),
                    delivery_to_date = COALESCE(VALUES(delivery_to_date), delivery_to_date),
                    shipping_service = COALESCE(VALUES(shipping_service), shipping_service),
                    fulfillment_channel = COALESCE(VALUES(fulfillment_channel), fulfillment_channel),
                    payment_method = COALESCE(VALUES(payment_method), payment_method),
                    recipient_name = COALESCE(VALUES(recipient_name), recipient_name),
                    contact_buyer_name = COALESCE(VALUES(contact_buyer_name), contact_buyer_name),
                    phone_number = COALESCE(VALUES(phone_number), phone_number),
                    address_line_1 = COALESCE(VALUES(address_line_1), address_line_1),
                    address_line_2 = COALESCE(VALUES(address_line_2), address_line_2),
                    address_line_3 = COALESCE(VALUES(address_line_3), address_line_3),
                    city = COALESCE(VALUES(city), city),
                    state_region = COALESCE(VALUES(state_region), state_region),
                    postal_code = COALESCE(VALUES(postal_code), postal_code),
                    country_code = COALESCE(VALUES(country_code), country_code),
                    items_total = COALESCE(VALUES(items_total), items_total),
                    tax_total = COALESCE(VALUES(tax_total), tax_total),
                    grand_total = COALESCE(VALUES(grand_total), grand_total),
                    package_count = COALESCE(VALUES(package_count), package_count),
                    order_item_count = COALESCE(VALUES(order_item_count), order_item_count),
                    total_quantity = COALESCE(VALUES(total_quantity), total_quantity),
                    delivery_status = COALESCE(VALUES(delivery_status), delivery_status),
                    has_refund = VALUES(has_refund),
                    refund_amount = COALESCE(VALUES(refund_amount), refund_amount),
                    last_import_run_id = VALUES(last_import_run_id),
                    last_imported_by = VALUES(last_imported_by)';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':order_id', $data['order_id'], PDO::PARAM_STR);
        $this->bindIntOrNull($statement, ':marketplace_id', $marketplaceId);

        $this->bindStringOrNull($statement, ':purchase_datetime', $data['purchase_datetime'] ?? null, 19);
        $this->bindStringOrNull($statement, ':ship_by_date', $data['ship_by_date'] ?? null, 10);
        $this->bindStringOrNull($statement, ':delivery_from_date', $data['delivery_from_date'] ?? null, 10);
        $this->bindStringOrNull($statement, ':delivery_to_date', $data['delivery_to_date'] ?? null, 10);

        $this->bindStringOrNull($statement, ':shipping_service', $data['shipping_service'] ?? null, 120);
        $this->bindStringOrNull($statement, ':fulfillment_channel', $data['fulfillment_channel'] ?? null, 60);
        $this->bindStringOrNull($statement, ':payment_method', $data['payment_method'] ?? null, 80);

        $this->bindStringOrNull($statement, ':recipient_name', $data['recipient_name'] ?? null, 120);
        $this->bindStringOrNull($statement, ':contact_buyer_name', $data['contact_buyer_name'] ?? null, 120);
        $this->bindStringOrNull($statement, ':phone_number', $data['phone_number'] ?? null, 20);
        $this->bindStringOrNull($statement, ':address_line_1', $data['address_line_1'] ?? null, 120);
        $this->bindStringOrNull($statement, ':address_line_2', $data['address_line_2'] ?? null, 120);
        $this->bindStringOrNull($statement, ':address_line_3', $data['address_line_3'] ?? null, 120);
        $this->bindStringOrNull($statement, ':city', $data['city'] ?? null, 80);
        $this->bindStringOrNull($statement, ':state_region', $data['state_region'] ?? null, 80);
        $this->bindStringOrNull($statement, ':postal_code', $data['postal_code'] ?? null, 20);
        $this->bindStringOrNull($statement, ':country_code', $data['country_code'] ?? null, 2);

        $this->bindDecimalOrNull($statement, ':items_total', $data['items_total'] ?? null);
        $this->bindDecimalOrNull($statement, ':tax_total', $data['tax_total'] ?? null);
        $this->bindDecimalOrNull($statement, ':grand_total', $data['grand_total'] ?? null);

        $this->bindIntOrNull($statement, ':package_count', $this->coerceIntOrNull($data['package_count'] ?? null));
        $this->bindIntOrNull($statement, ':order_item_count', $this->coerceIntOrNull($data['order_item_count'] ?? null));
        $this->bindIntOrNull($statement, ':total_quantity', $this->coerceIntOrNull($data['total_quantity'] ?? null));

        $deliveryStatus = $this->sanitizeDeliveryStatus($data['delivery_status'] ?? null);
        $this->bindStringOrNull($statement, ':delivery_status', $deliveryStatus, 20);
        $hasRefund = ($data['has_refund'] ?? 'No') === 'Sí' ? 'Sí' : 'No';
        $statement->bindValue(':has_refund', $hasRefund, PDO::PARAM_STR);
        $this->bindDecimalOrNull($statement, ':refund_amount', $data['refund_amount'] ?? null);

        $statement->bindValue(':run_id', $runId, PDO::PARAM_INT);
        $statement->bindValue(':run_id_dup', $runId, PDO::PARAM_INT);
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':user_id_dup', $userId, PDO::PARAM_INT);

        $statement->execute();
        return $this->classifyUpsert($statement);
    }

    /**
     * @return array{id:int,inserted:bool,updated:bool}
     */
    public function upsertOrderItem(int $orderRefId, string $orderId, array $data, int $runId): array
    {
        $sql = 'INSERT INTO order_items (
                    order_id_ref, order_id, order_item_id, line_number,
                    asin, sku, product_name, condition_val,
                    quantity, unit_price, item_subtotal, tax, item_total,
                    import_run_id
                ) VALUES (
                    :order_id_ref, :order_id, :order_item_id, :line_number,
                    :asin, :sku, :product_name, :condition_val,
                    :quantity, :unit_price, :item_subtotal, :tax, :item_total,
                    :run_id
                )
                ON DUPLICATE KEY UPDATE
                    id = LAST_INSERT_ID(id),
                    line_number = COALESCE(VALUES(line_number), line_number),
                    asin = COALESCE(VALUES(asin), asin),
                    sku = COALESCE(VALUES(sku), sku),
                    product_name = COALESCE(VALUES(product_name), product_name),
                    condition_val = COALESCE(VALUES(condition_val), condition_val),
                    quantity = COALESCE(VALUES(quantity), quantity),
                    unit_price = COALESCE(VALUES(unit_price), unit_price),
                    item_subtotal = COALESCE(VALUES(item_subtotal), item_subtotal),
                    tax = COALESCE(VALUES(tax), tax),
                    item_total = COALESCE(VALUES(item_total), item_total),
                    import_run_id = VALUES(import_run_id)';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':order_id_ref', $orderRefId, PDO::PARAM_INT);
        $statement->bindValue(':order_id', $orderId, PDO::PARAM_STR);
        $statement->bindValue(':order_item_id', $data['order_item_id'], PDO::PARAM_STR);
        $this->bindIntOrNull($statement, ':line_number', $this->coerceIntOrNull($data['line_number'] ?? null));
        $this->bindStringOrNull($statement, ':asin', $data['asin'] ?? null, 10);
        $this->bindStringOrNull($statement, ':sku', $data['sku'] ?? null, 128);
        $this->bindStringOrNull($statement, ':product_name', $data['product_name'] ?? null, 400);
        $this->bindStringOrNull($statement, ':condition_val', $data['condition_val'] ?? null, 60);
        $this->bindIntOrNull($statement, ':quantity', $this->coerceIntOrNull($data['quantity'] ?? null));
        $this->bindDecimalOrNull($statement, ':unit_price', $data['unit_price'] ?? null);
        $this->bindDecimalOrNull($statement, ':item_subtotal', $data['item_subtotal'] ?? null);
        $this->bindDecimalOrNull($statement, ':tax', $data['tax'] ?? null);
        $this->bindDecimalOrNull($statement, ':item_total', $data['item_total'] ?? null);
        $statement->bindValue(':run_id', $runId, PDO::PARAM_INT);

        $statement->execute();
        return $this->classifyUpsert($statement);
    }

    /**
     * @return array{id:int,inserted:bool,updated:bool}
     */
    public function upsertOrderPackage(int $orderRefId, string $orderId, array $data, int $runId): array
    {
        $sql = 'INSERT INTO order_packages (
                    order_id_ref, order_id, package_number,
                    tracking_id, carrier, shipping_service, package_type, ship_date,
                    dim_length, dim_width, dim_height, dim_unit, dimensions_raw,
                    weight_kg, weight_g, weight_raw,
                    delivery_events_count, has_movement,
                    first_event_time, first_event_location, first_event_details,
                    last_event_time, last_event_location, last_event_details,
                    import_run_id
                ) VALUES (
                    :order_id_ref, :order_id, :package_number,
                    :tracking_id, :carrier, :shipping_service, :package_type, :ship_date,
                    :dim_length, :dim_width, :dim_height, :dim_unit, :dimensions_raw,
                    :weight_kg, :weight_g, :weight_raw,
                    :delivery_events_count, :has_movement,
                    :first_event_time, :first_event_location, :first_event_details,
                    :last_event_time, :last_event_location, :last_event_details,
                    :run_id
                )
                ON DUPLICATE KEY UPDATE
                    id = LAST_INSERT_ID(id),
                    tracking_id = COALESCE(VALUES(tracking_id), tracking_id),
                    carrier = COALESCE(VALUES(carrier), carrier),
                    shipping_service = COALESCE(VALUES(shipping_service), shipping_service),
                    package_type = COALESCE(VALUES(package_type), package_type),
                    ship_date = COALESCE(VALUES(ship_date), ship_date),
                    dim_length = COALESCE(VALUES(dim_length), dim_length),
                    dim_width = COALESCE(VALUES(dim_width), dim_width),
                    dim_height = COALESCE(VALUES(dim_height), dim_height),
                    dim_unit = COALESCE(VALUES(dim_unit), dim_unit),
                    dimensions_raw = COALESCE(VALUES(dimensions_raw), dimensions_raw),
                    weight_kg = COALESCE(VALUES(weight_kg), weight_kg),
                    weight_g = COALESCE(VALUES(weight_g), weight_g),
                    weight_raw = COALESCE(VALUES(weight_raw), weight_raw),
                    delivery_events_count = VALUES(delivery_events_count),
                    has_movement = VALUES(has_movement),
                    first_event_time = COALESCE(VALUES(first_event_time), first_event_time),
                    first_event_location = COALESCE(VALUES(first_event_location), first_event_location),
                    first_event_details = COALESCE(VALUES(first_event_details), first_event_details),
                    last_event_time = COALESCE(VALUES(last_event_time), last_event_time),
                    last_event_location = COALESCE(VALUES(last_event_location), last_event_location),
                    last_event_details = COALESCE(VALUES(last_event_details), last_event_details),
                    import_run_id = VALUES(import_run_id)';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':order_id_ref', $orderRefId, PDO::PARAM_INT);
        $statement->bindValue(':order_id', $orderId, PDO::PARAM_STR);
        $statement->bindValue(':package_number', max(1, (int) ($data['package_number'] ?? 1)), PDO::PARAM_INT);

        $this->bindStringOrNull($statement, ':tracking_id', $data['tracking_id'] ?? null, 40);
        $this->bindStringOrNull($statement, ':carrier', $data['carrier'] ?? null, 80);
        $this->bindStringOrNull($statement, ':shipping_service', $data['shipping_service'] ?? null, 120);
        $this->bindStringOrNull($statement, ':package_type', $data['package_type'] ?? null, 80);
        $this->bindStringOrNull($statement, ':ship_date', $data['ship_date'] ?? null, 10);

        $this->bindDecimalOrNull($statement, ':dim_length', $data['dim_length'] ?? null);
        $this->bindDecimalOrNull($statement, ':dim_width', $data['dim_width'] ?? null);
        $this->bindDecimalOrNull($statement, ':dim_height', $data['dim_height'] ?? null);
        $this->bindStringOrNull($statement, ':dim_unit', $data['dim_unit'] ?? null, 5);
        $this->bindStringOrNull($statement, ':dimensions_raw', $data['dimensions_raw'] ?? null, 120);

        $this->bindDecimalOrNull($statement, ':weight_kg', $data['weight_kg'] ?? null);
        $this->bindDecimalOrNull($statement, ':weight_g', $data['weight_g'] ?? null);
        $this->bindStringOrNull($statement, ':weight_raw', $data['weight_raw'] ?? null, 120);

        $statement->bindValue(':delivery_events_count', max(0, (int) ($data['delivery_events_count'] ?? 0)), PDO::PARAM_INT);
        $statement->bindValue(':has_movement', !empty($data['has_movement']) ? 1 : 0, PDO::PARAM_INT);

        $this->bindStringOrNull($statement, ':first_event_time', $data['first_event_time'] ?? null, 19);
        $this->bindStringOrNull($statement, ':first_event_location', $data['first_event_location'] ?? null, 120);
        $this->bindStringOrNull($statement, ':first_event_details', $data['first_event_details'] ?? null, 240);
        $this->bindStringOrNull($statement, ':last_event_time', $data['last_event_time'] ?? null, 19);
        $this->bindStringOrNull($statement, ':last_event_location', $data['last_event_location'] ?? null, 120);
        $this->bindStringOrNull($statement, ':last_event_details', $data['last_event_details'] ?? null, 240);

        $statement->bindValue(':run_id', $runId, PDO::PARAM_INT);
        $statement->execute();

        return $this->classifyUpsert($statement);
    }

    public function upsertPackageItem(int $packageId, int $orderItemRefId, string $orderItemId, array $data, int $runId): void
    {
        $sql = 'INSERT INTO package_items (
                    package_id, order_item_id_ref, order_item_id,
                    asin, sku, quantity_in_package, import_run_id
                ) VALUES (
                    :package_id, :order_item_id_ref, :order_item_id,
                    :asin, :sku, :quantity_in_package, :run_id
                )
                ON DUPLICATE KEY UPDATE
                    asin = COALESCE(VALUES(asin), asin),
                    sku = COALESCE(VALUES(sku), sku),
                    quantity_in_package = VALUES(quantity_in_package),
                    import_run_id = VALUES(import_run_id)';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':package_id', $packageId, PDO::PARAM_INT);
        $statement->bindValue(':order_item_id_ref', $orderItemRefId, PDO::PARAM_INT);
        $statement->bindValue(':order_item_id', $orderItemId, PDO::PARAM_STR);
        $this->bindStringOrNull($statement, ':asin', $data['asin'] ?? null, 10);
        $this->bindStringOrNull($statement, ':sku', $data['sku'] ?? null, 128);
        $statement->bindValue(':quantity_in_package', max(1, (int) ($data['quantity_in_package'] ?? 1)), PDO::PARAM_INT);
        $statement->bindValue(':run_id', $runId, PDO::PARAM_INT);
        $statement->execute();
    }

    public function deleteOrderNotRegistered(string $orderId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM order_not_registered WHERE order_id = :order_id');
        $stmt->bindValue(':order_id', $orderId, PDO::PARAM_STR);
        $stmt->execute();
    }

    public function upsertOrderNotDelivered(string $orderId, ?string $trackingId, string $extractedAt): void
    {
        $sql = 'INSERT INTO order_not_delivered (order_id, tracking_id, extracted_at)
                VALUES (:order_id, :tracking_id, :extracted_at)
                ON DUPLICATE KEY UPDATE
                    tracking_id = COALESCE(VALUES(tracking_id), tracking_id),
                    extracted_at = VALUES(extracted_at)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':order_id', $orderId, PDO::PARAM_STR);
        $this->bindStringOrNull($stmt, ':tracking_id', $trackingId, 40);
        $stmt->bindValue(':extracted_at', $extractedAt, PDO::PARAM_STR);
        $stmt->execute();
    }

    public function deleteOrderNotDelivered(string $orderId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM order_not_delivered WHERE order_id = :order_id');
        $stmt->bindValue(':order_id', $orderId, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * Inserts a delivery event if not already present (UNIQUE on package_id+event_hash).
     * Returns true when a new row was inserted.
     */
    public function insertDeliveryEvent(int $packageId, string $orderId, ?string $trackingId, array $event, int $runId): bool
    {
        $eventTime = $this->normalizeDateTime($event['event_time'] ?? null);
        $eventTimeRaw = isset($event['event_time_raw']) ? (string) $event['event_time_raw'] : '';
        $location = isset($event['location']) ? (string) $event['location'] : '';
        $details = isset($event['event_details']) ? (string) $event['event_details'] : '';

        $hashSource = ($eventTime ?? '') . '|' . $location . '|' . $details;
        $eventHash = hash('sha256', $hashSource);

        $sql = 'INSERT IGNORE INTO delivery_events (
                    package_id, order_id, tracking_id,
                    event_time, event_time_raw, location, event_details,
                    event_hash, import_run_id
                ) VALUES (
                    :package_id, :order_id, :tracking_id,
                    :event_time, :event_time_raw, :location, :event_details,
                    :event_hash, :run_id
                )';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':package_id', $packageId, PDO::PARAM_INT);
        $statement->bindValue(':order_id', $orderId, PDO::PARAM_STR);
        $this->bindStringOrNull($statement, ':tracking_id', $trackingId, 40);
        $this->bindStringOrNull($statement, ':event_time', $eventTime, 19);
        $this->bindStringOrNull($statement, ':event_time_raw', $eventTimeRaw === '' ? null : $eventTimeRaw, 80);
        $this->bindStringOrNull($statement, ':location', $location === '' ? null : $location, 120);
        $this->bindStringOrNull($statement, ':event_details', $details === '' ? null : $details, 240);
        $statement->bindValue(':event_hash', $eventHash, PDO::PARAM_STR);
        $statement->bindValue(':run_id', $runId, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount() > 0;
    }

    private function classifyUpsert(PDOStatement $statement): array
    {
        $rows = $statement->rowCount();
        $id = (int) $this->pdo->lastInsertId();
        return [
            'id' => $id,
            'inserted' => $rows === 1,
            'updated' => $rows === 2,
        ];
    }

    private function bindStringOrNull(PDOStatement $statement, string $param, $value, int $maxLength): void
    {
        if ($value === null) {
            $statement->bindValue($param, null, PDO::PARAM_NULL);
            return;
        }
        $string = is_scalar($value) ? trim((string) $value) : '';
        if ($string === '') {
            $statement->bindValue($param, null, PDO::PARAM_NULL);
            return;
        }
        $statement->bindValue($param, substr($string, 0, $maxLength), PDO::PARAM_STR);
    }

    private function bindIntOrNull(PDOStatement $statement, string $param, ?int $value): void
    {
        if ($value === null) {
            $statement->bindValue($param, null, PDO::PARAM_NULL);
            return;
        }
        $statement->bindValue($param, $value, PDO::PARAM_INT);
    }

    private function bindDecimalOrNull(PDOStatement $statement, string $param, $value): void
    {
        if ($value === null || $value === '' || (is_string($value) && trim($value) === '')) {
            $statement->bindValue($param, null, PDO::PARAM_NULL);
            return;
        }
        if (!is_numeric($value)) {
            $statement->bindValue($param, null, PDO::PARAM_NULL);
            return;
        }
        $statement->bindValue($param, (string) $value, PDO::PARAM_STR);
    }

    private function coerceIntOrNull($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && (ctype_digit($value) || (str_starts_with($value, '-') && ctype_digit(substr($value, 1))))) {
            return (int) $value;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        return null;
    }

    private function normalizeDateTime(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }
        // Accepts both 'YYYY-MM-DD HH:MM:SS' and ISO 8601 with T/Z; we keep
        // only the first 19 chars after replacing 'T' with space.
        $iso = str_replace('T', ' ', $trimmed);
        return substr($iso, 0, 19);
    }
}
