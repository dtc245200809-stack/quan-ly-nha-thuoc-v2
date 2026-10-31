<?php
/**
 * API AI Trợ Lý Khách Hàng & Dược Lý Tham Khảo
 * Hỗ trợ Dược sĩ / Thu ngân giải thích đơn thuốc, quy chế tích điểm,
 * và tư vấn cách dùng thuốc an toàn (có Guardrails & Huấn luyện nghiệp vụ).
 */
header('Content-Type: application/json; charset=utf-8');
include_once __DIR__ . '/app.php';
include_once __DIR__ . '/ai_service.php';

// Kiểm tra phiên đăng nhập
$auth = pms_auth('admin,manager,pharmacist,cashier');

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';
$customerName = trim($input['customer_name'] ?? '');
$customerPhone = trim($input['phone'] ?? '');
$customerPoints = (int)($input['points'] ?? 0);
$question = trim($input['question'] ?? '');
$medicines = trim($input['medicines'] ?? '');

if (!$action) {
    echo json_encode(['ok' => false, 'message' => 'Hành động không hợp lệ.']);
    exit;
}

// 1. Phân loại hạng thành viên & quyền lợi ưu đãi
$tier = 'Thành viên mới';
$discountRate = '0%';
$nextTierGoal = 50;
if ($customerPoints >= 100) {
    $tier = 'Khách hàng VIP Kim Cương';
    $discountRate = '10%';
    $nextTierGoal = 'Tối đa';
} elseif ($customerPoints >= 50) {
    $tier = 'Khách hàng Thân Thiết Vàng';
    $discountRate = '5%';
    $nextTierGoal = 100 - $customerPoints;
} elseif ($customerPoints >= 20) {
    $tier = 'Khách hàng Bạc';
    $discountRate = '2%';
    $nextTierGoal = 50 - $customerPoints;
} else {
    $nextTierGoal = 20 - $customerPoints;
}

// 2. System Instruction được HUẤN LUYỆN CHẶT CHẼ THEO CHUẨN GPP
$systemInstruction = "Bạn là Trợ lý Dược phẩm AI của Nhà thuốc chuẩn GPP.
NHIỆM VỤ CỦA BẠN:
1. Hỗ trợ Dược sĩ/Thu ngân giải thích cho khách hàng về:
   - Cách dùng thuốc đúng giờ, đúng liều lượng, uống trước hay sau ăn (chỉ dựa trên dược thư và dữ liệu cung cấp).
   - Quyền lợi điểm thưởng, hạng thành viên ({$tier}), chiết khấu ({$discountRate}).
   - Các lưu ý kiêng khem (ví dụ: không uống kháng sinh với sữa/rượu, uống nhiều nước).
2. NGUYÊN TẮC BẢO VỆ VÀ ĐẠO ĐỨC Y TẾ (BẮT BUỘC):
   - Tuyệt đối không thay đổi đơn thuốc của bác sĩ.
   - Không tự ý chẩn đoán hoặc kê đơn bệnh nan y, hiểm nghèo.
   - Luôn kết thúc bằng câu dặn dò ân cần và nhắc khách hàng: 'Nếu có bất kỳ triệu chứng bất thường nào, vui lòng liên hệ ngay Dược sĩ nhà thuốc hoặc Bác sĩ điều trị'.
3. Văn phong: Chuyên nghiệp, nhã nhặn, chuẩn mực y khoa, ân cần với người bệnh.";

if ($action === 'consult_customer') {
    // Kịch bản 1: AI giải thích và sinh lời dặn dò khách hàng khi mua thuốc
    $prompt = "Hãy tạo một bản 'Lời dặn dò & Hướng dẫn sử dụng thuốc' cho khách hàng:
- Tên khách hàng: {$customerName}
- Hạng thành viên hiện tại: {$tier} (Tích lũy: {$customerPoints} điểm, Chiết khấu áp dụng: {$discountRate})
- Danh sách thuốc hoặc câu hỏi của khách: " . ($medicines ? $medicines : $question) . "

Yêu cầu trình bày thành 3 phần rõ ràng:
1. Tóm tắt cách dùng an toàn (uống lúc nào, uống với nước ấm, những thứ cần tránh).
2. Quyền lợi tích điểm thành viên (còn bao nhiêu điểm để lên hạng tiếp theo).
3. Lời dặn dò sức khỏe & lưu ý liên hệ Dược sĩ phụ trách khi cần.";

    $aiRes = pms_call_gemini_with_rotation($prompt, $systemInstruction);
    
    // Đảm bảo luôn có disclaimer pháp lý
    $finalText = $aiRes['text'];
    if (!str_contains($finalText, 'Lưu ý y tế')) {
        $finalText .= "\n\n---\n*⚠️ Lưu ý y tế: Thông tin mang tính chất hướng dẫn hỗ trợ tham khảo. Bệnh nhân cần tuân thủ tuyệt đối đơn thuốc của bác sĩ chuyên khoa.*";
    }

    echo json_encode([
        'ok' => true,
        'tier' => $tier,
        'discount' => $discountRate,
        'text' => $finalText,
        'model' => $aiRes['model'] ?? 'Gemini AI',
        'key_fp' => $aiRes['key_fingerprint'] ?? 'Nội bộ'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'explain_policy') {
    // Kịch bản 2: Giải thích quyền lợi tích điểm & chính sách ưu đãi
    $prompt = "Khách hàng {$customerName} (SĐT: {$customerPhone}, đang có {$customerPoints} điểm, hạng {$tier}).
Hãy giải thích ngắn gọn, niềm nở cho khách hàng biết:
- Quy tắc tích điểm: Mỗi 10.000đ mua hàng tích được 1 điểm (tương đương 100đ giảm giá cho lần sau).
- Họ đang được hưởng chiết khấu trực tiếp {$discountRate}.
- Cần thêm {$nextTierGoal} điểm nữa để được nâng hạng và tăng quyền lợi chiết khấu.
- Khuyến khích chăm sóc sức khỏe định kỳ tại nhà thuốc.";

    $aiRes = pms_call_gemini_with_rotation($prompt, $systemInstruction);
    echo json_encode([
        'ok' => true,
        'tier' => $tier,
        'discount' => $discountRate,
        'text' => $aiRes['text'],
        'model' => $aiRes['model'] ?? 'Gemini AI'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => false, 'message' => 'Yêu cầu không được hỗ trợ.']);
