<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cấu hình tài khoản thanh toán nhận tiền (Dự án MobieX ĐATN)
    |--------------------------------------------------------------------------
    | File cấu hình tập trung chứa thông tin tài khoản Ngân hàng (VietQR)
    | và Ví điện tử MoMo. Có thể cấu hình trực tiếp qua file .env hoặc sửa tại đây.
    | Tuyệt đối không hardcode thông tin tại các file controller/view khác.
    */

    'bank' => [
        // Mã ngân hàng theo chuẩn VietQR (ví dụ: vietcombank, vcb, techcombank, mbbank, icb, acb,...)
        'bank_id' => env('PAYMENT_BANK_ID', 'vietcombank'),

        // Tên ngân hàng đầy đủ hiển thị cho khách hàng
        'bank_name' => env('PAYMENT_BANK_NAME', 'Ngân hàng TMCP Ngoại thương Việt Nam (Vietcombank)'),

        // Số tài khoản người nhận
        'account_number' => env('PAYMENT_BANK_ACCOUNT_NUMBER', '1023034776'),

        // Tên chủ tài khoản người nhận (Viết hoa không dấu hoặc có dấu)
        'account_name' => env('PAYMENT_BANK_ACCOUNT_NAME', 'DINH NGOC HAI'),

        // Kiểu mẫu hiển thị VietQR: 'compact2' (chuẩn đầy đủ), 'compact' (ngắn gọn), 'qr_only' (chỉ mã QR)
        'qr_template' => env('PAYMENT_VIETQR_TEMPLATE', 'compact2'),
    ],

    'momo' => [
        // Số điện thoại đăng ký MoMo nhận tiền
        'phone' => env('PAYMENT_MOMO_PHONE', '0987880968'),

        // Tên chủ ví MoMo nhận tiền
        'name' => env('PAYMENT_MOMO_NAME', ''),

        // Tiền tố nội dung thanh toán MoMo
        'note_prefix' => env('PAYMENT_MOMO_NOTE_PREFIX', 'MobieX '),
    ],
];
