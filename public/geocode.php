<?php
// public/geocode.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if (!isset($_GET['q']) || empty(trim($_GET['q']))) {
    http_response_code(400);
    echo json_encode(['error' => 'Query parameter required']);
    exit;
}

$query = trim($_GET['q']);

// Sanitize input
$query = filter_var($query, FILTER_SANITIZE_STRING);
if (!$query) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid query']);
    exit;
}

// Load configuration
$configFile = __DIR__ . '/../config/geocoding.php';
$config = file_exists($configFile) ? include($configFile) : [];

// Google Maps API Key
$googleApiKey = $config['google_maps_api_key'] ?? '';
$settings = $config['settings'] ?? [];

// Database kota-kota besar Indonesia (offline)
function getIndonesianCities() {
    return [
        'jakarta' => [-6.200000, 106.816666, 'Jakarta, DKI Jakarta, Indonesia'],
        'surabaya' => [-7.250000, 112.750000, 'Surabaya, Jawa Timur, Indonesia'],
        'bandung' => [-6.917464, 107.619125, 'Bandung, Jawa Barat, Indonesia'],
        'medan' => [3.595196, 98.672226, 'Medan, Sumatera Utara, Indonesia'],
        'semarang' => [-6.966667, 110.416664, 'Semarang, Jawa Tengah, Indonesia'],
        'makassar' => [-5.135399, 119.423790, 'Makassar, Sulawesi Selatan, Indonesia'],
        'palembang' => [-2.976074, 104.775635, 'Palembang, Sumatera Selatan, Indonesia'],
        'denpasar' => [-8.670458, 115.212629, 'Denpasar, Bali, Indonesia'],
        'yogyakarta' => [-7.797068, 110.370529, 'Yogyakarta, DI Yogyakarta, Indonesia'],
        'yogya' => [-7.797068, 110.370529, 'Yogyakarta, DI Yogyakarta, Indonesia'],
        'malang' => [-7.966620, 112.632632, 'Malang, Jawa Timur, Indonesia'],
        'solo' => [-7.575489, 110.824326, 'Solo, Jawa Tengah, Indonesia'],
        'bekasi' => [-6.238270, 107.001110, 'Bekasi, Jawa Barat, Indonesia'],
        'tangerang' => [-6.178306, 106.631889, 'Tangerang, Banten, Indonesia'],
        'depok' => [-6.400000, 106.818611, 'Depok, Jawa Barat, Indonesia'],
        'bogor' => [-6.595038, 106.816635, 'Bogor, Jawa Barat, Indonesia'],
        'cirebon' => [-6.706189, 108.557457, 'Cirebon, Jawa Barat, Indonesia'],
        'samarinda' => [-0.502106, 117.153709, 'Samarinda, Kalimantan Timur, Indonesia'],
        'balikpapan' => [-1.267803, 116.831040, 'Balikpapan, Kalimantan Timur, Indonesia'],
        'banjarmasin' => [-3.319694, 114.590111, 'Banjarmasin, Kalimantan Selatan, Indonesia'],
        'pontianak' => [-0.026500, 109.342003, 'Pontianak, Kalimantan Barat, Indonesia'],
        'pekanbaru' => [0.533333, 101.450000, 'Pekanbaru, Riau, Indonesia'],
        'jambi' => [-1.609972, 103.607254, 'Jambi, Jambi, Indonesia'],
        'lampung' => [-5.450000, 105.266667, 'Bandar Lampung, Lampung, Indonesia'],
        'padang' => [-0.947000, 100.417000, 'Padang, Sumatera Barat, Indonesia'],
        'manado' => [1.487000, 124.845000, 'Manado, Sulawesi Utara, Indonesia'],
        'kupang' => [-10.178757, 123.597603, 'Kupang, Nusa Tenggara Timur, Indonesia'],
        'batam' => [1.130910, 104.053497, 'Batam, Kepulauan Riau, Indonesia'],
        'pekalongan' => [-6.888611, 109.675278, 'Pekalongan, Jawa Tengah, Indonesia'],
        'tasikmalaya' => [-7.327000, 108.221000, 'Tasikmalaya, Jawa Barat, Indonesia'],
        'purwokerto' => [-7.422000, 109.234000, 'Purwokerto, Jawa Tengah, Indonesia'],
        'tegal' => [-6.869000, 109.140000, 'Tegal, Jawa Tengah, Indonesia'],
        'magelang' => [-7.470000, 110.217000, 'Magelang, Jawa Tengah, Indonesia'],
        'cikarang' => [-6.261000, 107.152000, 'Cikarang, Jawa Barat, Indonesia'],
        'karawang' => [-6.301000, 107.305000, 'Karawang, Jawa Barat, Indonesia'],
        'sukabumi' => [-6.928000, 106.926000, 'Sukabumi, Jawa Barat, Indonesia'],
        'cibinong' => [-6.482000, 106.854000, 'Cibinong, Jawa Barat, Indonesia'],
        'serang' => [-6.118000, 106.150000, 'Serang, Banten, Indonesia'],
        'mataram' => [-8.583000, 116.116000, 'Mataram, Nusa Tenggara Barat, Indonesia'],
        'ambon' => [-3.695000, 128.181000, 'Ambon, Maluku, Indonesia'],
        'jayapura' => [-2.533000, 140.717000, 'Jayapura, Papua, Indonesia'],
        'monas' => [-6.175000, 106.827000, 'Monumen Nasional, Jakarta Pusat, DKI Jakarta, Indonesia'],
        'thamrin' => [-6.195000, 106.823000, 'Jalan M.H. Thamrin, Jakarta, DKI Jakarta, Indonesia'],
        'sudirman' => [-6.208000, 106.823000, 'Jalan Jenderal Sudirman, Jakarta, DKI Jakarta, Indonesia'],
        'grand indonesia' => [-6.195000, 106.823000, 'Grand Indonesia Mall, Jakarta, DKI Jakarta, Indonesia'],
        'kelapa gading' => [-6.158000, 106.910000, 'Kelapa Gading, Jakarta Utara, DKI Jakarta, Indonesia'],
        'mall kelapa gading' => [-6.158000, 106.910000, 'Mall Kelapa Gading, Jakarta Utara, DKI Jakarta, Indonesia'],
        'ancol' => [-6.124000, 106.839000, 'Ancol, Jakarta Utara, DKI Jakarta, Indonesia'],
        'pondok indah' => [-6.266000, 106.784000, 'Pondok Indah, Jakarta Selatan, DKI Jakarta, Indonesia'],
        'senayan' => [-6.225000, 106.802000, 'Senayan, Jakarta Pusat, DKI Jakarta, Indonesia'],
        'menteng' => [-6.195000, 106.830000, 'Menteng, Jakarta Pusat, DKI Jakarta, Indonesia']
    ];
}

