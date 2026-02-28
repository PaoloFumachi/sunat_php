<?php
// sunat_php/src/endpoints/emitir.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../services/FacturaService.php';

// ✅ AGREGAR ESTE USE
use Greenter\Model\Response\BillResult;

// Inicializar configuración
SunatConfig::init();

try {
    // 1. Obtener y validar datos JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('No se recibieron datos JSON válidos');
    }
    
    $errors = SunatConfig::validateInput($input);
    if (!empty($errors)) {
        throw new Exception('Datos inválidos: ' . implode(', ', $errors));
    }
    
    // 2. Determinar tipo de comprobante
    $tipoDocumento = SunatConfig::getTipoDocumentoSunat($input['cliente']['tipo_cliente'] ?? 'Persona');
    $tipoComprobante = SunatConfig::getTipoComprobante($input['cliente']['tipo_cliente'] ?? 'Persona');
    
    // 3. Configurar cliente para Greenter
    $input['cliente']['tipo_documento'] = $tipoDocumento;
    
    // 4. Crear comprobante según tipo
    if ($tipoComprobante === '01') {
        $comprobante = FacturaService::crearFactura($input);
    } else {
        $comprobante = FacturaService::crearBoleta($input);
    }
    
    // 5. Enviar a SUNAT
    $see = SunatConfig::getSee('beta');
    $result = $see->send($comprobante);
    
    // 6. Procesar respuesta
    if ($result->isSuccess()) {
        // ✅ En Greenter v5, getCdrResponse() SÍ existe, solo necesita el use
        $cdr = $result->getCdrResponse();
        
        // Guardar XML generado
        $xml = $see->getFactory()->getLastXml();
        $filename = $comprobante->getName() . '.xml';
        
        // Crear directorio si no existe
        $xmlDir = __DIR__ . '/../../storage/xml';
        if (!file_exists($xmlDir)) {
            mkdir($xmlDir, 0777, true);
        }
        
        $xmlPath = $xmlDir . '/' . $filename;
        file_put_contents($xmlPath, $xml);
        
        $response = [
            'success' => true,
            'message' => 'Comprobante emitido correctamente',
            'comprobante' => [
                'tipo' => $tipoComprobante === '01' ? 'FACTURA' : 'BOLETA',
                'serie' => $comprobante->getSerie(),
                'correlativo' => $comprobante->getCorrelativo(),
                'nombre' => $comprobante->getName(),
                'fecha_emision' => date('Y-m-d H:i:s')
            ],
            'sunat' => [
                'code' => $cdr->getCode(),
                'description' => $cdr->getDescription(),
                'notes' => $cdr->getNotes(),
                'hash' => $cdr->getHash(),
                'estado' => 'ACEPTADO'
            ],
            'archivos' => [
                'xml' => $xmlPath,
                'xml_content' => base64_encode($xml)
            ]
        ];
    } else {
        $error = $result->getError();
        $response = [
            'success' => false,
            'message' => 'Error al enviar a SUNAT',
            'sunat' => [
                'code' => $error->getCode(),
                'message' => $error->getMessage(),
                'estado' => 'RECHAZADO'
            ]
        ];
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error en el servidor PHP',
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>