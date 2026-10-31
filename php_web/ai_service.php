<?php
/**
 * AI Service Helper for Pharmacy Management System
 * Handles Gemini (Multi-Key Auto-Rotation) / OpenAI APIs and fallback local rule-based smart engine
 */

function pms_get_ai_config(): array {
    $configFile = __DIR__ . '/ai_config.json';
    $default = [
        'provider' => 'gemini',
        'api_key' => '',
        'api_keys' => [],
        'current_key_index' => 0,
        'model' => 'gemini-2.5-flash',
        'custom_prompt' => ''
    ];
    if (file_exists($configFile)) {
        $data = json_decode(file_get_contents($configFile), true);
        if (is_array($data)) {
            $merged = array_merge($default, $data);
            // Chuẩn hóa danh sách api_keys
            if (empty($merged['api_keys']) && !empty($merged['api_key'])) {
                $rawList = preg_split('/[\r\n,]+/', trim($merged['api_key']));
                $merged['api_keys'] = array_values(array_filter(array_map('trim', $rawList)));
            }
            return $merged;
        }
    }
    return $default;
}

function pms_save_ai_config(array $config): bool {
    $configFile = __DIR__ . '/ai_config.json';
    return (bool)file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Core AI query function with prompt guard and fallback
 */
function pms_call_ai(string $prompt, string $systemPrompt = ''): array {
    $config = pms_get_ai_config();
    $provider = $config['provider'] ?? 'gemini';
    $model = $config['model'] ?? 'gemini-2.5-flash';
    $keys = $config['api_keys'] ?? [];
    if (empty($keys) && !empty($config['api_key'])) {
        $keys = [$config['api_key']];
    }

    if (empty($systemPrompt)) {
        $systemPrompt = "Bạn là trợ lý nội bộ nhà thuốc. TUYỆT ĐỐI không đưa lời khuyên điều trị, không chỉ định liều dùng ngoài dữ liệu được cung cấp. Luôn nhắc nhở nhân viên hỏi ý kiến Dược sĩ phụ trách chuyên môn khi tư vấn người bệnh.";
    }

    if (!empty($keys)) {
        if ($provider === 'gemini') {
            $res = pms_call_gemini_with_rotation($keys, $model, $prompt, $systemPrompt);
            if ($res['success']) return $res;
        } elseif ($provider === 'openai') {
            $res = pms_call_openai($keys[0], $model, $prompt, $systemPrompt);
            if ($res['success']) return $res;
        }
    }

    // Local Fallback Smart Engine (Khi chưa có API key hoặc toàn bộ key đều hết token)
    return [
        'success' => true,
        'mode' => 'local_engine',
        'text' => pms_local_ai_fallback($prompt, $systemPrompt)
    ];
}

/**
 * Gọi Google Gemini API với cơ chế tự động xoay key (Auto-Rotation) khi gặp lỗi 429
 */
function pms_call_gemini_with_rotation(array $keys, string $model, string $prompt, string $systemPrompt): array {
    $totalKeys = count($keys);
    $config = pms_get_ai_config();
    $startIndex = ($config['current_key_index'] ?? 0) % $totalKeys;

    for ($i = 0; $i < $totalKeys; $i++) {
        $currentIndex = ($startIndex + $i) % $totalKeys;
        $activeKey = trim($keys[$currentIndex]);
        if (empty($activeKey)) continue;

        $res = pms_call_gemini_single($activeKey, $model, $prompt, $systemPrompt);

        if ($res['success']) {
            // Cập nhật key index đang hoạt động tốt
            $config['current_key_index'] = $currentIndex;
            pms_save_ai_config($config);

            if ($i > 0) {
                $res['text'] .= "\n\n*(Hệ thống đã tự động xoay sang Key dự phòng #" . ($currentIndex + 1) . " do key trước hết Token/Quota)*";
            }
            return $res;
        }

        // Nếu gặp lỗi 429 (ResourceExhausted / Quota Limit) -> Tự động xoay sang key kế tiếp
        if (isset($res['http_code']) && $res['http_code'] === 429) {
            error_log("[GEMINI AUTO ROTATION] Key index $currentIndex hết hạn mức token (429), đang tự động chuyển key...");
            continue;
        }
    }

    return [
        'success' => false,
        'error' => "Toàn bộ $totalKeys key Gemini đều đã hết Token hoặc lỗi kết nối. Đã tự động kích hoạt Local Smart Engine."
    ];
}

function pms_call_gemini_single(string $apiKey, string $model, string $prompt, string $systemPrompt): array {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/" . urlencode($model) . ":generateContent?key=" . urlencode($apiKey);
    $data = [
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => "System Instruction: " . $systemPrompt . "\n\nYêu cầu công việc:\n" . $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.3,
            'maxOutputTokens' => 1024
        ]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err || $httpCode !== 200) {
        return [
            'success' => false,
            'http_code' => $httpCode,
            'error' => $err ?: "Gemini API lỗi HTTP $httpCode: " . substr((string)$response, 0, 150)
        ];
    }

    $json = json_decode($response, true);
    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (!empty($text)) {
        return ['success' => true, 'mode' => 'gemini_online', 'text' => trim($text)];
    }
    return ['success' => false, 'http_code' => $httpCode, 'error' => 'Không đọc được phản hồi từ Gemini.'];
}

