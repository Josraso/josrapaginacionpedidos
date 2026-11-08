<?php
/**
 * Mis direcciones: paginadas, con buscador, ordenadas por la última usada y con vista compacta en tabla.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class JosrapaginacionpedidosAddressesModuleFrontController extends ModuleFrontController
{
    public $auth = true;
    public $authRedirection = 'addresses';
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();
        if (!Configuration::get('JOSRAPP_ADDRESSES')) {
            Tools::redirect($this->context->link->getPageLink('addresses', true));
        }
        $idCustomer = (int) $this->context->customer->id;
        $idLang = (int) $this->context->language->id;
        $perPage = max(5, (int) Configuration::get('JOSRAPP_ADDRESSES_PER_PAGE'));
        $q = trim((string) Tools::getValue('q'));
        $sort = Tools::getValue('sort') === 'alias' ? 'alias' : 'recent';

        // Vista: la elegida por el cliente se recuerda en su cookie
        $view = (string) Tools::getValue('view');
        if (in_array($view, ['table', 'cards'], true)) {
            $this->context->cookie->__set('josrapp_view', $view);
        } else {
            $view = (string) $this->context->cookie->__get('josrapp_view');
            if (!in_array($view, ['table', 'cards'], true)) {
                $view = Configuration::get('JOSRAPP_ADDRESSES_VIEW') === 'cards' ? 'cards' : 'table';
            }
        }

        $where = Josrapaginacionpedidos::addressSearchSql($idCustomer, $q);
        $total = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'address` a WHERE ' . $where);
        $totalAll = $q === '' ? $total : Josrapaginacionpedidos::countAddresses($idCustomer);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) Tools::getValue('page', 1)));
        $order = $sort === 'alias' ? 'a.`alias` ASC, a.`id_address` DESC' : 'us.`last_used` IS NULL, us.`last_used` DESC, a.`date_upd` DESC';
        $rows = Db::getInstance()->executeS('SELECT a.*, us.`n_orders`, us.`last_used`, cl.`name` AS country, s.`name` AS state
            FROM `' . _DB_PREFIX_ . 'address` a' . Josrapaginacionpedidos::usageJoin($idCustomer) . '
            LEFT JOIN `' . _DB_PREFIX_ . 'country_lang` cl ON (cl.`id_country` = a.`id_country` AND cl.`id_lang` = ' . $idLang . ')
            LEFT JOIN `' . _DB_PREFIX_ . 'state` s ON (s.`id_state` = a.`id_state`)
            WHERE ' . $where . ' ORDER BY ' . $order . ' LIMIT ' . (int) (($page - 1) * $perPage) . ', ' . (int) $perPage);

        $token = Tools::getToken();
        $addresses = [];
        foreach ((array) $rows as $r) {
            $id = (int) $r['id_address'];
            $addresses[$id] = [
                'id' => $id,
                'alias' => $r['alias'],
                'firstname' => $r['firstname'],
                'lastname' => $r['lastname'],
                'company' => $r['company'],
                'vat_number' => $r['vat_number'],
                'address1' => $r['address1'],
                'address2' => $r['address2'],
                'postcode' => $r['postcode'],
                'city' => $r['city'],
                'id_state' => (int) $r['id_state'],
                'state' => $r['state'],
                'id_country' => (int) $r['id_country'],
                'country' => $r['country'],
                'phone' => $r['phone'],
                'phone_mobile' => $r['phone_mobile'],
                'dni' => $r['dni'],
                'other' => $r['other'],
                'n_orders' => (int) $r['n_orders'],
                'last_used' => $r['last_used'],
                'formatted' => AddressFormat::generateAddress(new Address($id, $idLang), [], '<br>'),
                'edit_url' => $this->context->link->getPageLink('address', true, null, ['id_address' => $id]),
                'delete_url' => $this->context->link->getPageLink('address', true, null, ['id_address' => $id, 'delete' => 1, 'token' => $token]),
            ];
        }

        // La vista de tarjetas usa la plantilla del tema, que recorre $customer.addresses
        $customerVar = $this->context->smarty->getTemplateVars('customer');
        if (is_array($customerVar)) {
            $customerVar['addresses'] = $addresses;
            $this->context->smarty->assign('customer', $customerVar);
        }
        $base = ['q' => $q, 'sort' => $sort === 'alias' ? 'alias' : null];
        $link = $this->context->link;
        $this->context->smarty->assign([
            'josrapp' => [
                'addresses' => $addresses,
                'total' => $total,
                'total_all' => $totalAll,
                'page' => $page,
                'pages' => $pages,
                'from' => $total ? ($page - 1) * $perPage + 1 : 0,
                'to' => min($total, $page * $perPage),
                'q' => $q,
                'sort' => $sort,
                'view' => $view,
                'action' => $link->getModuleLink($this->module->name, 'addresses', [], true),
                'page_url' => self::pagePrefix($link->getModuleLink($this->module->name, 'addresses', array_filter($base), true)),
                'view_table' => $link->getModuleLink($this->module->name, 'addresses', array_filter(array_merge($base, ['view' => 'table', 'page' => $page > 1 ? $page : null])), true),
                'view_cards' => $link->getModuleLink($this->module->name, 'addresses', array_filter(array_merge($base, ['view' => 'cards', 'page' => $page > 1 ? $page : null])), true),
                'new_url' => $link->getPageLink('address', true),
            ],
        ]);
        $this->setTemplate('module:josrapaginacionpedidos/views/templates/front/' . ($view === 'cards' ? 'addresses-cards.tpl' : 'addresses-table.tpl'));
    }

    protected static function pagePrefix($url)
    {
        return $url . (strpos($url, '?') === false ? '?' : '&') . 'page=';
    }

    public function setMedia()
    {
        parent::setMedia();
        $this->registerStylesheet('modules-josrapp-front', 'modules/' . $this->module->name . '/views/css/front.css', ['media' => 'all', 'priority' => 200]);
    }

    public function getTemplateVarPage()
    {
        $page = parent::getTemplateVarPage();
        $page['page_name'] = 'addresses';
        $page['body_classes']['page-addresses'] = true;
        $page['body_classes']['page-customer-account'] = true;
        $page['meta']['title'] = $this->trans('Addresses', [], 'Shop.Theme.Customeraccount');
        $page['meta']['robots'] = 'noindex';

        return $page;
    }

    public function getBreadcrumbLinks()
    {
        $breadcrumb = parent::getBreadcrumbLinks();
        $breadcrumb['links'][] = $this->addMyAccountToBreadcrumb();
        $breadcrumb['links'][] = [
            'title' => $this->trans('Addresses', [], 'Shop.Theme.Customeraccount'),
            'url' => $this->context->link->getModuleLink($this->module->name, 'addresses', [], true),
        ];

        return $breadcrumb;
    }
}
