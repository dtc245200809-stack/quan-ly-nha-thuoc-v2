<?php
function pms_create_vietqr(int $amount, string $invoiceCode): array
{
    $url = 'https://api.vietqr.io/v2/generate';

    $payload = [
        'accountNo' => '106877097018',          // số TK thật của m
        'accountName' => 'NGUYEN MINH PHUONG',   // tên TK in hoa không dấu càng tốt
        'acqId' => 970415,                  // mã ngân hàng theo VietQR docs
        'amount' => $amount,
        'addInfo' => 'THANH TOAN ' . $invoiceCode,
        'template' => 'compact'
    ];

    $headers = [
        'Content-Type: application/json',
        'x-client-id: YOUR_CLIENT_ID',
        'x-api-key: YOUR_API_KEY'
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 20,
    ]);

    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        return ['ok' => false, 'message' => 'Lỗi kết nối VietQR: ' . $err];
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
        return ['ok' => false, 'message' => 'Response VietQR không hợp lệ', 'raw' => $raw];
    }

    if ($http !== 200 || ($json['code'] ?? '') !== '00' || empty($json['data']['qrDataURL'])) {
        return [
            'ok' => false,
            'message' => $json['desc'] ?? 'Tạo QR thất bại',
            'raw' => $json
        ];
    }

    return [
        'ok' => true,
        'qrDataURL' => $json['data']['qrDataURL'],
        'qrCode' => $json['data']['qrCode'] ?? '',
        'bankName' => $json['data']['accountName'] ?? $payload['accountName'],
        'amount' => $amount,
        'content' => $payload['addInfo'],
    ];
}