<?php
// sunat_php/index.php
header('Content-Type: application/json');

$request = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Simple router
switch (true) {
    case $method === 'POST' && preg_match('#/emitir#', $request):
         // ✅ AHORA USA emitir_fake.php EN VEZ DE emitir.php
        require __DIR__ . '/src/endpoints/emitir_fake.php';
        break;
        
    case $method === 'GET' && preg_match('#/status#', $request):
        echo json_encode([
            'status' => 'online',
            'service' => 'SUNAT PHP Microservice',
            'version' => '1.0',
            'greenter_version' => 'v6.1.0'
        ]);
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint no encontrado']);
        break;
}
?>