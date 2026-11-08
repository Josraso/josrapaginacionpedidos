<?php
/**
 * Historial de pedidos paginado: solo se cargan los pedidos de la página que se ve.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class JosrapaginacionpedidosHistoryModuleFrontController extends ModuleFrontController
{
    public $auth = true;
    public $authRedirection = 'history';
    public $ssl = true;

    protected function presenter()
    {
        if (class_exists('PrestaShop\PrestaShop\Adapter\Presenter\Order\OrderPresenter')) {
            return new PrestaShop\PrestaShop\Adapter\Presenter\Order\OrderPresenter();
        }

        return new PrestaShop\PrestaShop\Adapter\Order\OrderPresenter(); // PrestaShop 1.7.0 - 1.7.4
    }

    public function initContent()
    {
        parent::initContent();
        if (!Configuration::get('JOSRAPP_HISTORY')) {
            Tools::redirect($this->context->link->getPageLink('history', true));
        }
        $idCustomer = (int) $this->context->customer->id;
        $perPage = max(5, (int) Configuration::get('JOSRAPP_HISTORY_PER_PAGE'));
        $q = trim((string) Tools::getValue('q'));
        $year = (int) Tools::getValue('year');

        $where = 'o.`id_customer` = ' . $idCustomer . Shop::addSqlRestriction(Shop::SHARE_ORDER, 'o');
        if ($q !== '') {
            $like = pSQL(str_replace(['%', '_'], ['\%', '\_'], $q));
            $where .= ' AND (o.`reference` LIKE \'%' . $like . '%\' OR o.`id_order` = ' . (int) $q . ')';
        }
        if ($year) {
            $where .= ' AND YEAR(o.`date_add`) = ' . $year;
        }
        $total = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'orders` o WHERE ' . $where);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) Tools::getValue('page', 1)));
        $ids = Db::getInstance()->executeS('SELECT o.`id_order` FROM `' . _DB_PREFIX_ . 'orders` o WHERE ' . $where
            . ' ORDER BY o.`date_add` DESC, o.`id_order` DESC LIMIT ' . (int) (($page - 1) * $perPage) . ', ' . (int) $perPage);

        $presenter = $this->presenter();
        $orders = [];
        foreach ((array) $ids as $r) {
            $order = new Order((int) $r['id_order']);
            if (Validate::isLoadedObject($order)) {
                $orders[(int) $order->id] = $presenter->present($order);
            }
        }
        $years = [];
        foreach ((array) Db::getInstance()->executeS('SELECT DISTINCT YEAR(`date_add`) y FROM `' . _DB_PREFIX_ . 'orders`
            WHERE `id_customer` = ' . $idCustomer . ' ORDER BY y DESC') as $y) {
            $years[] = (int) $y['y'];
        }
        $totalAll = ($q === '' && !$year) ? $total : (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'orders` WHERE `id_customer` = ' . $idCustomer);

        $this->context->smarty->assign([
            'orders' => $orders,
            'josrapp' => [
                'total' => $total,
                'total_all' => $totalAll,
                'page' => $page,
                'pages' => $pages,
                'from' => $total ? ($page - 1) * $perPage + 1 : 0,
                'to' => min($total, $page * $perPage),
                'q' => $q,
                'year' => $year,
                'years' => $years,
                'action' => $this->context->link->getModuleLink($this->module->name, 'history', [], true),
                'page_url' => self::pagePrefix($this->context->link->getModuleLink($this->module->name, 'history', array_filter(['q' => $q, 'year' => $year]), true)),
            ],
        ]);
        $this->setTemplate('module:josrapaginacionpedidos/views/templates/front/history.tpl');
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
        $page['page_name'] = 'history';
        $page['body_classes']['page-history'] = true;
        $page['body_classes']['page-customer-account'] = true;
        $page['meta']['title'] = $this->trans('Order history', [], 'Shop.Theme.Customeraccount');
        $page['meta']['robots'] = 'noindex';

        return $page;
    }

    public function getBreadcrumbLinks()
    {
        $breadcrumb = parent::getBreadcrumbLinks();
        $breadcrumb['links'][] = $this->addMyAccountToBreadcrumb();
        $breadcrumb['links'][] = [
            'title' => $this->trans('Order history', [], 'Shop.Theme.Customeraccount'),
            'url' => $this->context->link->getModuleLink($this->module->name, 'history', [], true),
        ];

        return $breadcrumb;
    }
}