function pms_call_openai(string $apiKey, string $model, string $prompt, string $systemPrompt): array {
    $url = "https://api.openai.com/v1/chat/completions";
    $data = [
        'model' => $model ?: 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.3,
        'max_tokens' => 1024
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err || $httpCode !== 200) {
        return ['success' => false, 'error' => $err ?: "OpenAI API lỗi HTTP $httpCode"];
    }

    $json = json_decode($response, true);
    $text = $json['choices'][0]['message']['content'] ?? '';
    if (!empty($text)) {
        return ['success' => true, 'mode' => 'openai_online', 'text' => $text];
    }
    return ['success' => false, 'error' => 'Không đọc được phản hồi OpenAI.'];
}

function pms_local_ai_fallback(string $prompt, string $systemPrompt): string {
    $lower = mb_strtolower($prompt, 'UTF-8');

    // 1. Guardrail kiểm tra câu hỏi y tế vượt thẩm quyền
    $medicalWords = ['uống thuốc gì', 'chữa bệnh gì', 'bị đau đầu uống gì', 'chữa sốt', 'chữa ung thư', 'kê đơn', 'uống bao nhiêu viên', 'điều trị bệnh', 'tư vấn liều', 'tự tử', 'quá liều'];
    foreach ($medicalWords as $w) {
        if (strpos($lower, $w) !== false && strpos($lower, 'tóm tắt') === false && strpos($lower, 'lô') === false && strpos($lower, 'báo cáo') === false) {
            return "⛔ **CẢNH BÁO AN TOÀN Y TẾ TỪ AI NHÀ THUỐC**:\n\n" .
                   "Hệ thống AI không được phép đưa ra chẩn đoán bệnh tật, chỉ định điều trị hoặc tư vấn liều dùng ngoài quy chuẩn y khoa.\n" .
                   "👉 **Yêu cầu nhân viên:** Hướng dẫn khách hàng đến cơ sở y tế khám chữa bệnh hoặc liên hệ trực tiếp với **Dược sĩ phụ trách chuyên môn** của nhà thuốc để được tư vấn chính xác.\n\n" .
                   "---\nLưu ý: *Thông tin chỉ mang tính tham khảo nội bộ, không thay thế Bác sĩ/Dược sĩ chuyên môn.*";
        }
    }

    // 2. Tóm tắt thuốc
    if (strpos($lower, 'tóm tắt') !== false || strpos($lower, 'dữ liệu thuốc') !== false) {
        return "📋 **TÓM TẮT THÔNG TIN THUỐC (THAM KHẢO NỘI BỘ)**\n\n" .
               "• **Nhóm hoạt chất & tác dụng chính:** Tham khảo thông tin đã được đăng ký trong danh mục thuốc chuẩn của nhà thuốc.\n" .
               "• **Dạng bào chế & bảo quản:** Bảo quản ở nhiệt độ dưới 30°C, tránh ánh nắng trực tiếp và độ ẩm cao.\n" .
               "• **Lưu ý thao tác tại quầy:** Kiểm tra đối chiếu tên thuốc, hàm lượng, số lô và hạn sử dụng trước khi bàn giao cho khách hàng.\n\n" .
               "Lưu ý: **LƯU Ý QUAN TRỌNG:** Bản tóm tắt này chỉ phục vụ mục đích tra cứu nội bộ cho nhân viên. Tuyệt đối không thay thế chỉ định của Bác sĩ hoặc Dược sĩ chuyên môn.";
    }

    // 3. Báo cáo hạn dùng & đề xuất xử lý
    if (strpos($lower, 'hạn dùng') !== false || strpos($lower, 'hết hạn') !== false || strpos($lower, 'báo cáo') !== false) {
        return "📊 **BÁO CÁO & ĐỀ XUẤT XỬ LÝ LÔ THUỐC CẬN HẠN**\n\n" .
               "1. **Phân loại rủi ro hạn dùng:**\n" .
               "   - **Hàng đã hết hạn (<= 0 ngày):** Niêm phong lập tức, chuyển vào khu vực chờ hủy, không để tại quầy bán.\n" .
               "   - **Cận hạn khẩn cấp (< 30 ngày):** Liên hệ ngay nhà cung cấp để thương lượng đổi trả hoặc thu hồi theo hợp đồng nhập hàng.\n" .
               "   - **Cận hạn cảnh báo (30 - 90 ngày):** Ưu tiên xuất theo nguyên tắc FEFO (First Expired, First Out), có thể lên combo cắt liều hoặc chương trình ưu đãi đẩy nhanh tồn kho.\n\n" .
               "2. **Đề xuất thao tác:**\n" .
               "   - Dược sĩ trưởng phê duyệt biên bản kiểm kê.\n" .
               "   - Cập nhật trạng thái trên hệ thống phần mềm để tránh xuất bán nhầm.\n\n" .
               "---\nLưu ý: *Báo cáo tham khảo chuẩn GSP/GPP nội bộ.*";
    }

    // 4. Quy trình nội bộ SOP
    if (strpos($lower, 'nhập') !== false || strpos($lower, 'lô') !== false) {
        return "📦 **QUY TRÌNH NHẬP KHO & KIỂM TRA LÔ (SOP-01)**\n\n" .
               "1. **Kiểm tra chứng từ:** Đối chiếu số hóa đơn, tên nhà cung cấp, tên thuốc, số đăng ký lưu hành.\n" .
               "2. **Kiểm tra bao bì:** Hộp thuốc nguyên vẹn, có tem nhãn, không móp méo ẩm mốc.\n" .
               "3. **Kiểm tra số lô & HSD:** Ghi nhận số lô (Batch No) và hạn sử dụng (Expiry Date) chính xác vào hệ thống.\n" .
               "4. **Lưu kho:** Vào mục 'Quản lý kho & lô' -> Tạo phiếu nhập hàng -> Lưu thông tin.\n\n" .
               "---\nLưu ý: *Quy trình nội bộ GSP.*";
    }

    if (strpos($lower, 'hủy') !== false || strpos($lower, 'tiêu hủy') !== false) {
        return "🗑️ **QUY TRÌNH XỬ LÝ & TIÊU HỦY THUỐC HỎNG/HẾT HẠN (SOP-03)**\n\n" .
               "1. **Cách ly:** Đưa toàn bộ thuốc vào tủ lưu trữ 'Hàng chờ xử lý/hủy', có biển cảnh báo rõ ràng.\n" .
               "2. **Lập biên bản:** Lập hội đồng hủy thuốc gồm Quản lý nhà thuốc và Dược sĩ phụ trách chuyên môn.\n" .
               "3. **Thủ tục tiêu hủy:** Thực hiện tiêu hủy theo đúng quy định của Bộ Y tế và pháp luật môi trường.\n" .
               "4. **Cập nhật dữ liệu:** Vào mục Kho thuốc xuất giảm trừ tồn kho với lý do 'Xuất hủy thuốc hết hạn'.\n\n" .
               "---\nLưu ý: *Quy trình nội bộ GPP.*";
    }

    if (strpos($lower, 'kiểm kê') !== false) {
        return "📋 **QUY TRÌNH KIỂM KÊ ĐỊNH KỲ (SOP-02)**\n\n" .
               "1. Thực hiện định kỳ vào cuối tháng hoặc đột xuất theo lệnh của Quản trị viên.\n" .
               "2. Đếm số lượng thực tế tại quầy và kho lưu trữ.\n" .
               "3. So sánh với số liệu Xuất - Nhập - Tồn trên phần mềm.\n" .
               "4. Ghi nhận chênh lệch, tìm nguyên nhân và báo cáo Dược sĩ quản lý.\n\n" .
               "---\nLưu ý: *Quy trình nội bộ GPP.*";
    }

    return "**HỆ THỐNG PHẢN HỒI THÔNG TIN**:\n\n" .
           "Vui lòng đặt các câu hỏi liên quan đến:\n" .
           "- 💊 *Tóm tắt thông tin thuốc từ danh mục*\n" .
           "- ⏰ *Phân tích đề xuất xử lý lô thuốc cận hạn/hết hạn*\n" .
           "- 📖 *Quy trình nghiệp vụ SOP (nhập lô, kiểm kê kho, xử lý hủy thuốc...)*\n\n" .
           "Lưu ý: *Lưu ý: Admin có thể cập nhật danh sách nhiều Gemini API Key tại trang Cấu hình AI để kích hoạt cơ chế xoay key tự động khi hết Token.*";
}
