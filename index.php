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

function getRandomStringRandomInt($length = 16)
{
    $stringSpace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $pieces = [];
    $max = mb_strlen($stringSpace, '8bit') - 1;
    for ($i = 0; $i < $length; ++$i) {
        $pieces[] = $stringSpace[random_int(0, $max)];
    }
    return implode('', $pieces);
}

$md = new Memcached;
$md->addServer("localhost", 45111);
$data = array();


if ($hasToken) {
    $userToken = $_POST['userToken'];
    $mdUsersData = $md->get('userData');
    //check if user with that name has a profile stored
    if (array_key_exists($userName, $mdUserData)) {
        $mdUserData = $mdUserData[$userName];
        if ($mdUserData['userToken'] == $userToken) {
            //handshaking user matches the profile
            $data['status'] = 200;
            $data['userToken'] = $userToken;
        } else {
            //handshaking user doesn't matche the profile
            $data['status'] = 401;
            $data['message'] = "userToken doesn't match the userName";
        }
    } else {
        //profile doesn't exist - new user
    }
}


$JSON_data = json_encode($data);
die($JSON_data);
