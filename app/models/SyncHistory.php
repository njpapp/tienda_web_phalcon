<?php

namespace App\Models;

use Phalcon\Mvc\Model;
use Phalcon\Validation;
use Phalcon\Validation\Validator\PresenceOf;
use Phalcon\Validation\Validator\InclusionIn;

class SyncHistory extends Model
{
    public $id;
    public $proveedor_id;
    public $tipo_sync;
    public $estado;
    public $productos_procesados;
    public $productos_actualizados;
    public $productos_nuevos;
    public $productos_eliminados;
    public $errores_encontrados;
    public $tiempo_ejecucion;
    public $detalles_error;
    public $estadisticas;
    public $created_at;
    public $completed_at;

    public function initialize()
    {
        $this->setSource('sync_history');
        
        // Relaciones
        $this->belongsTo('proveedor_id', ProveedorDropshipping::class, 'id', [
            'alias' => 'Proveedor'
        ]);
    }

    public function validation()
    {
        $validator = new Validation();

        $validator->add(
            'proveedor_id',
            new PresenceOf([
                'message' => 'El proveedor es requerido'
            ])
        );

        $validator->add(
            'tipo_sync',
            new InclusionIn([
                'domain' => ['productos', 'precios', 'stock', 'pedidos', 'completa'],
                'message' => 'Tipo de sincronización no válido'
            ])
        );

        $validator->add(
            'estado',
            new InclusionIn([
                'domain' => ['iniciado', 'en_progreso', 'completado', 'error', 'cancelado'],
                'message' => 'Estado no válido'
            ])
        );

        return $this->validate($validator);
    }

    public function beforeSave()
    {
        if (is_array($this->estadisticas)) {
            $this->estadisticas = json_encode($this->estadisticas);
        }
    }

    public function afterFetch()
    {
        if (is_string($this->estadisticas)) {
            $this->estadisticas = json_decode($this->estadisticas, true);
        }
    }

    /**
     * Obtiene las estadísticas como array
     */
    public function getEstadisticas()
    {
        if (is_string($this->estadisticas)) {
            return json_decode($this->estadisticas, true) ?: [];
        }
        return $this->estadisticas ?: [];
    }

    /**
     * Calcula la duración en formato legible
     */
    public function getDuracionFormateada()
    {
        if (!$this->tiempo_ejecucion) {
            return 'N/A';
        }

        $segundos = $this->tiempo_ejecucion;
        $horas = floor($segundos / 3600);
        $minutos = floor(($segundos % 3600) / 60);
        $segundos = $segundos % 60;

        if ($horas > 0) {
            return sprintf('%02d:%02d:%02d', $horas, $minutos, $segundos);
        } else {
            return sprintf('%02d:%02d', $minutos, $segundos);
        }
    }

    /**
     * Verifica si la sincronización fue exitosa
     */
    public function fueExitosa()
    {
        return $this->estado === 'completado' && $this->errores_encontrados == 0;
    }

    /**
     * Obtiene el porcentaje de éxito
     */
    public function getPorcentajeExito()
    {
        if ($this->productos_procesados == 0) {
            return 0;
        }

        $exitosos = $this->productos_procesados - $this->errores_encontrados;
        return round(($exitosos / $this->productos_procesados) * 100, 2);
    }

    /**
     * Obtiene historial por proveedor
     */
    public static function getByProveedor($proveedorId, $limite = 10)
    {
        return self::find([
            'conditions' => 'proveedor_id = ?',
            'bind' => [$proveedorId],
            'order' => 'created_at DESC',
            'limit' => $limite
        ]);
    }

    /**
     * Obtiene última sincronización exitosa
     */
    public static function getUltimaExitosa($proveedorId, $tipoSync = null)
    {
        $conditions = ['proveedor_id = ? AND estado = ?'];
        $bind = [$proveedorId, 'completado'];

        if ($tipoSync) {
            $conditions[] = 'tipo_sync = ?';
            $bind[] = $tipoSync;
        }

        return self::findFirst([
            'conditions' => implode(' AND ', $conditions),
            'bind' => $bind,
            'order' => 'completed_at DESC'
        ]);
    }

    /**
     * Obtiene estadísticas generales de sincronización
     */
    public static function getEstadisticasGenerales($dias = 30)
    {
        $db = \Phalcon\Di::getDefault()->getDb();
        
        $sql = "
            SELECT 
                COUNT(*) as total_sincronizaciones,
                COUNT(CASE WHEN estado = 'completado' THEN 1 END) as exitosas,
                COUNT(CASE WHEN estado = 'error' THEN 1 END) as fallidas,
                AVG(tiempo_ejecucion) as tiempo_promedio,
                SUM(productos_procesados) as total_productos_procesados,
                SUM(productos_nuevos) as total_productos_nuevos,
                SUM(productos_actualizados) as total_productos_actualizados
            FROM sync_history 
            WHERE created_at >= CURRENT_DATE - INTERVAL '{$dias} days'
        ";
        
        return $db->fetchOne($sql);
    }

    /**
     * Obtiene sincronizaciones recientes
     */
    public static function getRecientes($limite = 20)
    {
        return self::find([
            'order' => 'created_at DESC',
            'limit' => $limite
        ]);
    }

    /**
     * Limpia registros antiguos
     */
    public static function limpiarAntiguos($dias = 90)
    {
        return self::find([
            'conditions' => 'created_at < ?',
            'bind' => [date('Y-m-d H:i:s', strtotime("-{$dias} days"))]
        ])->delete();
    }
}

