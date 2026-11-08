<?php
/**
 * Checkout: buscar una dirección del cliente y seleccionarla (envío o facturación).
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class JosrapaginacionpedidosAjaxModuleFrontController extends ModuleFrontController
{
    public $auth = true;
    public $ajax = true;
    public $ssl = true;

    public function displayAjax()
    {
        $out = ['ok' => false];
        try {
            if (Tools::getValue('token') !== Tools::getToken(false)) {
                throw new Exception('Token');
            }
            $idCustomer = (int) $this->context->customer->id;
            $action = (string) Tools::getValue('action');
            if ($action === 'search') {
                $q = (string) Tools::getValue('q');
                $rows = Db::getInstance()->executeS('SELECT a.`id_address`, a.`alias`, a.`firstname`, a.`lastname`, a.`company`, a.`address1`, a.`address2`,
                        a.`postcode`, a.`city`, us.`last_used`
                    FROM `' . _DB_PREFIX_ . 'address` a' . Josrapaginacionpedidos::usageJoin($idCustomer) . '
                    WHERE ' . Josrapaginacionpedidos::addressSearchSql($idCustomer, $q) . '
                    ORDER BY us.`last_used` IS NULL, us.`last_used` DESC, a.`date_upd` DESC LIMIT 20');
                $out['results'] = [];
                foreach ((array) $rows as $r) {
                    $out['results'][] = [
                        'id' => (int) $r['id_address'],
                        'alias' => $r['alias'],
                        'line' => Josrapaginacionpedidos::oneLine($r),
                    ];
                }
                $out['ok'] = true;
            } elseif ($action === 'same') {
                // volver a "facturar a la misma dirección de envío"
                $cart = $this->context->cart;
                if (!Validate::isLoadedObject($cart) || !$cart->id_address_delivery) {
                    throw new Exception($this->module->l('No hay dirección de envío.', 'ajax'));
                }
                $cart->id_address_invoice = (int) $cart->id_address_delivery;
                $cart->save();
                Josrapaginacionpedidos::updateCheckoutSession($cart, true);
                $this->context->cookie->__set('josrapp_inv_cart', (int) $cart->id); // no volver a poner la habitual en este carrito
                $this->context->cookie->__set('josrapp_same_cart', (int) $cart->id);
                $out['ok'] = true;
            } elseif ($action === 'select') {
                $id = (int) Tools::getValue('id_address');
                $type = Tools::getValue('type') === 'invoice' ? 'invoice' : 'delivery';
                $cart = $this->context->cart;
                if (!Validate::isLoadedObject($cart) || !Customer::customerHasAddress($idCustomer, $id)) {
                    throw new Exception($this->module->l('Dirección no válida.', 'ajax'));
                }
                $sameBefore = Josrapaginacionpedidos::sessionUsesSameAddress($cart);
                if ($type === 'delivery') {
                    $invoice = (int) $cart->id_address_invoice;
                    // cambia la dirección de los productos del carrito (updateAddressId también tocaría la facturación)
                    $cart->updateAddressId((int) $cart->id_address_delivery, $id);
                    $cart->id_address_invoice = $invoice;
                    $cart->id_address_delivery = $id;
                    // La facturación sigue a la nueva dirección de envío si factura en la misma dirección,
                    // salvo que tengamos su facturación habitual y no haya pedido expresamente "la misma"
                    $explicitSame = (int) $this->context->cookie->__get('josrapp_same_cart') === (int) $cart->id;
                    if ($sameBefore && ($explicitSame || !Configuration::get('JOSRAPP_HABITUAL_INVOICE')
                        || !Josrapaginacionpedidos::habitualInvoice($idCustomer))) {
                        $cart->id_address_invoice = $id;
                    }
                } else {
                    $cart->id_address_invoice = $id;
                    $this->context->cookie->__set('josrapp_same_cart', 0);
                }
                if (!$cart->id_address_invoice) {
                    $cart->id_address_invoice = $id;
                }
                $cart->save();
                Josrapaginacionpedidos::updateCheckoutSession($cart, (int) $cart->id_address_invoice === (int) $cart->id_address_delivery);
                $out['ok'] = true;
            }
        } catch (Exception $e) {
            $out['error'] = $e->getMessage();
        }
        header('Content-Type: application/json; charset=utf-8');
        if (method_exists($this, 'ajaxRender')) {
            $this->ajaxRender(json_encode($out));
        } else {
            echo json_encode($out); // PrestaShop 1.7.0 - 1.7.4
        }
        exit;
    }
}
