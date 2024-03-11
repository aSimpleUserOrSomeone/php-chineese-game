<?php
header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header("Access-Control-Allow-Headers: X-Requested-With");
error_reporting(E_ERROR);

$_POST = json_decode(file_get_contents("php://input"), true);
$gameState = new GameState();
$gameState->init();

class GameState
{
    function init()
    {
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

        require_once('./utils.php');

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
        $gameId = strval($mdUsersData[$userName]['gameId']);
        $mdGamesData = $md->get('gamesData');
        if (empty($mdGamesData) || !array_key_exists($gameId, $mdGamesData)) {
            $data = array('status' => 400, 'message' => "This game doesn't exist. Try joining the game first.");
            die(json_encode($data));
        }

        $playerColor;
        foreach ($GLOBALS['colors'] as $color) {
            if (array_key_exists($color, $mdGamesData[$gameId]) && $mdGamesData[$gameId][$color]['userName'] == $userName) {
                $playerColor = $color;
                break;
            }
        }

        while (time() - $req_timestamp < 15) {
            $mdGamesData = $md->get('gamesData');
            $mdGameState = $mdGamesData[$gameId];

            if (
                array_key_exists('actionTimestamp', $mdGameState) &&
                (time() - $mdGameState['actionTimestamp'] > 450) &&
                array_key_exists($mdGameState['turn'], $mdGameState)
            ) {
                //player is afk kick them
                removeUser($mdGameState[$mdGameState['turn']]['userName'], $md, $gameId);
                $mdGamesData =  $md->get('gamesData');
                $mdGameState =  $mdGamesData[$gameId];
                $mdGameState['turn'] = getNextPlayerColor($playerColor, $mdGameState);
                $mdGameState['action'] = 'dice';
                $mdGamesData[$gameId] = $mdGameState;
                $md->set('gamesData', $mdGamesData, 3600);
            }

            if ( //compare the 2 game states
                $this->arrayRecursiveDiff($mdGameState, $gameState) == array() &&
                $this->arrayRecursiveDiff($gameState, $mdGameState) == array()
            ) {
                usleep(250_000);
                continue;
            }
            break;
        }
        $data = array('status' => 200, 'gameState' => $mdGameState);
        die(json_encode($data));
    }


    function arrayRecursiveDiff(array $aArray1, array $aArray2)
    {
        $aReturn = array();

        foreach ($aArray1 as $mKey => $mValue) {
            if (array_key_exists($mKey, $aArray2)) {
                if (is_array($mValue)) {
                    $aRecursiveDiff = $this->arrayRecursiveDiff($mValue, $aArray2[$mKey]);
                    if (count($aRecursiveDiff)) {
                        $aReturn[$mKey] = $aRecursiveDiff;
                    }
                } else {
                    if ($mValue != $aArray2[$mKey]) {
                        $aReturn[$mKey] = $mValue;
                    }
                }
            } else {
                $aReturn[$mKey] = $mValue;
            }
        }
        return $aReturn;
    }
}