// Function to search in offline database
function searchOfflineDatabase($query) {
    $cities = getIndonesianCities();
    $query = strtolower(trim($query));
    
    // Direct match
    if (isset($cities[$query])) {
        $data = $cities[$query];
        return [[
            'lat' => $data[0],
            'lon' => $data[1],
            'display_name' => $data[2],
            'address' => [
                'city' => explode(',', $data[2])[0],
                'state' => explode(',', $data[2])[1] ?? ''
            ],
            'source' => 'offline'
        ]];
    }
    
    // Partial match
    $results = [];
    foreach ($cities as $cityKey => $data) {
        if (strpos($cityKey, $query) !== false || strpos($query, $cityKey) !== false) {
            $results[] = [
                'lat' => $data[0],
                'lon' => $data[1],
                'display_name' => $data[2],
                'address' => [
                    'city' => explode(',', $data[2])[0],
                    'state' => explode(',', $data[2])[1] ?? ''
                ],
                'source' => 'offline'
            ];
        }
    }
    
    return empty($results) ? false : array_slice($results, 0, 3);
}

// Function to try Indonesian Address Provider API (FREE!)
function tryIndonesianAddressAPI($query) {
    // API dari alamat.thecloudalert.com - GRATIS untuk Indonesia
    $searchUrl = 'https://alamat.thecloudalert.com/api/cari/index/?keyword=' . urlencode($query);
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: Afika-Music-Website/1.0',
                'Accept: application/json'
            ],
            'timeout' => 10
        ]
    ]);
    
    $response = @file_get_contents($searchUrl, false, $context);
    
    if ($response === false) {
        return false;
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['status']) || $data['status'] !== 200) {
        return false;
    }
    
    if (empty($data['result'])) {
        return false;
    }
    
    $results = [];
    foreach ($data['result'] as $result) {
        // Format alamat lengkap
        $addressParts = [];
        if (!empty($result['desakel'])) $addressParts[] = $result['desakel'];
        if (!empty($result['kecamatan'])) $addressParts[] = $result['kecamatan'];
        if (!empty($result['kabkota'])) $addressParts[] = $result['kabkota'];
        if (!empty($result['provinsi'])) $addressParts[] = $result['provinsi'];
        
        $fullAddress = implode(', ', $addressParts);
        
        // Estimasi koordinat berdasarkan daerah (akan lebih akurat jika dikombinasi dengan geocoding)
        $estimatedCoords = estimateCoordinatesFromAddress($result);
        
        $results[] = [
            'lat' => $estimatedCoords[0],
            'lon' => $estimatedCoords[1],
            'display_name' => $fullAddress,
            'address' => [
                'village' => $result['desakel'] ?? '',
                'suburb' => $result['kecamatan'] ?? '', 
                'city' => $result['kabkota'] ?? '',
                'state' => $result['provinsi'] ?? '',
                'country' => 'Indonesia'
            ],
            'source' => 'indonesian_address_api'
        ];
    }
    
    return empty($results) ? false : array_slice($results, 0, 5);
}

