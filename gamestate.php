<?php
header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header("Access-Control-Allow-Headers: X-Requested-With");

$_POST = json_decode(file_get_contents("php://input"), true);
$data = array("status" => 200);
$gameState = array();
if (isset($_POST['gameState']) && !empty($_POST['gameState'])) {
    $gameState = (array) $_POST['gameState'];
}
if (!isset($_POST['userName']) || empty($_POST['userName'])) {
    $data["status"] = 400;
}
if (!isset($_POST['userToken']) || empty($_POST['userToken'])) {
    $data["status"] = 400;
}

if ($data['status'] == 400) {
    $data['message'] = "Missing POST data";
    die(json_encode($data));
}

$userName = $_POST['userName'];
$userToken = $_POST['userToken'];


$md = new Memcached;
$md->addServer("localhost", 45111);
$data = array();

require_once('./verifyToken.php');

$mdUsersData = $md->get('usersData');
if (empty($mdUsersData)) {
    $data['status'] = 400;
    $data['message'] = 'Failed to get user data. Make sure to handshake first.';
    die(json_encode($data));
}
if (verifyToken($mdUsersData, $userName, $userToken) != 201) {
    $data['status'] = 400;
    $data['message'] = 'Failed to verify user. Make sure to handshake first.';
    die(json_encode($data));
}


$req_timestamp = time();
$mdGamesData = $md->get('gamesData');
if (!empty($mdGamesData)) { // aaa is to be replaced by game id 
    if (!array_key_exists('aaa', $mdGamesData)) {

        $initialGameState = file_get_contents("../../initialGameState.json");
        $gameState = json_decode($initialGameState, true);
        $gameState['red'] = $userName;

        $mdGamesData['aaa'] = $gameState;
        $md->set('gamesData', $mdGamesData, 5 * 3600);
        die(json_encode($gameState));
    }
} else {

    $initialGameState = file_get_contents("../../initialGameState.json");
    $gameState = json_decode($initialGameState, true);
    $gameState['red'] = $userName;

    $mdGamesData['aaa'] = $gameState;
    $md->set('gamesData', $mdGamesData, 5 * 3600);
    die(json_encode($gameState));
}

while (time() - $req_timestamp < 5) {
    $mdGamesData = (array) $md->get('gamesData');
    $mdGameState = (array) $mdGamesData['aaa'];

    if (
        is_array($mdGameState) &&
        is_array($gameState) &&
        array_key_exists('turn', $mdGameState) &&
        array_key_exists('action', $mdGameState) &&
        array_key_exists('diceValue', $mdGameState) &&
        array_key_exists('pawns', $mdGameState) &&
        array_key_exists('turn', $gameState) &&
        array_key_exists('action', $gameState) &&
        array_key_exists('diceValue', $gameState) &&
        array_key_exists('pawns', $gameState)

    ) {
        if (
            $mdGameState['turn'] == $gameState['turn'] &&
            $mdGameState['action'] == $gameState['action'] &&
            $mdGameState['diceValue'] == $gameState['diceValue'] &&
            $mdGameState['pawns'] == $gameState['pawns']
        ) {
            usleep(250_000);
            continue;
        }
    }

    die(json_encode($mdGameState));
}
die(json_encode($gameState));
