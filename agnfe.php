<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/AgNfeDocument.php';

class AgNfe extends Module
{
    public function __construct()
    {
        $this->name = 'agnfe';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'AGTI';
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = 'Documentos da NF-e';
        $this->description = 'Anexa XML e DANFE ao pedido e disponibiliza o DANFE ao cliente.';
    }

    public function install()
    {
        if (!class_exists('DOMDocument')) {
            $this->_errors[] = 'A extensão PHP DOM é necessária para validar o XML da NF-e.';
            return false;
        }
        return parent::install()
            && AgNfeDocument::installStorage()
            && $this->registerHook('displayAdminOrderTabLink')
            && $this->registerHook('displayAdminOrderTabContent')
            && $this->registerHook('displayOrderDetail')
            && $this->registerHook('actionAdminControllerSetMedia')
            && $this->installAdminTab()
            && AgNfeDocument::importMelhorEnvioXml();
    }

    private function installAdminTab()
    {
        $tab = new Tab();
        $tab->class_name = 'AdminAgNfeDocuments';
        $tab->module = $this->name;
        $tab->active = 0;
        $tab->id_parent = -1;
        foreach (Language::getLanguages(false) as $language) {
            $tab->name[(int) $language['id_lang']] = 'Documentos NF-e';
        }
        return $tab->add();
    }

    public function getContent()
    {
        if (Tools::isSubmit('submitAgNfeImport')) {
            AgNfeDocument::importMelhorEnvioXml();
        }
        if (Tools::isSubmit('submitAgNfeState')) {
            $id = (int) Tools::getValue('AGNFE_ORDER_STATE');
            if (!$id || !Validate::isLoadedObject(new OrderState($id))) {
                return $this->displayError('Selecione o estado do pedido para NF-e gerada.');
            }
            Configuration::updateValue('AGNFE_ORDER_STATE', $id);
            if ($id) {
                AgNfeDocument::clearConflictingMelhorEnvioMappings($id);
                AgNfeDocument::publishPending();
            }
        }

        $options = '<option value="0">Selecione um estado</option>';
        $selected = (int) Configuration::get('AGNFE_ORDER_STATE');
        foreach (OrderState::getOrderStates((int) $this->context->language->id) as $state) {
            $id = (int) $state['id_order_state'];
            $options .= '<option value="' . $id . '"' . ($id === $selected ? ' selected' : '') . '>'
                . Tools::safeOutput($state['name']) . '</option>';
        }

        $pending = AgNfeDocument::getPendingLegacyOrderIds();
        $migration = $pending ? '<div class="alert alert-warning">XMLs antigos ainda não importados nos pedidos: '
            . Tools::safeOutput(implode(', ', array_slice($pending, 0, 30))) . '</div>' : '';

        return $migration . '<form method="post"><div class="panel"><h3>Pedido com NF-e gerada</h3>'
            . '<p>Estado aplicado quando XML e DANFE forem anexados ao pedido.</p>'
            . '<select name="AGNFE_ORDER_STATE" class="form-control">' . $options . '</select><br>'
            . '<button class="btn btn-primary" name="submitAgNfeState" type="submit">Salvar</button>'
            . '</div></form><form method="post"><div class="panel"><h3>Migração do Melhor Envio</h3>'
            . '<p>Os arquivos antigos permanecem no módulo original. A importação confere o hash da cópia.</p>'
            . '<button class="btn btn-default" name="submitAgNfeImport" type="submit">Conferir e importar XMLs pendentes</button>'
            . '</div></form>';
    }

    public function hookActionAdminControllerSetMedia($params)
    {
        $legacyOrderView = $this->context->controller
            && $this->context->controller->controller_name === 'AdminOrders'
            && Tools::getIsSet('vieworder');
        $modernOrderView = !empty($_SERVER['REQUEST_URI'])
            && preg_match('#/sell/orders/[0-9]+/view(?:[/?]|$)#', $_SERVER['REQUEST_URI']);
        if ($this->context->controller && ($legacyOrderView || $modernOrderView)) {
            $this->context->controller->addJS($this->_path . 'views/js/admin_order.js?v=2');
            $this->context->controller->addCSS($this->_path . 'views/css/admin_order.css');
        }
    }

    public function hookDisplayAdminOrderTabLink($params)
    {
        return '<li class="nav-item"><a class="nav-link" id="agnfe-tab" data-toggle="tab" href="#agnfe-content" role="tab">NF-e / DANFE</a></li>';
    }

    public function hookDisplayAdminOrderTabContent($params)
    {
        $idOrder = isset($params['id_order']) ? (int) $params['id_order'] : (int) Tools::getValue('id_order');
        if (!Validate::isLoadedObject(new Order($idOrder))) {
            return '';
        }
        $doc = AgNfeDocument::getByOrder($idOrder);
        $this->context->smarty->assign([
            'agnfe_doc' => $doc,
            'agnfe_id_order' => $idOrder,
            'agnfe_ajax_url' => $this->context->link->getAdminLink('AdminAgNfeDocuments'),
            'agnfe_admin_xml_url' => $this->context->link->getAdminLink('AdminAgNfeDocuments', true, [], [
                'ajax' => 1, 'action' => 'Download', 'id_order' => $idOrder, 'file_type' => 'xml',
            ]),
            'agnfe_admin_pdf_url' => $this->context->link->getAdminLink('AdminAgNfeDocuments', true, [], [
                'ajax' => 1, 'action' => 'Download', 'id_order' => $idOrder, 'file_type' => 'pdf',
            ]),
        ]);
        return $this->display(__FILE__, 'views/templates/hook/admin_order.tpl');
    }

    public function hookDisplayOrderDetail($params)
    {
        $idOrder = isset($params['order']) && $params['order'] instanceof Order
            ? (int) $params['order']->id : (int) Tools::getValue('id_order');
        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order) || !$this->context->customer->isLogged()
            || (int) $order->id_customer !== (int) $this->context->customer->id) {
            return '';
        }
        $doc = AgNfeDocument::getByOrder($idOrder);
        if (!$doc || !(int) $doc['published'] || !AgNfeDocument::fileExists($doc['pdf_path'])) {
            return '';
        }
        $this->context->smarty->assign([
            'agnfe_number' => $doc['nfe_number'],
            'agnfe_download_url' => $this->context->link->getModuleLink('agnfe', 'download', ['id_order' => $idOrder], true),
        ]);
        return $this->display(__FILE__, 'views/templates/hook/order_detail.tpl');
    }
}
