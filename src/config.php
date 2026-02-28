<?php
// sunat_php/src/config.php
require_once __DIR__ . '/../vendor/autoload.php';

use Greenter\Ws\Services\SunatEndpoints;
use Greenter\See;

class SunatConfig {
    // ✅ CORREGIDO: No se puede usar getenv() en propiedades estáticas
    private static $empresa;
    private static $claveSOL;

    // Inicializar con valores por defecto
    public static function init() {
        self::$empresa = [
            'ruc' => '20612278815',
            'razonSocial' => 'GENERAL SERVICE VIÑA E.I.R.L.',
            'nombreComercial' => 'VIÑA',
            'direccion' => [
                'direccion' => 'OTR.PRIMAVERA MZA. 25 LOTE. 1',
                'distrito' => 'CALLERIA',
                'provincia' => 'CORONEL PORTILLO',
                'departamento' => 'UCAYALI',
                'ubigueo' => '250101'
            ]
        ];

        self::$claveSOL = [
            'ruc' => '20612278815',
            'usuario' => 'MODDATOS',
            'clave' => ''
        ];
    }

    public static function getSee($ambiente = 'beta') {
        self::init();
        
        $see = new See();
        
        if ($ambiente === 'produccion') {
            $see->setService(SunatEndpoints::FE_PRODUCCION);
        } else {
            $see->setService(SunatEndpoints::FE_BETA);
        }
        
        $see->setClaveSOL(
            self::$claveSOL['ruc'], 
            self::$claveSOL['usuario'], 
            self::$claveSOL['clave']
        );

        // Certificado si existe (Greenter v5 usa setCertificate con password como segundo parámetro)
        $certPath = __DIR__ . '/../certs/cert.pfx';
        if (file_exists($certPath)) {
            $certContent = file_get_contents($certPath);
            $certPassword = getenv('SUNAT_CERT_PASSWORD') ?: '';
            
            if (!empty($certContent) && !empty($certPassword)) {
                // En Greenter v5, setCertificate acepta (contenido, password)
                $see->setCertificate($certContent, $certPassword);
            }
        }

        return $see;
    }
    
    public static function getEmpresa() {
        self::init();
        return self::$empresa;
    }

    public static function getTipoDocumentoSunat($tipoCliente) {
        $map = ['Bodega' => '6', 'Restaurante' => '6', 'Gimnasio' => '6', 'Empresa' => '6', 'Persona' => '1'];
        return $map[$tipoCliente] ?? '1';
    }

    public static function getTipoComprobante($tipoCliente) {
        $tipoDocumento = self::getTipoDocumentoSunat($tipoCliente);
        return $tipoDocumento === '6' ? '01' : '03';
    }
    
    public static function validateInput($data) {
        $errors = [];
        
        if (empty($data['id_venta'])) {
            $errors[] = 'id_venta es requerido';
        }
        
        if (empty($data['cliente'])) {
            $errors[] = 'cliente es requerido';
        }
        
        if (empty($data['detalles']) || !is_array($data['detalles']) || count($data['detalles']) === 0) {
            $errors[] = 'detalles es requerido y debe ser un array con al menos un elemento';
        }
        
        foreach ($data['detalles'] as $index => $detalle) {
            if (empty($detalle['descripcion'])) {
                $errors[] = "Detalle {$index}: descripcion es requerida";
            }
            if (empty($detalle['cantidad']) || $detalle['cantidad'] <= 0) {
                $errors[] = "Detalle {$index}: cantidad debe ser mayor a 0";
            }
            if (empty($detalle['precio_unitario']) || $detalle['precio_unitario'] <= 0) {
                $errors[] = "Detalle {$index}: precio_unitario debe ser mayor a 0";
            }
        }
        
        if (empty($data['total']) || $data['total'] <= 0) {
            $errors[] = 'total debe ser mayor a 0';
        }
        
        return $errors;
    }
}