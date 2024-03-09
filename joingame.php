<?php
header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header("Access-Control-Allow-Headers: X-Requested-With");

$_POST = json_decode(file_get_contents("php://input"), true);
$data = array("status" => 200);
$gameState = array();
if (!isset($_POST['gameId']) || empty($_POST['gameId'])) {
    $data["status"] = 400;
} else if (!isset($_POST['userName']) || empty($_POST['userName'])) {
    $data["status"] = 401;
} else if (!isset($_POST['userToken']) || empty($_POST['userToken'])) {
    $data["status"] = 401;
}

if ($data['status'] == 400) {
    $data['message'] = "Missing gameId data";
    die(json_encode($data));
} else if ($data['status'] == 401) {
    $data['message'] = "Missing verification data";
    die(json_encode($data));
}

$userName = $_POST['userName'];
$userToken = $_POST['userToken'];
$gameId = $_POST['gameId'];


$md = new Memcached;
$md->addServer("localhost", 45111);
$data = array();

require_once('./verifyToken.php');

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

$idCounter = 0;
foreach ($mdUsersData as $mdUserName => $userData) {
    if (
        $mdUserName != $userName &&
        key_exists('gameId', $userData) &&
        $userData['gameId'] == $gameId
    ) {
        $idCounter = $idCounter + 1;
    }
}

if ($idCounter < 4) {
    $mdUsersData[$userName]['gameId'] = $gameId;
    $md->set('usersData', $mdUsersData, 5 * 3600);
    $data['status'] = 200;
    $data['message'] = "Successfully assigned new gameId.";
    echo (json_encode($data));
    //TODO: setup game joining to room of any state 
    $mdGamesData = $md->get('gamesData');
    if (!empty($mdGamesData)) {
        if (!array_key_exists($gameId, $mdGamesData)) {
            $initialGameState = file_get_contents("../../initialGameState.json");
            $gameState = json_decode($initialGameState, true);
            $gameState['red'] = $userName;

            $mdGamesData[$gameId] = $gameState;
            $md->set('gamesData', $mdGamesData, 5 * 3600);
            die();
        } else {
            //game exists and free spot needs to be found
            $gameState = $mdGamesData[$gameId];
            if ((key_exists('red', $gameState) && $gameState['red'] == $userName) ||
                (key_exists('yellow', $gameState) && $gameState['yellow'] == $userName) ||
                (key_exists('blue', $gameState) && $gameState['blue'] == $userName) ||
                (key_exists('green', $gameState) && $gameState['green'] == $userName)
            ) {
                //do nothing - already has a spot assigned
            } else {
                if (!key_exists('red', $gameState))
                    $gameState['red'] = $userName;
                else if (!key_exists('yellow', $gameState))
                    $gameState['yellow'] = $userName;
                else if (!key_exists('blue', $gameState))
                    $gameState['blue'] = $userName;
                else if (!key_exists('green', $gameState))
                    $gameState['green'] = $userName;

                $mdGamesData[$gameId] = $gameState;
                $md->set('gamesData', $mdGamesData, 5 * 3600);
            }
            die();
        }
    } else {
        $initialGameState = file_get_contents("../../initialGameState.json");
        $gameState = json_decode($initialGameState, true);
        $gameState['red'] = $userName;

        $mdGamesData[$gameId] = $gameState;
        $md->set('gamesData', $mdGamesData, 5 * 3600);
        die();
    }
} else {
    $data['status'] = 400;
    $data['message'] = "Too many users asigned to the gameId";
    die(json_encode($data));
}