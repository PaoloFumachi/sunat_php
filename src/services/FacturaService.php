<?php
// sunat_php/src/services/FacturaService.php
require_once __DIR__ . '/../config.php';

use Greenter\Model\Client\Client;
use Greenter\Model\Company\Company;
use Greenter\Model\Company\Address;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\SaleDetail;
use Greenter\Model\Sale\Legend;

class FacturaService {
    
    /**
     * Crear Factura electrónica
     */
    public static function crearFactura($data) {
        $config = SunatConfig::getEmpresa();
        
        // 1. Crear Factura
        $invoice = new Invoice();
        $invoice
            ->setUblVersion('2.1')
            ->setTipoOperacion('0101')
            ->setTipoDoc('01')
            ->setSerie($data['serie'] ?? 'F001')
            ->setCorrelativo($data['correlativo'] ?? str_pad(($data['id_venta'] ?? 1), 8, '0', STR_PAD_LEFT))
            ->setFechaEmision(new DateTime())
            ->setTipoMoneda('PEN')
            ->setCompany(self::getCompany($config))
            ->setClient(self::getClient($data['cliente']));
        
        // 2. Calcular totales
        $mtoOperGravadas = 0;
        $mtoIGV = 0;
        $total = 0;
        $details = [];
        
        foreach ($data['detalles'] as $index => $detalle) {
            $subtotal = floatval($detalle['cantidad']) * floatval($detalle['precio_unitario']);
            $mtoOperGravadas += $subtotal / 1.18;
            $mtoIGV += $subtotal - ($subtotal / 1.18);
            $total += $subtotal;
            
            $details[] = self::createDetail($detalle, $index + 1);
        }
        
        $invoice->setDetails($details);
        $invoice->setMtoOperGravadas($mtoOperGravadas);
        $invoice->setMtoIGV($mtoIGV);
        $invoice->setTotalImpuestos($mtoIGV);
        $invoice->setMtoImpVenta($total);
        
        // 3. Agregar leyenda (total en letras)
        $invoice->setLegends([
            (new Legend())
                ->setCode('1000')
                ->setValue(self::numeroALetras($total))
        ]);
        
        return $invoice;
    }
    
    /**
     * Crear Boleta electrónica
     */
    public static function crearBoleta($data) {
        $config = SunatConfig::getEmpresa();
        
        $invoice = new Invoice();
        $invoice
            ->setUblVersion('2.1')
            ->setTipoOperacion('0101')
            ->setTipoDoc('03')
            ->setSerie($data['serie'] ?? 'B001')
            ->setCorrelativo($data['correlativo'] ?? str_pad(($data['id_venta'] ?? 1), 8, '0', STR_PAD_LEFT))
            ->setFechaEmision(new DateTime())
            ->setTipoMoneda('PEN')
            ->setCompany(self::getCompany($config))
            ->setClient(self::getClient($data['cliente']));
        
        // Calcular totales
        $mtoOperGravadas = 0;
        $mtoIGV = 0;
        $total = 0;
        $details = [];
        
        foreach ($data['detalles'] as $index => $detalle) {
            $subtotal = floatval($detalle['cantidad']) * floatval($detalle['precio_unitario']);
            $mtoOperGravadas += $subtotal / 1.18;
            $mtoIGV += $subtotal - ($subtotal / 1.18);
            $total += $subtotal;
            
            $details[] = self::createDetail($detalle, $index + 1);
        }
        
        $invoice->setDetails($details);
        $invoice->setMtoOperGravadas($mtoOperGravadas);
        $invoice->setMtoIGV($mtoIGV);
        $invoice->setTotalImpuestos($mtoIGV);
        $invoice->setMtoImpVenta($total);
        
        // Leyenda
        $invoice->setLegends([
            (new Legend())
                ->setCode('1000')
                ->setValue(self::numeroALetras($total))
        ]);
        
        return $invoice;
    }
    
    /**
     * Crear empresa emisora
     */
    private static function getCompany($config) {
        return (new Company())
            ->setRuc($config['ruc'])
            ->setRazonSocial($config['razonSocial'])
            ->setNombreComercial($config['nombreComercial'])
            ->setAddress(
                (new Address())
                    ->setUbigueo($config['direccion']['ubigueo'])
                    ->setDepartamento($config['direccion']['departamento'])
                    ->setProvincia($config['direccion']['provincia'])
                    ->setDistrito($config['direccion']['distrito'])
                    ->setUrbanizacion('-')
                    ->setDireccion($config['direccion']['direccion'])
            );
    }
    
    /**
     * Crear cliente
     */
    private static function getClient($clienteData) {
        return (new Client())
            ->setTipoDoc($clienteData['tipo_documento'] ?? '1')
            ->setNumDoc($clienteData['numero_documento'] ?? '00000000')
            ->setRznSocial($clienteData['nombre'] ?? 'CLIENTE GENERICO');
    }
    
    /**
     * Crear detalle de producto
     */
    private static function createDetail($detalle, $itemNumber) {
        $precioUnitario = floatval($detalle['precio_unitario']);
        $cantidad = floatval($detalle['cantidad']);
        $subtotal = $precioUnitario * $cantidad;
        $valorVenta = $subtotal / 1.18;
        
        $detail = new SaleDetail();
        $detail
            ->setCodProducto($detalle['codigo'] ?? 'P' . str_pad($detalle['id_producto'] ?? $itemNumber, 4, '0', STR_PAD_LEFT))
            ->setUnidad('NIU')
            ->setCantidad($cantidad)
            ->setDescripcion($detalle['descripcion'])
            ->setMtoBaseIgv($valorVenta)
            ->setPorcentajeIgv(18.00)
            ->setIgv($subtotal - $valorVenta)
            ->setTipAfeIgv('10')
            ->setTotalImpuestos($subtotal - $valorVenta)
            ->setMtoValorVenta($valorVenta)
            ->setMtoValorUnitario($precioUnitario / 1.18)
            ->setMtoPrecioUnitario($precioUnitario);
        
        return $detail;
    }
    
    /**
     * Convertir número a letras (Perú)
     */
    private static function numeroALetras($numero) {
        $formatter = new NumberFormatter('es_PE', NumberFormatter::SPELLOUT);
        $partes = explode('.', number_format($numero, 2, '.', ''));
        
        $entero = intval($partes[0]);
        $decimal = isset($partes[1]) ? str_pad($partes[1], 2, '0') : '00';
        
        $letras = ucfirst($formatter->format($entero));
        return $letras . ' CON ' . $decimal . '/100 SOLES';
    }
}
?>