/**
 * Modelo para logs de API
 */
class ApiLog extends Model
{
    public $id;
    public $proveedor_id;
    public $endpoint;
    public $metodo;
    public $request_data;
    public $response_data;
    public $status_code;
    public $tiempo_respuesta;
    public $error_message;
    public $created_at;

    public function initialize()
    {
        $this->setSource('api_logs');
        
        $this->belongsTo('proveedor_id', ProveedorDropshipping::class, 'id', [
            'alias' => 'Proveedor'
        ]);
    }

    public function beforeSave()
    {
        if (is_array($this->request_data)) {
            $this->request_data = json_encode($this->request_data);
        }
        
        if (is_array($this->response_data)) {
            $this->response_data = json_encode($this->response_data);
        }
    }

    public function afterFetch()
    {
        if (is_string($this->request_data)) {
            $this->request_data = json_decode($this->request_data, true);
        }
        
        if (is_string($this->response_data)) {
            $this->response_data = json_decode($this->response_data, true);
        }
    }

    /**
     * Obtiene logs por proveedor
     */
    public static function getByProveedor($proveedorId, $limite = 50)
    {
        return self::find([
            'conditions' => 'proveedor_id = ?',
            'bind' => [$proveedorId],
            'order' => 'created_at DESC',
            'limit' => $limite
        ]);
    }

    /**
     * Obtiene logs con errores
     */
    public static function getErrores($limite = 50)
    {
        return self::find([
            'conditions' => 'status_code >= 400 OR error_message IS NOT NULL',
            'order' => 'created_at DESC',
            'limit' => $limite
        ]);
    }

    /**
     * Obtiene estadísticas de API
     */
    public static function getEstadisticas($proveedorId = null, $dias = 7)
    {
        $db = \Phalcon\Di::getDefault()->getDb();
        
        $whereClause = "created_at >= CURRENT_DATE - INTERVAL '{$dias} days'";
        $bind = [];
        
        if ($proveedorId) {
            $whereClause .= " AND proveedor_id = ?";
            $bind[] = $proveedorId;
        }
        
        $sql = "
            SELECT 
                COUNT(*) as total_requests,
                COUNT(CASE WHEN status_code >= 200 AND status_code < 300 THEN 1 END) as exitosos,
                COUNT(CASE WHEN status_code >= 400 OR error_message IS NOT NULL THEN 1 END) as errores,
                AVG(tiempo_respuesta) as tiempo_promedio,
                MAX(tiempo_respuesta) as tiempo_maximo
            FROM api_logs 
            WHERE {$whereClause}
        ";
        
        return $db->fetchOne($sql, $bind);
    }

    /**
     * Limpia logs antiguos
     */
    public static function limpiarAntiguos($dias = 30)
    {
        return self::find([
            'conditions' => 'created_at < ?',
            'bind' => [date('Y-m-d H:i:s', strtotime("-{$dias} days"))]
        ])->delete();
    }
}

/**
 * Modelo para alertas del sistema
 */
class AlertaSistema extends Model
{
    public $id;
    public $tipo;
    public $proveedor_id;
    public $titulo;
    public $mensaje;
    public $nivel;
    public $leida;
    public $datos_adicionales;
    public $created_at;

    public function initialize()
    {
        $this->setSource('alertas_sistema');
        
        $this->belongsTo('proveedor_id', ProveedorDropshipping::class, 'id', [
            'alias' => 'Proveedor'
        ]);
    }

    public function beforeSave()
    {
        if (is_array($this->datos_adicionales)) {
            $this->datos_adicionales = json_encode($this->datos_adicionales);
        }
    }

    public function afterFetch()
    {
        if (is_string($this->datos_adicionales)) {
            $this->datos_adicionales = json_decode($this->datos_adicionales, true);
        }
    }

    /**
     * Marca la alerta como leída
     */
    public function marcarComoLeida()
    {
        $this->leida = true;
        return $this->save();
    }

    /**
     * Obtiene alertas no leídas
     */
    public static function getNoLeidas($limite = 50)
    {
        return self::find([
            'conditions' => 'leida = false',
            'order' => 'created_at DESC',
            'limit' => $limite
        ]);
    }

    /**
     * Obtiene alertas por nivel
     */
    public static function getByNivel($nivel, $limite = 50)
    {
        return self::find([
            'conditions' => 'nivel = ?',
            'bind' => [$nivel],
            'order' => 'created_at DESC',
            'limit' => $limite
        ]);
    }

    /**
     * Obtiene alertas críticas recientes
     */
    public static function getCriticas($horas = 24)
    {
        return self::find([
            'conditions' => 'nivel = ? AND created_at >= ?',
            'bind' => ['critical', date('Y-m-d H:i:s', strtotime("-{$horas} hours"))],
            'order' => 'created_at DESC'
        ]);
    }

    /**
     * Cuenta alertas por tipo
     */
    public static function contarPorTipo($dias = 7)
    {
        $db = \Phalcon\Di::getDefault()->getDb();
        
        return $db->fetchAll("
            SELECT 
                tipo,
                COUNT(*) as cantidad,
                COUNT(CASE WHEN leida = false THEN 1 END) as no_leidas
            FROM alertas_sistema 
            WHERE created_at >= CURRENT_DATE - INTERVAL '{$dias} days'
            GROUP BY tipo
            ORDER BY cantidad DESC
        ");
    }
}

