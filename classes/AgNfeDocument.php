<?php

class AgNfeDocument
{
    const MAX_FILE_SIZE = 10485760;

    public static function installStorage()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'agnfe_document` (
            `id_order` INT UNSIGNED NOT NULL,
            `xml_path` VARCHAR(512) NULL,
            `pdf_path` VARCHAR(512) NULL,
            `nfe_key` VARCHAR(44) NULL,
            `nfe_number` VARCHAR(30) NULL,
            `published` TINYINT(1) NOT NULL DEFAULT 0,
            `published_at` DATETIME NULL,
            `notification_status` TINYINT NOT NULL DEFAULT 0,
            `notification_error` VARCHAR(255) NULL,
            `notification_sent_at` DATETIME NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_order`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4';
        return Db::getInstance()->execute($sql) && self::ensurePrivateDir();
    }

    public static function getByOrder($idOrder)
    {
        $idOrder = (int) $idOrder;
        if ($idOrder <= 0) {
            return null;
        }
        $row = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'agnfe_document` WHERE id_order = ' . $idOrder);
        return $row ?: null;
    }

    public static function getPrivateDir()
    {
        return dirname(_PS_ROOT_DIR_) . '/agnfe_private';
    }

    private static function ensurePrivateDir()
    {
        $dir = self::getPrivateDir();
        return (is_dir($dir) || @mkdir($dir, 0700, true)) && is_writable($dir);
    }

    public static function fileExists($path)
    {
        if (!$path || !preg_match('/^[a-f0-9]{32}\.(xml|pdf)$/', $path)) {
            return false;
        }
        return is_file(self::getPrivateDir() . '/' . $path);
    }

    public static function getXmlContent($idOrder)
    {
        $doc = self::getByOrder($idOrder);
        return $doc && self::fileExists($doc['xml_path'])
            ? file_get_contents(self::getPrivateDir() . '/' . $doc['xml_path']) : null;
    }

    public static function saveUpload($idOrder, $field, $file)
    {
        $idOrder = (int) $idOrder;
        if (!Validate::isLoadedObject(new Order($idOrder))) {
            throw new Exception('Pedido inválido.');
        }
        if (!in_array($field, ['xml', 'pdf'], true)) {
            throw new Exception('Tipo de arquivo inválido.');
        }
        if (!isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK
            || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])
            || (int) $file['size'] <= 0 || (int) $file['size'] > self::MAX_FILE_SIZE) {
            throw new Exception('Arquivo não recebido ou maior que 10 MB.');
        }

        $current = self::getByOrder($idOrder);
        if ($current && (int) $current['published']) {
            throw new Exception('Nota já publicada. Para corrigi-la, envie um novo par XML e PDF.');
        }
        $details = $field === 'xml' ? self::validateXml($file['tmp_name']) : self::validatePdf($file['tmp_name']);
        if (!self::ensurePrivateDir()) {
            throw new Exception('Armazenamento privado indisponível.');
        }
        $name = bin2hex(random_bytes(16)) . '.' . $field;
        $destination = self::getPrivateDir() . '/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new Exception('Não foi possível salvar o arquivo.');
        }
        self::removeMovedUploadFromGlobals($file['tmp_name']);
        @chmod($destination, 0600);

        $data = [
            $field . '_path' => $name,
            'date_upd' => date('Y-m-d H:i:s'),
        ];
        if ($field === 'xml') {
            $data['nfe_key'] = $details['key'];
            $data['nfe_number'] = $details['number'];
        }
        if ($current) {
            $ok = Db::getInstance()->update('agnfe_document', $data, 'id_order = ' . $idOrder);
        } else {
            $data['id_order'] = $idOrder;
            $ok = Db::getInstance()->insert('agnfe_document', $data);
        }
        if (!$ok) {
            @unlink($destination);
            throw new Exception('Não foi possível salvar o cadastro da NF-e.');
        }
        if ($current && self::fileExists($current[$field . '_path'])) {
            @unlink(self::getPrivateDir() . '/' . $current[$field . '_path']);
        }

        self::publishIfComplete($idOrder);
        if ($field === 'xml') {
            self::retryMelhorEnvio($idOrder);
        }
        return self::getByOrder($idOrder);
    }

    public static function replacePair($idOrder, $xmlFile, $pdfFile)
    {
        $idOrder = (int) $idOrder;
        $current = self::getByOrder($idOrder);
        if (!$current || !(int) $current['published']) {
            throw new Exception('Não há nota publicada para substituir.');
        }
        foreach ([$xmlFile, $pdfFile] as $file) {
            if (!isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK
                || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])
                || (int) $file['size'] <= 0 || (int) $file['size'] > self::MAX_FILE_SIZE) {
                throw new Exception('Envie os dois arquivos, cada um com até 10 MB.');
            }
        }
        $details = self::validateXml($xmlFile['tmp_name']);
        self::validatePdf($pdfFile['tmp_name']);
        if (!self::ensurePrivateDir()) {
            throw new Exception('Armazenamento privado indisponível.');
        }
        $xmlName = bin2hex(random_bytes(16)) . '.xml';
        $pdfName = bin2hex(random_bytes(16)) . '.pdf';
        $xmlPath = self::getPrivateDir() . '/' . $xmlName;
        $pdfPath = self::getPrivateDir() . '/' . $pdfName;
        if (!move_uploaded_file($xmlFile['tmp_name'], $xmlPath)) {
            throw new Exception('Não foi possível salvar o novo XML.');
        }
        if (!move_uploaded_file($pdfFile['tmp_name'], $pdfPath)) {
            @unlink($xmlPath);
            throw new Exception('Não foi possível salvar o novo DANFE.');
        }
        self::removeMovedUploadFromGlobals($xmlFile['tmp_name']);
        self::removeMovedUploadFromGlobals($pdfFile['tmp_name']);
        @chmod($xmlPath, 0600);
        @chmod($pdfPath, 0600);
        $ok = Db::getInstance()->update('agnfe_document', [
            'xml_path' => $xmlName,
            'pdf_path' => $pdfName,
            'nfe_key' => $details['key'],
            'nfe_number' => $details['number'],
            'date_upd' => date('Y-m-d H:i:s'),
        ], 'id_order = ' . $idOrder);
        if (!$ok) {
            @unlink($xmlPath);
            @unlink($pdfPath);
            throw new Exception('Não foi possível substituir a nota.');
        }
        foreach (['xml_path', 'pdf_path'] as $field) {
            if (self::fileExists($current[$field])) {
                @unlink(self::getPrivateDir() . '/' . $current[$field]);
            }
        }
        self::retryMelhorEnvio($idOrder);
        return self::getByOrder($idOrder);
    }

    private static function removeMovedUploadFromGlobals($path)
    {
        // Hooks can create a Symfony Request after the move. Its FileBag must not
        // attempt to reopen an upload that PHP has already moved away.
        foreach ($_FILES as $key => $upload) {
            if (isset($upload['tmp_name']) && $upload['tmp_name'] === $path) {
                unset($_FILES[$key]);
            }
        }
    }

    private static function validateXml($path)
    {
        $xml = file_get_contents($path);
        if ($xml === false || strlen($xml) > self::MAX_FILE_SIZE || stripos($xml, '<!DOCTYPE') !== false) {
            throw new Exception('XML inválido.');
        }
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $valid = $dom->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$valid) {
            throw new Exception('XML inválido.');
        }
        $xpath = new DOMXPath($dom);
        $id = trim((string) $xpath->evaluate('string(//*[local-name()="infNFe"]/@Id)'));
        $key = preg_match('/^NFe([0-9]{44})$/', $id, $m) ? $m[1] : '';
        $number = trim((string) $xpath->evaluate('string(//*[local-name()="ide"]/*[local-name()="nNF"])'));
        if (!$key || !preg_match('/^[0-9]{1,30}$/', $number)) {
            throw new Exception('O XML não contém chave e número válidos de NF-e.');
        }
        return ['key' => $key, 'number' => $number];
    }

    private static function validatePdf($path)
    {
        $handle = fopen($path, 'rb');
        $header = $handle ? fread($handle, 5) : '';
        if ($handle) {
            fseek($handle, max(0, filesize($path) - 1024));
            $tail = stream_get_contents($handle);
            fclose($handle);
        } else {
            $tail = '';
        }
        if ($header !== '%PDF-' || strpos($tail, '%%EOF') === false) {
            throw new Exception('O DANFE deve ser um PDF válido.');
        }
        return true;
    }

    private static function publishIfComplete($idOrder)
    {
        $doc = self::getByOrder($idOrder);
        if (!$doc || (int) $doc['published'] || !self::fileExists($doc['xml_path'])
            || !self::fileExists($doc['pdf_path'])) {
            return;
        }
        $stateId = (int) Configuration::get('AGNFE_ORDER_STATE');
        if (!$stateId || !Validate::isLoadedObject(new OrderState($stateId))) {
            throw new Exception('Configure o estado “Pedido com NF-e gerada” no módulo agnfe.');
        }
        $order = new Order((int) $idOrder);
        if ((int) $order->current_state !== $stateId) {
            $history = new OrderHistory();
            $history->id_order = (int) $idOrder;
            $history->changeIdOrderState($stateId, $order, true);
            if (!$history->add()) {
                throw new Exception('Não foi possível atualizar o estado do pedido.');
            }
        }
        $now = date('Y-m-d H:i:s');
        if (!Db::getInstance()->update('agnfe_document', [
            'published' => 1,
            'published_at' => $now,
            'notification_status' => 0,
        ], 'id_order = ' . (int) $idOrder . ' AND published = 0')) {
            throw new Exception('Não foi possível disponibilizar o DANFE.');
        }
        self::sendNotification($idOrder);
    }

    public static function publishPending()
    {
        $rows = Db::getInstance()->executeS('SELECT id_order FROM `' . _DB_PREFIX_
            . 'agnfe_document` WHERE published = 0 AND xml_path IS NOT NULL AND pdf_path IS NOT NULL');
        foreach ((array) $rows as $row) {
            self::publishIfComplete((int) $row['id_order']);
        }
    }

    public static function sendNotification($idOrder)
    {
        $doc = self::getByOrder($idOrder);
        if (!$doc || !(int) $doc['published'] || (int) $doc['notification_status'] === 1) {
            return false;
        }
        $order = new Order((int) $idOrder);
        $customer = new Customer((int) $order->id_customer);
        $url = Context::getContext()->link->getPageLink('order-detail', true, (int) $order->id_lang,
            ['id_order' => (int) $idOrder]);
        try {
            $sent = (int) Configuration::get('PS_MAIL_METHOD') !== Mail::METHOD_DISABLE
                && Mail::Send((int) $order->id_lang, 'agnfe_available',
                    'Nota fiscal disponível para o pedido ' . $order->reference,
                    ['{firstname}' => $customer->firstname, '{order_reference}' => $order->reference, '{order_url}' => $url],
                    $customer->email, $customer->firstname . ' ' . $customer->lastname,
                    null, null, null, null, _PS_MODULE_DIR_ . 'agnfe/mails', false, (int) $order->id_shop);
        } catch (Exception $e) {
            $sent = false;
            PrestaShopLogger::addLog('agnfe: falha no aviso do pedido ' . (int) $idOrder . ': ' . $e->getMessage(), 2);
        }
        Db::getInstance()->update('agnfe_document', [
            'notification_status' => $sent ? 1 : -1,
            'notification_error' => $sent ? null : 'Falha no envio. Verifique a configuração de e-mail.',
            'notification_sent_at' => $sent ? date('Y-m-d H:i:s') : null,
        ], 'id_order = ' . (int) $idOrder);
        return (bool) $sent;
    }

    private static function retryMelhorEnvio($idOrder)
    {
        if (Module::isEnabled('agmelhorenvio')) {
            try {
                $module = Module::getInstanceByName('agmelhorenvio');
                $module->processPendingLabelEmission((int) $idOrder);
            } catch (Exception $e) {
                PrestaShopLogger::addLog('agnfe: falha na tentativa de emissão para pedido ' . (int) $idOrder . ': ' . $e->getMessage(), 2);
            }
        }
    }

    public static function clearConflictingMelhorEnvioMappings($stateId)
    {
        $rows = Db::getInstance()->executeS('SELECT `name`, `value` FROM `' . _DB_PREFIX_
            . 'configuration` WHERE `name` LIKE "AGMELHORENVIO_STATUS_MAPPING_%"');
        foreach ((array) $rows as $row) {
            if ($row['name'] !== 'AGMELHORENVIO_STATUS_MAPPING_ENABLED'
                && (int) $row['value'] === (int) $stateId) {
                Configuration::updateValue($row['name'], 0);
            }
        }
    }

    public static function importMelhorEnvioXml()
    {
        $table = _DB_PREFIX_ . 'agmelhorenvio_order_nfe';
        if (!Db::getInstance()->executeS('SHOW TABLES LIKE "' . pSQL($table) . '"')) {
            return true;
        }
        $rows = Db::getInstance()->executeS('SELECT id_order, file_path FROM `' . $table . '`');
        foreach ((array) $rows as $row) {
            $idOrder = (int) $row['id_order'];
            if (self::getByOrder($idOrder)) {
                continue;
            }
            $relative = str_replace('\\', '/', (string) $row['file_path']);
            $allowedDir = realpath(_PS_MODULE_DIR_ . 'agmelhorenvio/var/nfe/' . $idOrder);
            if (!$allowedDir) {
                continue;
            }
            $source = realpath(strpos($relative, '/') === 0
                ? $relative : _PS_MODULE_DIR_ . 'agmelhorenvio/' . $relative);
            if (!$source || dirname($source) !== $allowedDir || !preg_match('/\.xml$/i', $source)
                || !is_file($source) || !is_readable($source)) {
                continue;
            }
            try {
                $details = self::validateXml($source);
                $name = bin2hex(random_bytes(16)) . '.xml';
                if (!copy($source, self::getPrivateDir() . '/' . $name)
                    || hash_file('sha256', $source) !== hash_file('sha256', self::getPrivateDir() . '/' . $name)) {
                    throw new Exception('Cópia do XML não conferiu.');
                }
                @chmod(self::getPrivateDir() . '/' . $name, 0600);
                if (!Db::getInstance()->insert('agnfe_document', [
                    'id_order' => $idOrder,
                    'xml_path' => $name,
                    'nfe_key' => $details['key'],
                    'nfe_number' => $details['number'],
                    'date_upd' => date('Y-m-d H:i:s'),
                ])) {
                    @unlink(self::getPrivateDir() . '/' . $name);
                    throw new Exception('Falha ao registrar XML importado.');
                }
            } catch (Exception $e) {
                PrestaShopLogger::addLog('agnfe: XML antigo do pedido ' . $idOrder . ' não importado: ' . $e->getMessage(), 2);
            }
        }
        return true;
    }

    public static function getPendingLegacyOrderIds()
    {
        $table = _DB_PREFIX_ . 'agmelhorenvio_order_nfe';
        if (!Db::getInstance()->executeS('SHOW TABLES LIKE "' . pSQL($table) . '"')) {
            return [];
        }
        $rows = Db::getInstance()->executeS('SELECT old.id_order FROM `' . $table . '` old '
            . 'LEFT JOIN `' . _DB_PREFIX_ . 'agnfe_document` newdoc ON newdoc.id_order = old.id_order '
            . 'WHERE newdoc.id_order IS NULL ORDER BY old.id_order');
        return array_map('intval', array_column((array) $rows, 'id_order'));
    }
}
