<?php
/**
 * Paginación de pedidos y direcciones para clientes con miles de pedidos/direcciones.
 *
 *  - Historial de pedidos paginado (evita el "Allowed memory size exhausted" de la página de historial).
 *  - Mis direcciones paginadas, con buscador y vista compacta en tabla.
 *  - Checkout: lista corta de direcciones + buscador, y dirección de facturación habitual preseleccionada.
 *
 * Sin overrides. PrestaShop 1.7, 8 y 9. Usa las plantillas del propio tema (vale para cualquier tema).
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class Josrapaginacionpedidos extends Module
{
    public function __construct()
    {
        $this->name = 'josrapaginacionpedidos';
        $this->tab = 'front_office_features';
        $this->version = '1.0.2';
        $this->author = 'Josra';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Paginación de pedidos y direcciones');
        $this->description = $this->l('Historial de pedidos y direcciones paginados, y checkout ágil para clientes con miles de pedidos o direcciones.');
        $this->ps_versions_compliancy = ['min' => '1.7.0.0', 'max' => _PS_VERSION_];
    }

    public static function defaults()
    {
        return [
            'JOSRAPP_HISTORY' => 1,
            'JOSRAPP_HISTORY_PER_PAGE' => 20,
            'JOSRAPP_ADDRESSES' => 1,
            'JOSRAPP_ADDRESSES_PER_PAGE' => 20,
            'JOSRAPP_ADDRESSES_VIEW' => 'table',
            'JOSRAPP_CHECKOUT' => 1,
            'JOSRAPP_CHECKOUT_MIN' => 10,
            'JOSRAPP_CHECKOUT_RECENT' => 5,
            'JOSRAPP_HABITUAL_INVOICE' => 1,
            'JOSRAPP_FOLD_INVOICE' => 1,
        ];
    }

    public function install()
    {
        foreach (self::defaults() as $k => $v) {
            if (Configuration::get($k) === false) {
                Configuration::updateValue($k, $v);
            }
        }

        return parent::install() && $this->registerHook([
            'actionFrontControllerSetMedia',
            'actionFrontControllerSetVariables',
            'moduleRoutes',
        ]);
    }

    public function uninstall()
    {
        foreach (array_keys(self::defaults()) as $k) {
            Configuration::deleteByName($k);
        }

        return parent::uninstall();
    }

    /* ------------------------------------------------------------------ */
    /*  URLs propias                                                       */
    /* ------------------------------------------------------------------ */

    public function hookModuleRoutes()
    {
        return [
            'module-josrapaginacionpedidos-history' => [
                'controller' => 'history',
                'rule' => 'mis-pedidos',
                'keywords' => [],
                'params' => ['fc' => 'module', 'module' => $this->name, 'controller' => 'history'],
            ],
            'module-josrapaginacionpedidos-addresses' => [
                'controller' => 'addresses',
                'rule' => 'mis-direcciones',
                'keywords' => [],
                'params' => ['fc' => 'module', 'module' => $this->name, 'controller' => 'addresses'],
            ],
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Tienda                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Antes de que PrestaShop cargue nada pesado:
     *  - historial y direcciones -> nuestras páginas paginadas
     *  - checkout -> preselecciona la facturación habitual y carga el buscador
     */
    public function hookActionFrontControllerSetMedia($params)
    {
        $controller = isset($this->context->controller->php_self) ? $this->context->controller->php_self : '';
        if (!$this->context->customer || !$this->context->customer->isLogged()) {
            return;
        }
        if ($controller === 'history' && Configuration::get('JOSRAPP_HISTORY')) {
            Tools::redirect($this->context->link->getModuleLink($this->name, 'history', [], true));
        }
        if ($controller === 'addresses' && Configuration::get('JOSRAPP_ADDRESSES')) {
            Tools::redirect($this->context->link->getModuleLink($this->name, 'addresses', [], true));
        }
        if (($controller === 'order' || $controller === 'checkout') && Configuration::get('JOSRAPP_CHECKOUT')) {
            $this->checkoutMedia();
        }
    }

    /** Las URLs del tema (Mi cuenta, menú...) ya apuntan a nuestras páginas, sin pasar por la redirección */
    public function hookActionFrontControllerSetVariables($params)
    {
        if (!isset($params['templateVars']) || !is_array($params['templateVars'])) {
            return;
        }
        $vars = &$params['templateVars'];
        if (isset($vars['urls']['pages'])) {
            if (Configuration::get('JOSRAPP_HISTORY')) {
                $vars['urls']['pages']['history'] = $this->context->link->getModuleLink($this->name, 'history', [], true);
            }
            if (Configuration::get('JOSRAPP_ADDRESSES')) {
                $vars['urls']['pages']['addresses'] = $this->context->link->getModuleLink($this->name, 'addresses', [], true);
            }
        }
        $controller = isset($this->context->controller->php_self) ? $this->context->controller->php_self : '';
        if (($controller === 'order' || $controller === 'checkout') && Configuration::get('JOSRAPP_CHECKOUT')
            && isset($vars['customer']['addresses']) && is_array($vars['customer']['addresses'])) {
            $total = count($vars['customer']['addresses']);
            if ($total >= (int) Configuration::get('JOSRAPP_CHECKOUT_MIN')) {
                $keep = $this->checkoutShortList((int) $this->context->customer->id);
                $short = [];
                foreach ($keep as $id) {
                    if (isset($vars['customer']['addresses'][$id])) {
                        $short[$id] = $vars['customer']['addresses'][$id];
                    }
                }
                if ($short) {
                    $vars['customer']['addresses'] = $short;
                }
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Checkout                                                           */
    /* ------------------------------------------------------------------ */

    public static function countAddresses($idCustomer)
    {
        return (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'address`
            WHERE `id_customer` = ' . (int) $idCustomer . ' AND `deleted` = 0');
    }

    /** Dirección de facturación más usada (y que siga existiendo) */
    public static function habitualInvoice($idCustomer)
    {
        return (int) Db::getInstance()->getValue('SELECT o.`id_address_invoice` FROM `' . _DB_PREFIX_ . 'orders` o
            INNER JOIN `' . _DB_PREFIX_ . 'address` a ON (a.`id_address` = o.`id_address_invoice` AND a.`deleted` = 0)
            WHERE o.`id_customer` = ' . (int) $idCustomer . '
            GROUP BY o.`id_address_invoice` ORDER BY COUNT(*) DESC, MAX(o.`date_add`) DESC');
    }

    /** Ids a mostrar en el checkout: seleccionadas, facturación habitual y las últimas usadas */
    public function checkoutShortList($idCustomer)
    {
        $cart = $this->context->cart;
        $ids = [];
        if (Validate::isLoadedObject($cart)) {
            $ids[] = (int) $cart->id_address_delivery;
            $ids[] = (int) $cart->id_address_invoice;
        }
        if (Configuration::get('JOSRAPP_HABITUAL_INVOICE')) {
            $ids[] = self::habitualInvoice($idCustomer);
        }
        $n = max(1, (int) Configuration::get('JOSRAPP_CHECKOUT_RECENT'));
        foreach (self::recentAddresses($idCustomer, $n + 3) as $id) {
            if (count(array_unique(array_filter($ids))) >= $n + 3) {
                break;
            }
            $ids[] = (int) $id;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /** Últimas direcciones usadas en pedidos y, si faltan, las últimas creadas o modificadas */
    public static function recentAddresses($idCustomer, $limit)
    {
        $ids = [];
        $rows = Db::getInstance()->executeS('SELECT u.`id_address` FROM (
                SELECT o.`id_address_delivery` AS id_address, o.`date_add` FROM `' . _DB_PREFIX_ . 'orders` o WHERE o.`id_customer` = ' . (int) $idCustomer . '
                UNION ALL
                SELECT o.`id_address_invoice`, o.`date_add` FROM `' . _DB_PREFIX_ . 'orders` o WHERE o.`id_customer` = ' . (int) $idCustomer . '
            ) u INNER JOIN `' . _DB_PREFIX_ . 'address` a ON (a.`id_address` = u.`id_address` AND a.`deleted` = 0)
            GROUP BY u.`id_address` ORDER BY MAX(u.`date_add`) DESC LIMIT ' . (int) $limit);
        foreach ((array) $rows as $r) {
            $ids[] = (int) $r['id_address'];
        }
        if (count($ids) < $limit) {
            foreach ((array) Db::getInstance()->executeS('SELECT `id_address` FROM `' . _DB_PREFIX_ . 'address`
                WHERE `id_customer` = ' . (int) $idCustomer . ' AND `deleted` = 0' . ($ids ? ' AND `id_address` NOT IN (' . implode(',', $ids) . ')' : '') . '
                ORDER BY `date_upd` DESC LIMIT ' . (int) ($limit - count($ids))) as $r) {
                $ids[] = (int) $r['id_address'];
            }
        }

        return $ids;
    }

    protected function checkoutMedia()
    {
        $customer = $this->context->customer;
        $cart = $this->context->cart;
        if ($customer->isGuest() || !Validate::isLoadedObject($cart)) {
            return;
        }
        $total = self::countAddresses($customer->id);
        if ($total < (int) Configuration::get('JOSRAPP_CHECKOUT_MIN')) {
            return;
        }
        // Facturación habitual: una vez por carrito, para no deshacer lo que elija el cliente después
        if (Configuration::get('JOSRAPP_HABITUAL_INVOICE') && !Tools::getValue('ajax')
            && (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] === 'GET')) {
            $applied = (int) $this->context->cookie->__get('josrapp_inv_cart');
            $habitual = self::habitualInvoice($customer->id);
            if ($applied !== (int) $cart->id && $habitual && $cart->id_address_delivery
                && (int) $cart->id_address_invoice !== $habitual) {
                $cart->id_address_invoice = $habitual;
                $cart->save();
                self::updateCheckoutSession($cart, (int) $cart->id_address_invoice === (int) $cart->id_address_delivery);
                $this->context->cookie->__set('josrapp_inv_cart', (int) $cart->id);
            } elseif ($applied !== (int) $cart->id) {
                $this->context->cookie->__set('josrapp_inv_cart', (int) $cart->id);
            }
        }

        Media::addJsDef(['josrapp' => [
            'ajax' => $this->context->link->getModuleLink($this->name, 'ajax', [], true),
            'token' => Tools::getToken(false),
            'total' => $total,
            'fold' => (bool) Configuration::get('JOSRAPP_FOLD_INVOICE'),
            'orderUrl' => $this->context->link->getPageLink('order', true),
            't' => [
                'searchDelivery' => $this->l('Buscar otra dirección de envío (nombre, empresa, calle, ciudad, CP...)'),
                'searchInvoice' => $this->l('Buscar otra dirección de facturación'),
                'showing' => sprintf($this->l('Se muestran las direcciones recientes. Tienes %d en total: usa el buscador para encontrar cualquier otra.'), $total),
                'noResults' => $this->l('No hay ninguna dirección que coincida.'),
                'searching' => $this->l('Buscando…'),
                'change' => $this->l('Cambiar dirección de facturación'),
                'same' => $this->l('Facturar a la misma dirección de envío'),
                'select' => $this->l('Usar esta'),
                'saving' => $this->l('Guardando…'),
            ],
        ]]);
        $this->context->controller->registerJavascript('modules-josrapp-checkout', 'modules/' . $this->name . '/views/js/checkout.js', ['position' => 'bottom', 'priority' => 200]);
        $this->context->controller->registerStylesheet('modules-josrapp-front', 'modules/' . $this->name . '/views/css/front.css', ['media' => 'all', 'priority' => 200]);
    }

    /** ¿El checkout está en modo "usar la misma dirección para facturar"? (por defecto sí) */
    public static function sessionUsesSameAddress(Cart $cart)
    {
        $raw = Db::getInstance()->getValue('SELECT `checkout_session_data` FROM `' . _DB_PREFIX_ . 'cart` WHERE `id_cart` = ' . (int) $cart->id);
        $data = json_decode((string) $raw, true);
        if (isset($data['checkout-addresses-step']['use_same_address'])) {
            return (bool) $data['checkout-addresses-step']['use_same_address'];
        }

        return (int) $cart->id_address_invoice === (int) $cart->id_address_delivery;
    }

    /**
     * Mantiene coherente la sesión del checkout tras cambiar direcciones del carrito:
     * "usar la misma dirección para facturar", pasos siguientes por rehacer y la suma de control del carrito.
     */
    public static function updateCheckoutSession(Cart $cart, $useSame)
    {
        $raw = Db::getInstance()->getValue('SELECT `checkout_session_data` FROM `' . _DB_PREFIX_ . 'cart` WHERE `id_cart` = ' . (int) $cart->id);
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            $data = [];
        }
        $found = false;
        foreach ($data as $key => &$step) {
            if (!is_array($step)) {
                continue;
            }
            if ($key === 'checkout-addresses-step') {
                $found = true;
                $step['use_same_address'] = (bool) $useSame;
                $step['step_is_complete'] = false; // el cliente vuelve al paso de direcciones para revisarlas y pulsar «Continuar»
                $step['step_is_reachable'] = true;
            }
            if ($found && $key !== 'checkout-addresses-step') {
                $step['step_is_complete'] = false; // transporte y pago se vuelven a confirmar
            }
        }
        unset($step);
        if (!isset($data['checkout-addresses-step'])) {
            $data['checkout-addresses-step'] = ['step_is_reachable' => true, 'step_is_complete' => false, 'use_same_address' => (bool) $useSame];
        }
        if (class_exists('CartChecksum') && class_exists('AddressChecksum')) {
            $checksum = new CartChecksum(new AddressChecksum());
            $data['checksum'] = $checksum->generateChecksum($cart);
        }
        Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'cart` SET `checkout_session_data` = \'' . pSQL(json_encode($data)) . '\'
            WHERE `id_cart` = ' . (int) $cart->id);
    }

    /* ------------------------------------------------------------------ */
    /*  Direcciones: consultas comunes                                     */
    /* ------------------------------------------------------------------ */

    public static function addressSearchSql($idCustomer, $q)
    {
        $w = 'a.`id_customer` = ' . (int) $idCustomer . ' AND a.`deleted` = 0';
        $q = trim((string) $q);
        if ($q !== '') {
            foreach (preg_split('/\s+/', $q) as $word) {
                $like = pSQL(str_replace(['%', '_'], ['\%', '\_'], $word));
                $w .= ' AND CONCAT_WS(\' \', a.`alias`, a.`firstname`, a.`lastname`, a.`company`, a.`address1`, a.`address2`,
                    a.`postcode`, a.`city`, a.`phone`, a.`phone_mobile`, a.`dni`, a.`vat_number`) LIKE \'%' . $like . '%\'';
            }
        }

        return $w;
    }

    /** Uso de cada dirección del cliente en pedidos (para ordenar por "última usada") */
    public static function usageJoin($idCustomer)
    {
        return ' LEFT JOIN (SELECT u.`id_address`, COUNT(DISTINCT u.`id_order`) AS n_orders, MAX(u.`date_add`) AS last_used FROM (
                SELECT o.`id_order`, o.`id_address_delivery` AS id_address, o.`date_add` FROM `' . _DB_PREFIX_ . 'orders` o WHERE o.`id_customer` = ' . (int) $idCustomer . '
                UNION ALL
                SELECT o.`id_order`, o.`id_address_invoice`, o.`date_add` FROM `' . _DB_PREFIX_ . 'orders` o WHERE o.`id_customer` = ' . (int) $idCustomer . '
            ) u GROUP BY u.`id_address`) us ON (us.`id_address` = a.`id_address`)';
    }

    /** Una línea con la dirección: "Alias — Nombre / Empresa, calle, CP ciudad" */
    public static function oneLine(array $a)
    {
        $name = trim($a['firstname'] . ' ' . $a['lastname']);
        $parts = array_filter([
            $a['company'] ? $a['company'] . ($name ? ' (' . $name . ')' : '') : $name,
            trim($a['address1'] . ' ' . $a['address2']),
            trim($a['postcode'] . ' ' . $a['city']),
        ]);

        return implode(', ', $parts);
    }

    /* ------------------------------------------------------------------ */
    /*  Configuración                                                      */
    /* ------------------------------------------------------------------ */

    public function getContent()
    {
        $html = '';
        if (Tools::isSubmit('submitJosrapp')) {
            foreach (['JOSRAPP_HISTORY', 'JOSRAPP_ADDRESSES', 'JOSRAPP_CHECKOUT', 'JOSRAPP_HABITUAL_INVOICE', 'JOSRAPP_FOLD_INVOICE'] as $k) {
                Configuration::updateValue($k, (int) Tools::getValue($k));
            }
            Configuration::updateValue('JOSRAPP_HISTORY_PER_PAGE', max(5, min(200, (int) Tools::getValue('JOSRAPP_HISTORY_PER_PAGE'))));
            Configuration::updateValue('JOSRAPP_ADDRESSES_PER_PAGE', max(5, min(200, (int) Tools::getValue('JOSRAPP_ADDRESSES_PER_PAGE'))));
            Configuration::updateValue('JOSRAPP_ADDRESSES_VIEW', Tools::getValue('JOSRAPP_ADDRESSES_VIEW') === 'cards' ? 'cards' : 'table');
            Configuration::updateValue('JOSRAPP_CHECKOUT_MIN', max(2, (int) Tools::getValue('JOSRAPP_CHECKOUT_MIN')));
            Configuration::updateValue('JOSRAPP_CHECKOUT_RECENT', max(1, min(50, (int) Tools::getValue('JOSRAPP_CHECKOUT_RECENT'))));
            $html .= $this->displayConfirmation($this->l('Configuración guardada.'));
        }

        return $html . $this->renderForm();
    }

    protected function switchField($name, $label, $desc)
    {
        return [
            'type' => 'switch',
            'label' => $label,
            'name' => $name,
            'is_bool' => true,
            'desc' => $desc,
            'values' => [
                ['id' => $name . '_on', 'value' => 1, 'label' => $this->l('Sí')],
                ['id' => $name . '_off', 'value' => 0, 'label' => $this->l('No')],
            ],
        ];
    }

    protected function renderForm()
    {
        $perPage = [];
        foreach ([10, 20, 30, 50, 100] as $n) {
            $perPage[] = ['id' => $n, 'name' => $n];
        }
        $forms = [
            ['form' => [
                'legend' => ['title' => $this->l('Historial de pedidos'), 'icon' => 'icon-list'],
                'input' => [
                    $this->switchField('JOSRAPP_HISTORY', $this->l('Historial paginado'),
                        $this->l('Sustituye la página «Historial y detalles de mis pedidos» por una paginada con buscador. Evita el error de memoria con clientes de miles de pedidos. Usa la plantilla de historial de tu tema.')),
                    [
                        'type' => 'select',
                        'label' => $this->l('Pedidos por página'),
                        'name' => 'JOSRAPP_HISTORY_PER_PAGE',
                        'options' => ['query' => $perPage, 'id' => 'id', 'name' => 'name'],
                    ],
                ],
                'submit' => ['title' => $this->l('Guardar'), 'name' => 'submitJosrapp'],
            ]],
            ['form' => [
                'legend' => ['title' => $this->l('Mis direcciones'), 'icon' => 'icon-map-marker'],
                'input' => [
                    $this->switchField('JOSRAPP_ADDRESSES', $this->l('Direcciones paginadas'),
                        $this->l('Sustituye la página «Mis direcciones» por una paginada, con buscador y ordenada por la última usada.')),
                    [
                        'type' => 'select',
                        'label' => $this->l('Direcciones por página'),
                        'name' => 'JOSRAPP_ADDRESSES_PER_PAGE',
                        'options' => ['query' => $perPage, 'id' => 'id', 'name' => 'name'],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Vista por defecto'),
                        'name' => 'JOSRAPP_ADDRESSES_VIEW',
                        'desc' => $this->l('El cliente puede cambiar de vista con un botón; se recuerda su elección.'),
                        'options' => ['query' => [
                            ['id' => 'table', 'name' => $this->l('Tabla compacta (recomendada con muchas direcciones)')],
                            ['id' => 'cards', 'name' => $this->l('Tarjetas del tema')],
                        ], 'id' => 'id', 'name' => 'name'],
                    ],
                ],
                'submit' => ['title' => $this->l('Guardar'), 'name' => 'submitJosrapp'],
            ]],
            ['form' => [
                'legend' => ['title' => $this->l('Checkout'), 'icon' => 'icon-shopping-cart'],
                'input' => [
                    $this->switchField('JOSRAPP_CHECKOUT', $this->l('Lista corta y buscador'),
                        $this->l('En el paso de direcciones muestra solo las seleccionadas y las últimas usadas, con un buscador para elegir cualquier otra.')),
                    [
                        'type' => 'text',
                        'label' => $this->l('A partir de'),
                        'name' => 'JOSRAPP_CHECKOUT_MIN',
                        'suffix' => $this->l('direcciones'),
                        'col' => 2,
                        'desc' => $this->l('Los clientes con menos direcciones ven el checkout de siempre.'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Direcciones recientes a mostrar'),
                        'name' => 'JOSRAPP_CHECKOUT_RECENT',
                        'col' => 2,
                    ],
                    $this->switchField('JOSRAPP_HABITUAL_INVOICE', $this->l('Facturación habitual preseleccionada'),
                        $this->l('Al empezar cada compra se selecciona como dirección de facturación la que el cliente más ha usado para facturar. Así solo tiene que buscar la de envío.')),
                    $this->switchField('JOSRAPP_FOLD_INVOICE', $this->l('Plegar la facturación'),
                        $this->l('Muestra solo la dirección de facturación elegida, con un botón «Cambiar» para ver las demás.')),
                ],
                'submit' => ['title' => $this->l('Guardar'), 'name' => 'submitJosrapp'],
            ]],
        ];
        $values = [];
        foreach (self::defaults() as $k => $v) {
            $values[$k] = Configuration::get($k);
        }
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->module = $this;
        $helper->table = 'josrapp_conf';
        $helper->identifier = 'id_josrapp_conf';
        $helper->default_form_language = (int) $this->context->language->id;
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->submit_action = 'submitJosrapp';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name
            . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $values,
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => (int) $this->context->language->id,
        ];

        return $helper->generateForm($forms);
    }
}
