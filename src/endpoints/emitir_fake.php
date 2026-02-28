<?php
// sunat_php/src/endpoints/emitir_fake.php (para pruebas)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('No se recibieron datos JSON válidos');
    }
    
    $errors = SunatConfig::validateInput($input);
    if (!empty($errors)) {
        throw new Exception('Datos inválidos: ' . implode(', ', $errors));
    }
    // Verificar qué tipo de comprobante se solicitó
$tipoSolicitado = $input['tipo'] ?? 'FACTURA'; // Valor por defecto del Node.js
    // Respuesta simulada de SUNAT BETA
    $response = [
           'success' => true,
    'message' => 'Comprobante emitido correctamente',
    'comprobante' => [
        'tipo' => $tipoSolicitado, // ← USAR EL TIPO SOLICITADO
        'serie' => $tipoSolicitado === 'BOLETA' ? 'B001' : 'F001',
        'correlativo' => $input['correlativo'] ?? '00000001',
        'nombre' => ($tipoSolicitado === 'BOLETA' ? 'B001' : 'F001') . '-' . ($input['correlativo'] ?? '00000001'),
        'fecha_emision' => date('Y-m-d H:i:s')
    ],
        'sunat' => [
            'code' => '0',
            'description' => 'La Factura ha sido aceptada',
            'notes' => ['Generado en modo desarrollo'],
            'hash' => 'dev_' . md5(time() . $input['id_venta']),
            'estado' => 'ACEPTADO'
        ],
        'archivos' => [
            'xml' => '/storage/xml/F001-00000001.xml',
            'xml_content' => base64_encode('<?xml version="1.0"?><invoice>XML SIMULADO</invoice>')
        ]
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error en el servidor PHP',
        'error' => $e->getMessage()
    ]);
}
?>