// Function to estimate coordinates from Indonesian address
function estimateCoordinatesFromAddress($addressData) {
    // Database koordinat estimasi berdasarkan provinsi/kabkota
    $indonesianRegionCoords = [
        // DKI Jakarta
        'jakarta' => [-6.200000, 106.816666],
        'jakarta pusat' => [-6.183333, 106.833333],
        'jakarta utara' => [-6.150000, 106.833333],
        'jakarta selatan' => [-6.250000, 106.833333],
        'jakarta barat' => [-6.183333, 106.783333],
        'jakarta timur' => [-6.183333, 106.883333],
        
        // Jawa Barat
        'bandung' => [-6.917464, 107.619125],
        'kabupaten bandung' => [-6.917464, 107.619125],
        'kota bandung' => [-6.917464, 107.619125],
        'bekasi' => [-6.238270, 107.001110],
        'bogor' => [-6.595038, 106.816635],
        'depok' => [-6.400000, 106.818611],
        'cirebon' => [-6.706189, 108.557457],
        'tasikmalaya' => [-7.327000, 108.221000],
        'sukabumi' => [-6.928000, 106.926000],
        'karawang' => [-6.301000, 107.305000],
        'purwakarta' => [-6.550000, 107.433000],
        
        // Jawa Tengah  
        'semarang' => [-6.966667, 110.416664],
        'solo' => [-7.575489, 110.824326],
        'surakarta' => [-7.575489, 110.824326],
        'yogyakarta' => [-7.797068, 110.370529],
        'magelang' => [-7.470000, 110.217000],
        'purwokerto' => [-7.422000, 109.234000],
        'tegal' => [-6.869000, 109.140000],
        'pekalongan' => [-6.888611, 109.675278],
        
        // Jawa Timur
        'surabaya' => [-7.250000, 112.750000],
        'malang' => [-7.966620, 112.632632],
        'kediri' => [-7.820000, 112.017000],
        'blitar' => [-8.100000, 112.170000],
        'jember' => [-8.170000, 113.700000],
        'banyuwangi' => [-8.220000, 114.370000],
        
        // Banten
        'tangerang' => [-6.178306, 106.631889],
        'serang' => [-6.118000, 106.150000],
        'cilegon' => [-6.003000, 106.019000],
        
        // Sumatera
        'medan' => [3.595196, 98.672226],
        'palembang' => [-2.976074, 104.775635],
        'padang' => [-0.947000, 100.417000],
        'pekanbaru' => [0.533333, 101.450000],
        'jambi' => [-1.609972, 103.607254],
        'bengkulu' => [-3.800000, 102.267000],
        'bandar lampung' => [-5.450000, 105.266667],
        'banda aceh' => [5.550000, 95.317000],
        
        // Kalimantan
        'pontianak' => [-0.026500, 109.342003],
        'banjarmasin' => [-3.319694, 114.590111],
        'samarinda' => [-0.502106, 117.153709],
        'balikpapan' => [-1.267803, 116.831040],
        
        // Sulawesi
        'makassar' => [-5.135399, 119.423790],
        'manado' => [1.487000, 124.845000],
        'kendari' => [-3.980000, 122.515000],
        'palu' => [-0.900000, 119.870000],
        
        // Bali & Nusa Tenggara
        'denpasar' => [-8.670458, 115.212629],
        'mataram' => [-8.583000, 116.116000],
        'kupang' => [-10.178757, 123.597603],
        
        // Maluku & Papua
        'ambon' => [-3.695000, 128.181000],
        'jayapura' => [-2.533000, 140.717000],
        'sorong' => [-0.867000, 131.267000]
    ];
    
    $kabkota = strtolower($addressData['kabkota'] ?? '');
    $provinsi = strtolower($addressData['provinsi'] ?? '');
    
    // Cari berdasarkan kabupaten/kota dulu
    foreach ($indonesianRegionCoords as $region => $coords) {
        if (strpos($kabkota, $region) !== false || strpos($region, $kabkota) !== false) {
            return $coords;
        }
    }
    
    // Jika tidak ketemu, cari berdasarkan provinsi
    foreach ($indonesianRegionCoords as $region => $coords) {
        if (strpos($provinsi, $region) !== false || strpos($region, $provinsi) !== false) {
            return $coords;
        }
    }
    
    // Default ke Jakarta jika tidak ketemu
    return [-6.200000, 106.816666];
}

