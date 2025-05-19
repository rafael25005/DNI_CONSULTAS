<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$token = 'apis-token-14955.Ikgf5KtkQqIpqdM1enyWlqCohcKIVHKv';
$type = isset($_GET['type']) ? $_GET['type'] : '';
$number = isset($_GET['number']) ? $_GET['number'] : '';

if (empty($type) || !in_array($type, ['dni', 'ruc'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo de consulta inválido']);
    exit;
}

if (empty($number) || !preg_match('/^[0-9]+$/', $number)) {
    http_response_code(400);
    echo json_encode(['error' => 'Número inválido']);
    exit;
}

$curl = curl_init();
$url = $type === 'dni' 
    ? 'https://api.apis.net.pe/v2/reniec/dni?numero=' . $number
    : 'https://api.apis.net.pe/v2/sunat/ruc?numero=' . $number;

curl_setopt_array($curl, array(
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => 0,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 2,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => array(
        'Referer: https://apis.net.pe/',
        'Authorization: Bearer ' . $token
    ),
));

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$err = curl_error($curl);
curl_close($curl);

if ($err || $httpCode !== 200) {
    http_response_code($httpCode === 200 ? 500 : $httpCode);
    echo json_encode(['error' => $err ?: 'Error en la API (código: ' . $httpCode . ')']);
    exit;
}

// Procesar respuesta
$data = json_decode($response, true);
if ($type === 'dni' && !empty($data)) {
    // Extraer nombres y apellidos
    $nombres = isset($data['nombres']) ? $data['nombres'] : 
               (isset($data['nombre']) ? $data['nombre'] : 
               (isset($data['nombreCompleto']) ? $data['nombreCompleto'] : 'N/A'));
    $apellidoPaterno = isset($data['apellidoPaterno']) ? $data['apellidoPaterno'] : 'N/A';
    $apellidoMaterno = isset($data['apellidoMaterno']) ? $data['apellidoMaterno'] : 'N/A';
    
    // Estandarizar respuesta
    $data = [
        'numeroDocumento' => isset($data['numeroDocumento']) ? $data['numeroDocumento'] : $number,
        'nombres' => $nombres,
        'apellidoPaterno' => $apellidoPaterno,
        'apellidoMaterno' => $apellidoMaterno
    ];
    
    // Verificar si hay datos válidos
    if ($nombres === 'N/A' && $apellidoPaterno === 'N/A' && $apellidoMaterno === 'N/A') {
        echo json_encode(['error' => 'No se encontraron datos para el DNI ' . $number]);
        exit;
    }
} elseif ($type === 'ruc' && !empty($data)) {
    $data = [
        'numeroDocumento' => isset($data['numeroDocumento']) ? $data['numeroDocumento'] : $number,
        'razonSocial' => isset($data['razonSocial']) ? $data['razonSocial'] : 'N/A',
        'estado' => isset($data['estado']) ? $data['estado'] : 'N/A',
        'condicion' => isset($data['condicion']) ? $data['condicion'] : 'N/A'
    ];
} else {
    echo json_encode(['error' => 'Respuesta de API vacía o inválida']);
    exit;
}

echo json_encode($data);
?>