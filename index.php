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

//TODO: Response should later return the room id for later quick in-game checks.
//      The room id should also be stored in the correct memcached user profile

$mdUsersData = $md->get('usersData');

if (array_key_exists($userName, $mdUsersData)) {
    //profile with that name exists
    if (!$hasToken) {
        //profile for this user already exists - send retry status
        $data['status'] = 401;
        $data['message'] = "Profile already exists for this user - Try different name.";
        die(json_encode($data));
    }
    $userToken = $_POST['userToken'];
    $mdUserData = $mdUsersData[$userName];
    if ($mdUserData['userToken'] == $userToken) {
        //handshaking user matches the profile
        $data['status'] = 200;
        $data['userToken'] = $userToken;
        $data['message'] = "Found existing profile - Successful handshake.";
        die(json_encode($data));
    } else {
        //handshaking userToken doesn't match the profile
        $data['status'] = 402;
        $data['message'] = "Error: userToken doesn't or the userToken wasn't sent.";
        die(json_encode($data));
    }
} else {
    //no such profile exists - create new profile
    $userToken = getRandomStringRandomInt(8);
    $mdUsersData[$userName] = array('userToken' => $userToken);
    $md->set('usersData', $mdUsersData, 3600);

    $data['status'] = 200;
    $data['userToken'] = $userToken;
    $data['message'] = "No matching profile exists, created a new one - Successful handshake.";

    die(json_encode($data));
}