// Function to try PositionStack (free tier - 25k requests/month)  
function tryPositionStack($query) {
    // PositionStack free API - no credit card required
    // Daftar di: https://positionstack.com/signup/free
    $apiKey = ''; // Kosongkan dulu, bisa diisi nanti
    
    if (empty($apiKey)) {
        return false; // Skip if no API key
    }
    
    $params = [
        'access_key' => $apiKey,
        'query' => $query . ', Indonesia',
        'country' => 'ID',
        'limit' => 5
    ];
    
    $url = 'http://api.positionstack.com/v1/forward?' . http_build_query($params);
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 10
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return false;
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['data']) || empty($data['data'])) {
        return false;
    }
    
    $results = [];
    foreach ($data['data'] as $result) {
        $results[] = [
            'lat' => $result['latitude'],
            'lon' => $result['longitude'],
            'display_name' => $result['label'],
            'address' => [
                'road' => $result['street'] ?? '',
                'city' => $result['locality'] ?? $result['administrative_area'] ?? '',
                'state' => $result['region'] ?? ''
            ],
            'source' => 'positionstack'
        ];
    }
    
    return $results;
}

// Function to try Google Maps Geocoding (backup)
function tryGoogleGeocoding($query, $apiKey = '') {
    $params = [
        'address' => $query . ', Indonesia',
        'region' => 'id',
        'language' => 'id'
    ];
    
    if (!empty($apiKey)) {
        $params['key'] = $apiKey;
    }
    
    $googleUrl = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query($params);
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: Afika-Music-Website/1.0',
                'Accept: application/json'
            ],
            'timeout' => 10
        ]
    ]);
    
    $response = @file_get_contents($googleUrl, false, $context);
    
    if ($response === false) {
        return false;
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['status'])) {
        return false;
    }
    
    if ($data['status'] === 'OK' && !empty($data['results'])) {
        // Convert Google Maps format to our format
        $results = [];
        foreach ($data['results'] as $result) {
            $lat = $result['geometry']['location']['lat'];
            $lng = $result['geometry']['location']['lng'];
            $formatted_address = $result['formatted_address'];
            
            // Parse address components
            $address_components = [];
            foreach ($result['address_components'] as $component) {
                $types = $component['types'];
                if (in_array('route', $types)) {
                    $address_components['road'] = $component['long_name'];
                } elseif (in_array('sublocality_level_1', $types) || in_array('sublocality', $types)) {
                    $address_components['suburb'] = $component['long_name'];
                } elseif (in_array('administrative_area_level_2', $types)) {
                    $address_components['city'] = $component['long_name'];
                } elseif (in_array('administrative_area_level_1', $types)) {
                    $address_components['state'] = $component['long_name'];
                }
            }
            
            $results[] = [
                'lat' => $lat,
                'lon' => $lng,
                'display_name' => $formatted_address,
                'address' => $address_components,
                'source' => 'google'
            ];
        }
        return $results;
    }
    
    return false;
}

// Function to try Nominatim as fallback
function tryNominatim($query) {
    $nominatimUrl = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'format' => 'json',
        'q' => $query,
        'limit' => 3,
        'countrycodes' => 'id',
        'addressdetails' => 1
    ]);
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: Afika-Music-Website/1.0 (Contact: admin@example.com)',
                'Accept: application/json',
                'Accept-Language: id,en;q=0.9'
            ],
            'timeout' => 10
        ]
    ]);
    
    $response = @file_get_contents($nominatimUrl, false, $context);
    
    if ($response === false) {
        return false;
    }
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return false;
    }
    
    // Add source indicator
    foreach ($data as &$result) {
        $result['source'] = 'nominatim';
    }
    
    return $data;
}

// Priority order: Offline Database → Indonesian Address API → PositionStack → Nominatim → Google Maps
$results = false;

// 1. Try offline database first (instant, no internet required)
$results = searchOfflineDatabase($query);

// 2. Try Indonesian Address Provider API (FREE, specialized for Indonesia!)
if ($results === false) {
    $results = tryIndonesianAddressAPI($query);
}

// 3. Try PositionStack (free tier, no credit card required)
if ($results === false) {
    $results = tryPositionStack($query);
}

// 4. Try Nominatim (free but with CORS issues sometimes)
if ($results === false) {
    $results = tryNominatim($query);
}

// 4. Try Google Maps (backup, needs API key)
if ($results === false && !empty($googleApiKey)) {
    $results = tryGoogleGeocoding($query, $googleApiKey);
}

// If all services fail
if ($results === false) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Lokasi tidak ditemukan di semua layanan',
        'suggestion' => 'Coba gunakan nama kota besar (Jakarta, Bandung, Surabaya) atau koordinat manual'
    ]);
    exit;
}

// Return the results
echo json_encode($results);
?>
