<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

$baseUrl = 'https://api-proactiveclothing.azurewebsites.net/api/v1.0';
$apiKey  = '91425af8-0b2f-451c-83e6-e8657541ed1a';

$action = $_GET['action'] ?? 'sellable';
$id     = $_GET['id'] ?? '';

if ($action === 'sendorder') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request payload']);
        exit;
    }

    $name = trim((string)($input['name'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    $phone = trim((string)($input['phone'] ?? ''));
    $items = $input['items'] ?? [];

    if ($name === '' || $email === '' || $phone === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Missing customer details']);
        exit;
    }

    if (!is_array($items) || !$items) {
        http_response_code(400);
        echo json_encode(['error' => 'Cart is empty']);
        exit;
    }

    $lines = [];
    $lines[] = 'Order from Centricare product showcase';
    $lines[] = '';
    $lines[] = 'Items:';

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $qty = max(1, (int)($item['qty'] ?? 1));
        $title = trim((string)($item['title'] ?? $item['name'] ?? $item['sku'] ?? 'Item'));
        $sku = trim((string)($item['sku'] ?? ''));
        $productId = trim((string)($item['productId'] ?? ''));

        $line = $qty . ' x ' . $title;
        if ($sku !== '') {
            $line .= ' (SKU: ' . $sku . ')';
        }
        if ($productId !== '') {
            $line .= ' [ID: ' . $productId . ']';
        }

        $lines[] = $line;
    }

    $lines[] = '';
    $lines[] = 'Customer details:';
    $lines[] = 'Name: ' . $name;
    $lines[] = 'Email: ' . $email;
    $lines[] = 'Phone: ' . $phone;
    $lines[] = '';
    $lines[] = 'Please contact the customer to confirm and process the order.';

    $message = implode("\n", $lines);
    $subject = 'Order from Centricare';
    $headers = [
        'From: Centricare <info@centricare.co.za>',
        'Reply-To: ' . $name . ' <' . $email . '>',
        'Content-Type: text/plain; charset=UTF-8',
    ];

    $sent = @mail('info@centricare.co.za', $subject, $message, implode("\r\n", $headers));

    if (!$sent) {
        http_response_code(500);
        echo json_encode(['error' => 'Unable to send email']);
        exit;
    }

    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'getbyid') {
    if (empty($id)) {
        echo json_encode(['error' => 'Missing id parameter']);
        exit;
    }
    $apiUrl = "$baseUrl/product/getbyid/" . urlencode($id);
} else {
    $apiUrl = "$baseUrl/product/sellable";
}

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPGET        => true,
    CURLOPT_HTTPHEADER     => ["accesskey: $apiKey"],
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(['error' => 'cURL error', 'detail' => $curlError]);
    exit;
}

if ($httpCode !== 200) {
    echo json_encode(['error' => "API returned HTTP $httpCode", 'detail' => $response]);
    exit;
}

echo $response;
?>