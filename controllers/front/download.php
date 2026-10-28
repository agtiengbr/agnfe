<?php

class AgNfeDownloadModuleFrontController extends ModuleFrontController
{
    public $auth = true;
    public $guestAllowed = false;

    public function initContent()
    {
        $idOrder = (int) Tools::getValue('id_order');
        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order)
            || !$this->context->customer->isLogged()
            || (int) $order->id_customer !== (int) $this->context->customer->id) {
            header('HTTP/1.1 403 Forbidden');
            exit;
        }
        $doc = AgNfeDocument::getByOrder($idOrder);
        if (!$doc || !(int) $doc['published'] || !AgNfeDocument::fileExists($doc['pdf_path'])) {
            header('HTTP/1.1 404 Not Found');
            exit;
        }
        $path = AgNfeDocument::getPrivateDir() . '/' . $doc['pdf_path'];
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="DANFE-' . (int) $idOrder . '.pdf"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }
}
