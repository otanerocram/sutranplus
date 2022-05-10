<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
header('Content-Type: text/html; charset=UTF-8');
header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
error_reporting(E_ALL);
date_default_timezone_set('America/Lima');

require('config.php');
$reponseData    = array();
$conexion       = @new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);

if ($conexion->connect_error) {
    die('Error de conectando a la base de datos: ' . $conexion->connect_error);
}

$sqlQuery   = "SELECT `id`, `placa`, `latitud`, `longitud`, `rumbo`, `velocidad`, `evento`, `fecha`, `estado`, `empresa` FROM $table WHERE `estado`='Nuevo' ORDER BY `id` DESC LIMIT $limit;";

$resultado  = $conexion->query($sqlQuery);

$start      = 0;
$end        = 0;

if ($resultado->num_rows > 0) {

    while ($row = $resultado->fetch_array(MYSQLI_ASSOC)) {

        if ($start == 0) {
            $start = $row['id'];
        }

        $plate  = str_replace("-", "", utf8_encode($row['placa']));
        $rumbo  = (int)$row['rumbo'];
        $velo   = (int)$row['velocidad'];
        $epoc   = (int)$row['fecha'];
        $fecha  = date('Y-m-d H:i:s', $epoc);

        $lat    = (float)$row['latitud'];
        $lon    = (float)$row['longitud'];
        $evento = "ER";

        if ($velo <= 3) {
            $evento = "PA";
        }

        $reponseData[] = array(
            "plate"         => $plate,
            "geo"           => [$lat, $lon],
            "direction"     => $rumbo,
            "speed"         => $velo,
            "time_device"   => $fecha,
            "event"         => $evento
        );

        $end = $row['id'];
    }
} else {
    $conexion->close();
    die("Todos los registros han sido enviados! No hay data nueva que enviar...");
}

$payload         = json_encode($reponseData);

print_r($payload);

$curl = curl_init($apiUrl);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLINFO_HEADER_OUT, true);
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
// curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2TLS);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt(
    $curl,
    CURLOPT_HTTPHEADER,
    array(
        "Authorization: Bearer " . $token,
        "Content-Type: application/json"
    )
);

$response   = curl_exec($curl);
$err        = curl_error($curl);

curl_close($curl);

if ($err) {
    $conexion->close();
    die("cURL Error #:" . $err);
}

print_r("<pre><code>" . json_encode($response, JSON_PRETTY_PRINT) . "</code></pre>");

$SqlUpdate = "UPDATE `$table` SET `estado`='Sent' WHERE `id` BETWEEN $end AND $start;";

$mensajeUpdate = "";

if ($conexion->multi_query($SqlUpdate) === TRUE) {
    $mensajeUpdate    = "Registros Enviados!  ";
} else {
    $mensajeUpdate    = "Error insertando en la tabla " . $conexion->error;
    $conexion->close();
    die($mensajeUpdate);
}

$conexion->close();
