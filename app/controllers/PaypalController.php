<?php

namespace App\Controllers;

use App\Models\Pedido;
use App\Models\EstadoPedido;

class PaypalController extends BaseController
{
    public function initialize()
    {
        parent::initialize();
        
        // Verificar que el usuario es cliente
        if (!$this->requireCliente()) {
            return false;
        }
    }

    /**
     * Crear pago con PayPal
     */
    public function crearPagoAction()
    {
        if (!$this->request->isPost()) {
            return $this->jsonError('Método no permitido', 405);
        }

        $pedidoId = $this->request->getPost('pedido_id', 'int');
        
        if (!$pedidoId) {
            return $this->jsonError('ID de pedido requerido');
        }

        // Verificar que el pedido pertenece al cliente
        $pedido = Pedido::findFirst([
            'conditions' => 'id = ? AND cliente_id = ?',
            'bind' => [$pedidoId, $this->usuario->id]
        ]);

        if (!$pedido) {
            return $this->jsonError('Pedido no encontrado');
        }

        // Verificar que el pedido está en estado pendiente
        if ($pedido->estado->nombre !== 'pendiente') {
            return $this->jsonError('El pedido no puede ser pagado en su estado actual');
        }

        try {
            // Configurar PayPal
            $config = $this->getDI()->getConfig();
            $paypalConfig = $config->paypal;
            
            // Crear el pago con PayPal
            $paymentData = $this->crearPagoPayPal($pedido, $paypalConfig);
            
            if ($paymentData && isset($paymentData['approval_url'])) {
                // Guardar el ID de pago de PayPal en el pedido
                $pedido->paypal_payment_id = $paymentData['payment_id'];
                $pedido->save();
                
                $this->registrarActividad('paypal_payment_created', "Pago PayPal creado para pedido {$pedido->numero_pedido}");
                
                return $this->jsonSuccess('Pago creado exitosamente', [
                    'approval_url' => $paymentData['approval_url'],
                    'payment_id' => $paymentData['payment_id']
                ]);
            } else {
                return $this->jsonError('Error al crear el pago con PayPal');
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Error al crear pago PayPal: ' . $e->getMessage());
            return $this->jsonError('Error interno del servidor');
        }
    }

    /**
     * Procesar respuesta exitosa de PayPal
     */
    public function successAction()
    {
        $paymentId = $this->request->getQuery('paymentId', 'string');
        $payerId = $this->request->getQuery('PayerID', 'string');
        $token = $this->request->getQuery('token', 'string');

        if (!$paymentId || !$payerId) {
            $this->flashSession->error('Parámetros de pago inválidos');
            return $this->response->redirect('/cliente/compras');
        }

        try {
            // Buscar el pedido por payment_id
            $pedido = Pedido::findFirst([
                'conditions' => 'paypal_payment_id = ? AND cliente_id = ?',
                'bind' => [$paymentId, $this->usuario->id]
            ]);

            if (!$pedido) {
                $this->flashSession->error('Pedido no encontrado');
                return $this->response->redirect('/cliente/compras');
            }

            // Ejecutar el pago con PayPal
            $config = $this->getDI()->getConfig();
            $paypalConfig = $config->paypal;
            
            $resultado = $this->ejecutarPagoPayPal($paymentId, $payerId, $paypalConfig);
            
            if ($resultado && $resultado['state'] === 'approved') {
                // Actualizar el estado del pedido
                $estadoPagado = EstadoPedido::obtenerPorNombre('pagado');
                if ($estadoPagado) {
                    $pedido->cambiarEstado($estadoPagado->id, $this->usuario->id);
                }
                
                // Guardar información del pago
                $pedido->paypal_payer_id = $payerId;
                $pedido->paypal_payment_state = $resultado['state'];
                $pedido->fecha_pago = date('Y-m-d H:i:s');
                $pedido->save();
                
                $this->flashSession->success('¡Pago procesado exitosamente!');
                $this->registrarActividad('paypal_payment_completed', "Pago PayPal completado para pedido {$pedido->numero_pedido}");
                
                // Enviar email de confirmación
                $this->enviarEmailConfirmacionPago($pedido);
                
                return $this->response->redirect('/cliente/compras/' . $pedido->id);
                
            } else {
                $this->flashSession->error('Error al procesar el pago');
                $this->logger->error('Error en pago PayPal: ' . json_encode($resultado));
                return $this->response->redirect('/checkout');
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Error al procesar pago PayPal: ' . $e->getMessage());
            $this->flashSession->error('Error interno al procesar el pago');
            return $this->response->redirect('/checkout');
        }
    }

    /**
     * Procesar cancelación de PayPal
     */
    public function cancelAction()
    {
        $this->flashSession->warning('Pago cancelado por el usuario');
        $this->registrarActividad('paypal_payment_cancelled', 'Usuario canceló pago PayPal');
        return $this->response->redirect('/checkout');
    }

    /**
     * Webhook de PayPal para notificaciones IPN
     */
    public function webhookAction()
    {
        // Verificar que la petición viene de PayPal
        $headers = getallheaders();
        $body = file_get_contents('php://input');
        
        // Log de la notificación
        $this->logger->info('PayPal Webhook recibido: ' . $body);
        
        try {
            $data = json_decode($body, true);
            
            if (isset($data['event_type'])) {
                switch ($data['event_type']) {
                    case 'PAYMENT.SALE.COMPLETED':
                        $this->procesarPagoCompletado($data);
                        break;
                        
                    case 'PAYMENT.SALE.DENIED':
                        $this->procesarPagoDenegado($data);
                        break;
                        
                    case 'PAYMENT.SALE.REFUNDED':
                        $this->procesarReembolso($data);
                        break;
                }
            }
            
            // Responder con 200 OK
            $this->response->setStatusCode(200);
            return $this->response->setContent('OK');
            
        } catch (\Exception $e) {
            $this->logger->error('Error procesando webhook PayPal: ' . $e->getMessage());
            $this->response->setStatusCode(500);
            return $this->response->setContent('Error');
        }
    }

    /**
     * Crear pago con PayPal API
     */
    private function crearPagoPayPal($pedido, $config)
    {
        // Simular creación de pago (en producción usar PayPal SDK)
        $paymentData = [
            'payment_id' => 'PAY-' . uniqid(),
            'approval_url' => $config->return_url . '?paymentId=PAY-' . uniqid() . '&PayerID=PAYER123&token=TOKEN123',
            'state' => 'created'
        ];
        
        // En producción, aquí iría la integración real con PayPal SDK:
        /*
        $apiContext = new \PayPal\Rest\ApiContext(
            new \PayPal\Auth\OAuthTokenCredential($config->client_id, $config->secret)
        );
        
        $payer = new \PayPal\Api\Payer();
        $payer->setPaymentMethod('paypal');
        
        $amount = new \PayPal\Api\Amount();
        $amount->setTotal($pedido->total);
        $amount->setCurrency($config->currency);
        
        $transaction = new \PayPal\Api\Transaction();
        $transaction->setAmount($amount);
        $transaction->setDescription("Pedido #{$pedido->numero_pedido}");
        
        $redirectUrls = new \PayPal\Api\RedirectUrls();
        $redirectUrls->setReturnUrl($config->return_url);
        $redirectUrls->setCancelUrl($config->cancel_url);
        
        $payment = new \PayPal\Api\Payment();
        $payment->setIntent('sale');
        $payment->setPayer($payer);
        $payment->setTransactions([$transaction]);
        $payment->setRedirectUrls($redirectUrls);
        
        $payment->create($apiContext);
        */
        
        return $paymentData;
    }

    /**
     * Ejecutar pago con PayPal API
     */
    private function ejecutarPagoPayPal($paymentId, $payerId, $config)
    {
        // Simular ejecución de pago (en producción usar PayPal SDK)
        return [
            'id' => $paymentId,
            'state' => 'approved',
            'payer' => [
                'payer_info' => [
                    'payer_id' => $payerId
                ]
            ]
        ];
        
        // En producción:
        /*
        $apiContext = new \PayPal\Rest\ApiContext(
            new \PayPal\Auth\OAuthTokenCredential($config->client_id, $config->secret)
        );
        
        $payment = \PayPal\Api\Payment::get($paymentId, $apiContext);
        
        $execution = new \PayPal\Api\PaymentExecution();
        $execution->setPayerId($payerId);
        
        $result = $payment->execute($execution, $apiContext);
        return $result->toArray();
        */
    }

    /**
     * Procesar pago completado desde webhook
     */
    private function procesarPagoCompletado($data)
    {
        // Implementar lógica para procesar pago completado
        $this->logger->info('Pago completado vía webhook: ' . json_encode($data));
    }

    /**
     * Procesar pago denegado desde webhook
     */
    private function procesarPagoDenegado($data)
    {
        // Implementar lógica para procesar pago denegado
        $this->logger->info('Pago denegado vía webhook: ' . json_encode($data));
    }

    /**
     * Procesar reembolso desde webhook
     */
    private function procesarReembolso($data)
    {
        // Implementar lógica para procesar reembolso
        $this->logger->info('Reembolso procesado vía webhook: ' . json_encode($data));
    }

    /**
     * Enviar email de confirmación de pago
     */
    private function enviarEmailConfirmacionPago($pedido)
    {
        try {
            // Aquí iría la lógica para enviar email
            // Por ahora solo registramos en el log
            $this->logger->info("Email de confirmación enviado para pedido {$pedido->numero_pedido}");
        } catch (\Exception $e) {
            $this->logger->error('Error enviando email de confirmación: ' . $e->getMessage());
        }
    }
}

