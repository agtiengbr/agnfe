<?php

class AdminAgNfeDocumentsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    private function requireOrder()
    {
        $idOrder = (int) Tools::getValue('id_order');
        if (!Validate::isLoadedObject(new Order($idOrder))) {
            throw new Exception('Pedido inválido.');
        }
        return $idOrder;
    }

    private function response($success, $message, $doc = null)
    {
        header('Content-Type: application/json; charset=utf-8');
        die(json_encode([
            'success' => (bool) $success,
            'message' => $message,
            'published' => $doc ? (bool) $doc['published'] : false,
            'notification_status' => $doc ? (int) $doc['notification_status'] : 0,
        ]));
    }

    public function ajaxProcessUpload()
    {
        try {
            $idOrder = $this->requireOrder();
            $field = Tools::getValue('file_type');
            if (!isset($_FILES['document'])) {
                throw new Exception('Selecione um arquivo.');
            }
            $doc = AgNfeDocument::saveUpload($idOrder, $field, $_FILES['document']);
            $this->response(true, $doc['published'] ? 'DANFE disponível para o cliente.' : 'Arquivo anexado.', $doc);
        } catch (Exception $e) {
            $this->response(false, $e->getMessage());
        }
    }

    public function ajaxProcessReplace()
    {
        try {
            $idOrder = $this->requireOrder();
            if (!isset($_FILES['xml'], $_FILES['pdf'])) {
                throw new Exception('Selecione XML e PDF.');
            }
            $doc = AgNfeDocument::replacePair($idOrder, $_FILES['xml'], $_FILES['pdf']);
            $this->response(true, 'Nota substituída. O aviso anterior não foi reenviado.', $doc);
        } catch (Exception $e) {
            $this->response(false, $e->getMessage());
        }
    }

    public function ajaxProcessRetryNotice()
    {
        try {
            $idOrder = $this->requireOrder();
            $doc = AgNfeDocument::getByOrder($idOrder);
            if (!$doc || !(int) $doc['published'] || (int) $doc['notification_status'] === 1) {
                throw new Exception('Não há aviso pendente para reenviar.');
            }
            $sent = AgNfeDocument::sendNotification($idOrder);
            $this->response($sent, $sent ? 'Aviso enviado.' : 'O e-mail não pôde ser enviado.', AgNfeDocument::getByOrder($idOrder));
        } catch (Exception $e) {
            $this->response(false, $e->getMessage());
        }
    }

    public function ajaxProcessDownload()
    {
        $idOrder = $this->requireOrder();
        $field = Tools::getValue('file_type');
        if (!in_array($field, ['xml', 'pdf'], true)) {
            header('HTTP/1.1 400 Bad Request');
            exit;
        }
        $doc = AgNfeDocument::getByOrder($idOrder);
        $name = $doc ? $doc[$field . '_path'] : null;
        if (!AgNfeDocument::fileExists($name)) {
            header('HTTP/1.1 404 Not Found');
            exit;
        }
        $path = AgNfeDocument::getPrivateDir() . '/' . $name;
        header('Content-Type: ' . ($field === 'pdf' ? 'application/pdf' : 'application/xml'));
        header('Content-Disposition: attachment; filename="' . strtoupper($field) . '-' . $idOrder . '.' . $field . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }
}
