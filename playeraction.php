<?php
header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header("Access-Control-Allow-Headers: X-Requested-With");

$_POST = json_decode(file_get_contents("php://input"), true);
$data = array("status" => 200);
$gameState = array();
if (!isset($_POST['action']) || empty($_POST['action'])) {
    $data["status"] = 400;
} else if ($_POST['action'] == 'move' && (!isset($_POST['position']) || empty($_POST['position']))) {
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
$action = $_POST['action'];

include_once('./globals.php');
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
//this shit above appears so often like wtf
if (!array_key_exists('gameId', $mdUsersData[$userName])) {
    $data['status'] = 400;
    $data['message'] = "Player is not in a game. Join a game first.";
    die(json_encode($data));
}

$gameId = $mdUsersData[$userName];
$mdGamesData = $md->get('gamesData');
if (!array_key_exists($gameId, $mdGamesData)) {
    $data['status'] = 400;
    $data['message'] = "This game doesn't exist. Try joining a game first to create one.";
    die(json_encode($data));
}

$mdGameData = $mdGamesData[$gameId];
$playerColor = null;
foreach ($colors as $color) {
    if (array_key_exists($color, $mdGameData) && $mdGameData[$color]['userName'] == $userName) {
        $playerColor = $color;
        break;
    }
}
if ($playerColor == null) {
    $data['status'] = 400;
    $data['message'] = "Couldn't find this player in this room. Try joining the game again.";
    die(json_encode($data));
}

if ($action == 'dice') {
    if (!array_key_exists('action', $mdGameData) | $mdGameData['action'] != 'dice') {
        $data['status'] = 400;
        $data['message'] = "It's not time to throw the dice!";
        die(json_encode($data));
    } else if ($playerColor != $mdGameData['turn']) {
        $data['status'] = 400;
        $data['message'] = "It's not your time to throw.";
        die(json_encode($data));
    }

    //TODO: Check if has any valid move after throwing dice! 

    $diceValue = (rand() & 5) + 1;
    $mdGameData['diceValue'] = $diceValue;
    $mdGameData['action'] = 'move';
    $mdGamesData[$gameId] = $mdGameData;
    $md->set('gamesData', $mdGamesData, 5 * 3600);
} else if ($action == 'move') {
    //fuckery begins here :)
    $position = $_POST['position'];
    //check if position is valid
    $isPosistionValid = false;
    foreach ($mdGameData['pawns'] as $pawn) {
        if ($pawn['pos'] == $position && $pawn['color'] == $playerColor)
            $isPosistionValid = true;
    }
    if (!$isPosistionValid) {
        $data['status'] = 400;
        $data['message'] = "Pawn's position is not valid.";
        die(json_encode($data));
    }

    //calculate the move
    $targetDestination = $position + $mdGameData['diceValue'];
    if ($position < 40) {
    } else if ($position < 56) { //check if entering starting fields
        switch ($playerColor) {
            case 'red':
                if ($position < 40 && $target >= 40)
                    $target += 0;
                //check if exceeding own end fields
                $target = calculateExceedEnd(43, $position, $targetDestination, $mdGameData['pawns']);
                break;
            case 'yellow':
                if ($position < 10 && $target >= 10)
                    $target += 34;
                //check if exceeding own end fields
                $target = calculateExceedEnd(47, $position, $targetDestination, $mdGameData['pawns']);
                break;
            case 'blue':
                if ($position < 20 && $target >= 20)
                    $target += 28;
                //check if exceeding own end fields
                $target = calculateExceedEnd(51, $position, $targetDestination, $mdGameData['pawns']);
                break;
            case 'red':
                if ($position < 30 && $target >= 30)
                    $target += 22;
                //check if exceeding own end fields
                $target = calculateExceedEnd(55, $position, $targetDestination, $mdGameData['pawns']);
                break;
        }
    } else if ($position < 72) {
        if (!($diceValue == 1 || $diceValue == 6)) {
            $data['status'] = 400;
            $data['message'] = "Can't move from start fields if dice not 1 or 6.";
            die(json_encode($data));
        }
        $deployField = 0;
        switch ($playerColor) {
            case 'red':
                $deployField = 0;
                break;
            case 'yellow':
                $deployField = 10;
                break;
            case 'blue':
                $deployField = 20;
                break;
            case 'green':
                $deployField = 30;
                break;
        }

        $targetPawn = getPosPawn($deployField, $mdGameData['pawns']);
        if ($targetPawn == null) {
            $mdGameData['pawns'] = movePawn($position, $deployField, $mdGameData['pawns']);
        } else if ($targetPawn['color'] == $playerColor) {
            $data['status'] = 400;
            $data['message'] = "Can't move on your own pawn.";
            die(json_encode($data));
        } else if ($targetPawn['color'] != $playerColor) {
            $mdGameData['pawns'] = resetPawn($deployField, $playerColor, $mdGameData['pawns']);
            $mdGameData['pawns'] = movePawn($position, $deployField, $mdGameData['pawns']);
        }
    }
} else {
    $data['status'] = 400;
    $data['message'] = "This should never happen.";
    die(json_encode($data));
}

function getPosPawn(int $pos, $pawns)
{
    foreach ($pawns as $pawn) {
        if ($pawn['pos'] == $pos) {
            return $pawn;
        }
    }
    return null;
}

function calculateExceedEnd($endfieldEnd, $position, $target, $pawns)
{
    if ($target > $endfieldEnd) {
        $target = $endfieldEnd;
        while ($target >= $position) {
            if (getPosPawn($target, $pawns) == null)
                break; //found an empty spot on the endfields    
            $target -= 1;
        }
    }
    return $target;
}

function movePawn(int $from, int $to, $pawns)
{
    foreach ($pawns as $pawn) {
        if ($pawn['pos'] == $from) {
            $pawn['pos'] = $to;
            break;
        }
    }
    return $pawns;
}

function resetPawn(int $from, string $color, $pawns)
{
    $baseStart = null;
    switch ($color) {
        case 'red':
            $baseStart = 56;
            break;
        case 'yellow':
            $baseStart = 60;
            break;
        case 'blue':
            $baseStart = 64;
            break;
        case 'green':
            $baseStart = 68;
            break;
    }
    for ($i = 0; $i < 4; $i += 1) {
        if (getPosPawn($baseStart + $i, $pawns) == null) {
            $pawns = movePawn($from, $baseStart + $i, $pawns);
            break;
        }
    }
    return $pawns;
}
