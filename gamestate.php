<?php
header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header("Access-Control-Allow-Headers: X-Requested-With");

$_POST = json_decode(file_get_contents("php://input"), true);
if (!isset($_POST['userName']) || empty($_POST['userName'])) {
    $data = array("status" => 400, "message" => "No userName data in POST");
    $JSON_data = json_encode($data);
    die($JSON_data);
}
$userName = $_POST['userName'];

$hasToken = false;
if (isset($_POST['userToken']) && !empty($_POST['userToken'])) {
    $hasToken = true;
}

$md = new Memcached;
$md->addServer("localhost", 45111);
$data = array();
