<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../classes/AgNfeDocument.php';

function check($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$xmlMethod = new ReflectionMethod(AgNfeDocument::class, 'validateXml');
$pdfMethod = new ReflectionMethod(AgNfeDocument::class, 'validatePdf');
$path = tempnam(sys_get_temp_dir(), 'agnfe-test-');

try {
    $key = str_repeat('1', 44);
    file_put_contents($path, '<nfeProc><NFe><infNFe Id="NFe' . $key
        . '"><ide><nNF>123</nNF></ide></infNFe></NFe></nfeProc>');
    $details = $xmlMethod->invoke(null, $path);
    check($details['key'] === $key && $details['number'] === '123', 'NF-e válida não foi lida.');

    file_put_contents($path, '<documento><numero>123</numero></documento>');
    try {
        $xmlMethod->invoke(null, $path);
        throw new RuntimeException('XML sem NF-e foi aceito.');
    } catch (Exception $e) {
        check($e->getMessage() !== 'XML sem NF-e foi aceito.', $e->getMessage());
    }

    file_put_contents($path, '<!DOCTYPE doc [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><doc/>');
    try {
        $xmlMethod->invoke(null, $path);
        throw new RuntimeException('XML com DOCTYPE foi aceito.');
    } catch (Exception $e) {
        check($e->getMessage() !== 'XML com DOCTYPE foi aceito.', $e->getMessage());
    }

    file_put_contents($path, '%PDF-1.4' . "\n" . '%%EOF');
    check($pdfMethod->invoke(null, $path) === true, 'PDF válido foi recusado.');
    file_put_contents($path, 'not a PDF');
    try {
        $pdfMethod->invoke(null, $path);
        throw new RuntimeException('Arquivo sem cabeçalho PDF foi aceito.');
    } catch (Exception $e) {
        check($e->getMessage() !== 'Arquivo sem cabeçalho PDF foi aceito.', $e->getMessage());
    }

    echo "agnfe validation: OK\n";
} finally {
    @unlink($path);
}
