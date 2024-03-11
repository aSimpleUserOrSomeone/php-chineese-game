<?php
header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header("Access-Control-Allow-Headers: X-Requested-With");

$_POST = json_decode(file_get_contents("php://input"), true);
if (!isset($_POST['userName']) || empty($_POST['userName'])) {
    $data = array("status" => 400, "message" => "No userName data in POST");
    $JSON_data = json_encode($data);
    die($JSON_data);
}
$userName = $_POST['userName'];

$userToken = '';
if (isset($_POST['userToken']) && !empty($_POST['userToken'])) {
    $userToken = $_POST['userToken'];
}

require_once('verifyToken.php');
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

$mdUsersData = $md->get('usersData');
if (empty($mdUsersData)) {
    //no profiles exist
    if (empty($userToken))
        $userToken = getRandomStringRandomInt(8);
    $md->set('usersData', array($userName => array('userToken' => $userToken)), 3600);
    $data['status'] = 200;
    $data['userToken'] = $userToken;
    $data['message'] = "No matching profile exists, created a new one - Successful handshake.";
    die(json_encode($data));
}

$verifyCode = verifyToken($mdUsersData, $userName, $userToken);
switch ($verifyCode) {
    case 201:
        //handshaking user matches the profile
        $data['status'] = 200;
        $data['userToken'] = $userToken;
        $data['message'] = "Found existing profile - Successful handshake.";
        die(json_encode($data));
        break;

    case 401:
        //profile found - token doesnt match
        $data['status'] = 401;
        $data['message'] = "Token doesn't match for the existing profile.";
        die(json_encode($data));
        break;

    case 404:
        //no such profile exists - create new profile
        if (empty($userToken)) $userToken = getRandomStringRandomInt(8);
        $mdUsersData[$userName] = array('userToken' => $userToken);
        $md->set('usersData', $mdUsersData, 3600);
        $data['status'] = 200;
        $data['userToken'] = $userToken;
        $data['message'] = "No matching profile exists, created a new one - Successful handshake.";
        die(json_encode($data));
        break;
}
