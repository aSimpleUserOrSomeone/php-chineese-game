<?php
header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header("Access-Control-Allow-Headers: X-Requested-With");

$_POST = json_decode(file_get_contents("php://input"), true);
$data = array("status" => 200);
$gameState = array();
if (!isset($_POST['isReady'])) {
    $data["status"] = 400;
} else if (!isset($_POST['userName']) || empty($_POST['userName'])) {
    $data["status"] = 401;
} else if (!isset($_POST['userToken']) || empty($_POST['userToken'])) {
    $data["status"] = 401;
}

if ($data['status'] == 400) {
    $data['message'] = "Missing isReady data";
    die(json_encode($data));
} else if ($data['status'] == 401) {
    $data['message'] = "Missing verification data";
    die(json_encode($data));
}

$userName = $_POST['userName'];
$userToken = $_POST['userToken'];
$isReady = $_POST['isReady'];


$md = new Memcached;
$md->addServer("localhost", 45111);
$data = array();

require_once('./utils.php');

$mdUsersData = $md->get('usersData');
if (empty($mdUsersData)) {
    $data['status'] = 401;
    $data['message'] = 'Failed to get user data. Make sure to handshake first.';
    die(json_encode($data));
}
if (verifyToken($mdUsersData, $userName, $userToken) != 201) {
    $data['status'] = 401;
    $data['message'] = 'Failed to verify user. Make sure to handshake first.';
    die(json_encode($data));
}

$mdUserData = $mdUsersData[$userName];
$gameId = $mdUserData['gameId'];
$mdGamesData = $md->get('gamesData');
$mdGameData = $mdGamesData[$gameId];

if ($mdGameData['action'] != 'wait') {
    $data['status'] = 402;
    $data['message'] = "Cannot change ready state in an active game";
    die(json_encode($data));
}

$data['status'] = 200;
$data['message'] = "Successfully changed ready state";
if (
    array_key_exists('red', $mdGameData) &&
    array_key_exists('userName', $mdGameData['red']) &&
    $mdGameData['red']['userName'] == $userName
) {
    $mdGameData['red']['isReady'] = $isReady;
} else if (
    array_key_exists('yellow', $mdGameData) &&
    array_key_exists('userName', $mdGameData['yellow']) &&
    $mdGameData['yellow']['userName'] == $userName
) {
    $mdGameData['yellow']['isReady'] = $isReady;
} else if (
    array_key_exists('blue', $mdGameData) &&
    array_key_exists('userName', $mdGameData['blue']) &&
    $mdGameData['blue']['userName'] == $userName
) {
    $mdGameData['blue']['isReady'] = $isReady;
} else if (
    array_key_exists('green', $mdGameData) &&
    array_key_exists('userName', $mdGameData['green']) &&
    $mdGameData['green']['userName'] == $userName
) {
    $mdGameData['green']['isReady'] = $isReady;
} else {
    $data['status'] = 402;
    $data['message'] = "Error changing state.";
    die(json_encode($data));
}

//checking if the game can be started
$allReady = true;
$playerCount = 0;
$colors = ['red', 'yellow', 'blue', 'green'];

foreach ($colors as $color) {
    if (array_key_exists($color, $mdGameData)) {
        $playerCount += 1;
        if (
            !array_key_exists('isReady', $mdGameData[$color]) ||
            $mdGameData[$color]['isReady'] == false
        ) {
            $allReady = false;
            break;
        }
    }
}

if ($allReady && $playerCount > 1) {
    $mdGameData['action'] = 'dice';
    foreach ($colors as $colors) {
        if (array_key_exists($color, $mdGameData)) {
            $mdGameData['turn'] = $color;
            $data['message'] += " The game is starting.";
            break;
        }
    }
}

$mdGamesData[$gameId] = $mdGameData;
$md->set('gamesData', $mdGamesData, 3600);
die(json_encode($data));
