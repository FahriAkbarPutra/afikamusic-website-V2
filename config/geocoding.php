<?php
// config/geocoding.php
// Konfigurasi untuk Google Maps Geocoding API

return [
    // Google Maps API Key
    // Dapatkan dari: https://console.cloud.google.com/apis/credentials
    // Enable: Geocoding API
    'google_maps_api_key' => '', // Isi dengan API key Anda
    
    // Pengaturan Geocoding
    'settings' => [
        'default_region' => 'id', // Indonesia
        'default_language' => 'id', // Bahasa Indonesia
        'max_results' => 5,
        'timeout' => 10, // seconds
        
        // Prioritas service (1 = tertinggi)
        'service_priority' => [
            'google' => 1,
            'nominatim' => 2
        ]
    ]
];
?>

