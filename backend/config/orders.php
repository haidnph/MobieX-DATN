<?php

return [
    // Unpaid online orders hold inventory and coupon capacity for this long.
    'reservation_expiration_minutes' => (int) env('ORDER_RESERVATION_EXPIRATION_MINUTES', 30),
];
