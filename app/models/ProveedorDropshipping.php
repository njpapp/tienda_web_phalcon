<?php

namespace App\Models;

use Phalcon\Mvc\Model;
use Phalcon\Validation;
use Phalcon\Validation\Validator\PresenceOf;
use Phalcon\Validation\Validator\Uniqueness;
use Phalcon\Validation\Validator\InclusionIn;

class ProveedorDropshipping extends Model
{
    public $id;
    public $nombre;
    public $tipo;
    public $api_key;
    public $api_secret;
    public $configuracion;
    public $activo;
    public $limite_requests_dia;
    public $requests_realizados_hoy;
    public $ultima_sincronizacion;
    public $created_at;
    public $updated_at;

    public function initialize()
    {
        $this->setSource('proveedores_dropshipping');
        
        // Relaciones
        $this->hasMany('id', ProductoExterno::class, 'proveedor_id', [
            'alias' => 'ProductosExternos'
        ]);
        
        $this->hasMany('id', SyncHistory::class, 'proveedor_id', [
            'alias' => 'HistorialSync'
        ]);
        
        $this->hasMany('id', PedidoDropshipping::class, 'proveedor_id', [
            'alias' => 'PedidosDropshipping'
        ]);
    }

    public function validation()
    {
        $validator = new Validation();

        $validator->add(
            'nombre',
            new PresenceOf([
                'message' => 'El nombre del proveedor es requerido'
            ])
        );

        $validator->add(
            'tipo',
            new PresenceOf([
                'message' => 'El tipo de proveedor es requerido'
            ])
        );

        $validator->add(
            'tipo',
            new InclusionIn([
                'domain' => ['aliexpress', 'amazon', 'cj_dropshipping', 'spocket', 'doba'],
                'message' => 'Tipo de proveedor no válido'
            ])
        );

        return $this->validate($validator);
    }

    public function beforeSave()
    {
        if (is_array($this->configuracion)) {
            $this->configuracion = json_encode($this->configuracion);
        }
    }

    public function afterFetch()
    {
        if (is_string($this->configuracion)) {
            $this->configuracion = json_decode($this->configuracion, true);
        }
    }

    /**
     * Obtiene la configuración como array
     */
    public function getConfiguracion()
    {
        if (is_string($this->configuracion)) {
            return json_decode($this->configuracion, true) ?: [];
        }
        return $this->configuracion ?: [];
    }

    /**
     * Establece la configuración
     */
    public function setConfiguracion($config)
    {
        $this->configuracion = is_array($config) ? $config : [];
    }

    /**
     * Verifica si el proveedor puede hacer más requests hoy
     */
    public function puedeHacerRequest()
    {
        return $this->activo && $this->requests_realizados_hoy < $this->limite_requests_dia;
    }

    /**
     * Incrementa el contador de requests
     */
    public function incrementarRequests()
    {
        $this->requests_realizados_hoy++;
        return $this->save();
    }

    /**
     * Obtiene el adaptador correspondiente al tipo de proveedor
     */
    public function getAdapter()
    {
        $adapterClass = 'App\\Library\\Dropshipping\\' . ucfirst($this->tipo) . 'Adapter';
        
        if (class_exists($adapterClass)) {
            return new $adapterClass($this);
        }
        
        throw new \Exception("Adaptador no encontrado para el proveedor: {$this->tipo}");
    }

    /**
     * Obtiene estadísticas del proveedor
     */
    public function getEstadisticas()
    {
        $stats = [];
        
        // Total de productos
        $stats['total_productos'] = ProductoExterno::count([
            'proveedor_id = ?',
            'bind' => [$this->id]
        ]);
        
        // Productos disponibles
        $stats['productos_disponibles'] = ProductoExterno::count([
            'proveedor_id = ? AND disponible = true',
            'bind' => [$this->id]
        ]);
        
        // Última sincronización
        $ultimaSync = SyncHistory::findFirst([
            'conditions' => 'proveedor_id = ? AND estado = ?',
            'bind' => [$this->id, 'completado'],
            'order' => 'created_at DESC'
        ]);
        
        $stats['ultima_sincronizacion'] = $ultimaSync ? $ultimaSync->created_at : null;
        
        // Pedidos del mes
        $stats['pedidos_mes'] = PedidoDropshipping::count([
            'proveedor_id = ? AND created_at >= ?',
            'bind' => [$this->id, date('Y-m-01')]
        ]);
        
        return $stats;
    }

    /**
     * Resetea el contador de requests diarios
     */
    public function resetearContadorDiario()
    {
        $this->requests_realizados_hoy = 0;
        return $this->save();
    }

    /**
     * Actualiza la fecha de última sincronización
     */
    public function actualizarUltimaSincronizacion()
    {
        $this->ultima_sincronizacion = date('Y-m-d H:i:s');
        return $this->save();
    }

    /**
     * Obtiene proveedores activos
     */
    public static function getProveedoresActivos()
    {
        return self::find([
            'conditions' => 'activo = true',
            'order' => 'nombre ASC'
        ]);
    }

    /**
     * Obtiene proveedor por tipo
     */
    public static function getByTipo($tipo)
    {
        return self::findFirst([
            'conditions' => 'tipo = ? AND activo = true',
            'bind' => [$tipo]
        ]);
    }
}

