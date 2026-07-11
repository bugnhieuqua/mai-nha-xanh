<?php
/**
 * Bảng Điều Khoản & Quy Tắc Nội Bộ — AI Kiểm Duyệt Tự Động
 * AI sẽ đọc file này để đối chiếu nội dung vi phạm trong nhóm chat.
 */

$moderation_rules = [
    'spam' => [
        'name' => 'Spam / Quảng cáo',
        'description' => 'Gửi tin nhắn lặp lại nhiều lần, quảng cáo sản phẩm/dịch vụ không liên quan đến phòng trọ, gửi liên kết rác hoặc mã giới thiệu.',
        'severity' => 'medium',
        'examples' => [
            'Gửi cùng một tin nhắn > 3 lần liên tiếp',
            'Quảng cáo sản phẩm bảo hiểm, MLM, tiền ảo trong nhóm',
            'Gửi link rút gọn đáng ngờ liên tục'
        ]
    ],
    'abuse' => [
        'name' => 'Ngôn ngữ xúc phạm / Quấy rối',
        'description' => 'Sử dụng ngôn ngữ thô tục, xúc phạm, đe doạ, quấy rối hoặc bắt nạt thành viên khác trong nhóm.',
        'severity' => 'high',
        'examples' => [
            'Chửi bới, sỉ nhục cá nhân',
            'Đe doạ bạo lực hoặc gây hại',
            'Quấy rối tình dục, bình luận khiếm nhã về giới tính'
        ]
    ],
    'fraud' => [
        'name' => 'Lừa đảo / Thông tin sai lệch',
        'description' => 'Đăng thông tin phòng trọ sai lệch, lừa đảo tiền cọc, mạo danh chủ trọ hoặc gian lận giao dịch.',
        'severity' => 'critical',
        'examples' => [
            'Đưa ảnh phòng trọ giả, không đúng thực tế',
            'Yêu cầu đặt cọc trước qua chuyển khoản cá nhân rồi biến mất',
            'Giả mạo giấy tờ hợp đồng thuê trọ',
            'Đăng phòng đã cho thuê nhưng vẫn nhận cọc từ nhiều người'
        ]
    ],
    'nsfw' => [
        'name' => 'Nội dung NSFW / Bạo lực',
        'description' => 'Chia sẻ nội dung khiêu dâm, bạo lực, đồ hoạ gây sốc hoặc nội dung không phù hợp với mục đích của nền tảng.',
        'severity' => 'critical',
        'examples' => [
            'Chia sẻ hình ảnh/video khiêu dâm',
            'Nội dung bạo lực cực đoan',
            'Nội dung liên quan đến ma tuý, chất cấm'
        ]
    ],
    'impersonation' => [
        'name' => 'Mạo danh / Lợi dụng hệ thống',
        'description' => 'Mạo danh admin, chủ trọ hoặc nhân viên Mái Nhà Xanh. Lợi dụng hệ thống để gây hại.',
        'severity' => 'high',
        'examples' => [
            'Giả làm admin để yêu cầu thông tin cá nhân',
            'Mạo danh chủ trọ để nhận tiền cọc',
            'Tạo nhóm giả mạo dịch vụ của Mái Nhà Xanh'
        ]
    ],
    'privacy' => [
        'name' => 'Vi phạm quyền riêng tư',
        'description' => 'Chia sẻ thông tin cá nhân của người khác mà không có sự đồng ý (số CMND, địa chỉ riêng, ảnh cá nhân).',
        'severity' => 'high',
        'examples' => [
            'Đăng số CMND/CCCD của người khác',
            'Chia sẻ ảnh cá nhân của thành viên không đồng ý',
            'Công khai thông tin tài khoản ngân hàng của người khác'
        ]
    ]
];

/**
 * Hàm tạo AI prompt từ bảng điều khoản
 * @return string Prompt text cho AI đọc và đối chiếu
 */
function buildModerationPrompt(): string {
    global $moderation_rules;
    
    $prompt = "=== BẢNG ĐIỀU KHOẢN SỬ DỤNG MÁI NHÀ XANH ===\n\n";
    $prompt .= "Bạn là AI Kiểm duyệt viên của nền tảng Mái Nhà Xanh (hệ thống quản lý phòng trọ).\n";
    $prompt .= "Nhiệm vụ: Phân tích nội dung được gửi đến và xác định xem nó có vi phạm các điều khoản dưới đây hay không.\n\n";
    
    $prompt .= "CÁC QUY TẮC VI PHẠM:\n\n";
    
    foreach ($moderation_rules as $key => $rule) {
        $prompt .= "[$key] {$rule['name']} (Mức độ: {$rule['severity']})\n";
        $prompt .= "  Mô tả: {$rule['description']}\n";
        $prompt .= "  Ví dụ vi phạm:\n";
        foreach ($rule['examples'] as $ex) {
            $prompt .= "    - $ex\n";
        }
        $prompt .= "\n";
    }
    
    $prompt .= "\n=== HƯỚNG DẪN PHẢN HỒI ===\n";
    $prompt .= "Trả lời ĐÚNG định dạng JSON sau (không kèm markdown):\n";
    $prompt .= '{"is_violation":true/false,"severity":"low|medium|high|critical","matched_rule":"rule_key hoặc null","reason":"Giải thích ngắn gọn tiếng Việt","confidence":0.0-1.0}' . "\n";
    $prompt .= "\nQuy tắc phân loại severity:\n";
    $prompt .= "- low: Hơi khó chịu nhưng không rõ ràng vi phạm\n";
    $prompt .= "- medium: Vi phạm nhẹ, cần cảnh cáo\n";
    $prompt .= "- high: Vi phạm nghiêm trọng, cần khoá nhóm\n";
    $prompt .= "- critical: Vi phạm cực kỳ nghiêm trọng, khoá nhóm ngay lập tức\n";
    
    return $prompt;
}

return $moderation_rules;
?>
