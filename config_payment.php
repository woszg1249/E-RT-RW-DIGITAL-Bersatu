<?php
/**
 * Konfigurasi Pembayaran Real-Time & Payment Gateway
 * RT RW Digital Bersatu
 */

return [
    // Mode Pembayaran Default:
    // 'realtime' : Menggunakan mesin QRIS Dinamis internal dengan deteksi real-time & simulasi webhook
    // 'midtrans' : Menggunakan Payment Gateway Midtrans Snap QRIS
    // 'tripay'   : Menggunakan Payment Gateway Tripay QRIS
    'mode' => 'realtime',

    // Pengaturan Midtrans (Opsional jika ingin menggunakan Midtrans sungguhan)
    'midtrans' => [
        'merchant_id'   => '',
        'client_key'    => '',
        'server_key'    => '',
        'is_production' => false,
    ],

    // Pengaturan Tripay (Opsional jika ingin menggunakan Tripay sungguhan)
    'tripay' => [
        'api_key'       => '',
        'private_key'   => '',
        'merchant_code' => '',
        'is_production' => false,
    ]
];
