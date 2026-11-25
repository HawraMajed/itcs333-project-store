<?php
declare(strict_types=1);

$rest = $GLOBALS['__route']['rest'] ?? '';
$method = http_method();
$pdo = db();

// Check if the user is logged in
if (empty($_SESSION['user_id'])) {
    send_error(401, 'AUTH_REQUIRED');
}

$user_id = (int) $_SESSION['user_id'];

function formatCartItem(array $row): array
{
    $price = (float) $row['price'];
    $qty = (int) $row['qty'];

    return [
        'id' => (int) $row['id'],
        'product_id' => (int) $row['product_id'],
        'qty' => $qty,
        'unit_price' => $price,
        'subtotal' => round($price * $qty, 2)
    ];
}
// Read cart items
switch ($method) {

    case 'GET':

        if ($rest !== '') {
            send_error(404, 'NOT_FOUND');
        }

        $stmt = $pdo->prepare(
            "SELECT cart_items.id,
                    cart_items.product_id,
                    cart_items.qty,
                    products.price
             FROM cart_items, products
             WHERE cart_items.product_id = products.id
             AND cart_items.user_id = ?
             ORDER BY cart_items.id"
        );

        $stmt->execute([$user_id]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        $total = 0;

        foreach ($rows as $row) {
            $item = formatCartItem($row);
            $items[] = $item;
            $total += $item['subtotal'];
        }

        send_json(200, [
            'data' => $items,
            'total' => round($total, 2)
        ]);

        break;
        case 'POST':

        if ($rest !== '') {
            send_error(404, 'NOT_FOUND');
        }

        $input = json_body();

        $product_id = param_id(
            isset($input['product_id'])
            ? (string) $input['product_id'] : null
        );

        $qty = (int) ($input['qty'] ?? 0);

        if (!$product_id) {
            send_error(422, 'VALIDATION', [
                'fields' => ['product_id' => 'required']
            ]);
        }

        if ($qty < 1) {
            send_error(422, 'VALIDATION', [
                'fields' => ['qty' => 'must be >= 1']
            ]);
        }

        $stmt = $pdo->prepare(
            "SELECT id, price FROM products WHERE id = ?"
        );

        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            send_error(404, 'PRODUCT_NOT_FOUND');
        }

        $id = db_insert_and_get_id($pdo, 'cart_items', [
            'user_id' => $user_id,
            'product_id' => $product_id,
            'qty' => $qty
        ]);

        send_json(201, formatCartItem([
            'id' => $id,
            'product_id' => $product_id,
            'qty' => $qty,
            'price' => $product['price']
        ]));

        break;
        case 'PATCH':

        if (!preg_match('#^/(\d+)$#', $rest, $matches)) {
            send_error(404, 'NOT_FOUND');
        }

        $id = (int) $matches[1];

        $input = json_body();
        $qty = (int) ($input['qty'] ?? 0);

        if ($qty < 1) {
            send_error(422, 'VALIDATION', [
                'fields' => ['qty' => 'must be >= 1']
            ]);
        }

        $stmt = $pdo->prepare(
            "SELECT cart_items.id,
                    cart_items.product_id,
                    products.price
             FROM cart_items, products
             WHERE cart_items.product_id = products.id
             AND cart_items.id = ?
             AND cart_items.user_id = ?"
        );

        $stmt->execute([$id, $user_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            send_error(404, 'NOT_FOUND');
        }

        $stmt = $pdo->prepare(
            "UPDATE cart_items SET qty = ?
             WHERE id = ? AND user_id = ?"
        );

        $stmt->execute([$qty, $id, $user_id]);

        $item['qty'] = $qty;

        send_json(200, formatCartItem($item));

        break;
        case 'DELETE':

        if (!preg_match('#^/(\d+)$#', $rest, $matches)) {
            send_error(404, 'NOT_FOUND');
        }

        $id = (int) $matches[1];

        $stmt = $pdo->prepare(
            "DELETE FROM cart_items
             WHERE id = ? AND user_id = ?"
        );

        $stmt->execute([$id, $user_id]);

        if ($stmt->rowCount() === 0) {
            send_error(404, 'NOT_FOUND');
        }

        send_json(200, ['deleted' => true]);

        break;

    default:
        send_error(404, 'NOT_FOUND');
}