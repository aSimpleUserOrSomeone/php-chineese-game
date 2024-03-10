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

$md = new Memcached;
$md->addServer("localhost", 45111);
$data = array();
$colors = ['red', 'yellow', 'blue', 'green'];

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

$gameId = $mdUsersData[$userName]['gameId'];
$mdGamesData = $md->get('gamesData');
if (array_key_exists($gameId, $mdGamesData) == false) {
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
    if (!array_key_exists('action', $mdGameData) || $mdGameData['action'] != 'dice') {
        $data['status'] = 400;
        $data['message'] = "It's not time to throw the dice!";
        die(json_encode($data));
    } else if ($playerColor != $mdGameData['turn']) {
        $data['status'] = 400;
        $data['message'] = "It's not your time to throw.";
        die(json_encode($data));
    }

    //TODO: Check if has any valid move after throwing dice! 

    $diceValue = rand(1, 6);
    $mdGameData['diceValue'] = $diceValue;
    $mdGameData['action'] = 'move';

    if (hasValidMove($diceValue, $mdGameData['pawns'], $playerColor)) {
        $mdGameData['action'] = 'move';
    } else {
        $mdGameData['turn'] = getNextPlayerColor($playerColor, $mdGameData);
    }
    $mdGamesData[$gameId] = $mdGameData;
    $md->set('gamesData', $mdGamesData, 5 * 60);


    $data['status'] = 200;
    $data['message'] = "Player rolled dice.";
    die(json_encode($data));
} else if ($action == 'move') {
    if (!array_key_exists('action', $mdGameData) || $mdGameData['action'] != 'move') {
        $data['status'] = 400;
        $data['message'] = "It's not time to move!";
        die(json_encode($data));
    } else if ($playerColor != $mdGameData['turn']) {
        $data['status'] = 400;
        $data['message'] = "It's not your time to move.";
        die(json_encode($data));
    }

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

    $targetDestination = $position + $mdGameData['diceValue'];
    if ($position < 40 && $targetDestination < 40) { //check regular move
        $targetPawn = getPosPawn($targetDestination, $mdGameData['pawns']);
        if ($targetPawn == null) {
            $mdGameData['pawns'] = movePawn($position, $targetDestination, $mdGameData['pawns']);
        } else {
            if ($targetPawn['color'] == $playerColor) { // invalid move
                $data['status'] = 400;
                $data['message'] = "Can't move on your own pawn.";
                die(json_encode($data));
            }
            $mdGameData['pawns'] = resetPawn($targetDestination, $targetPawn['color'], $mdGameData['pawns']);
            $mdGameData['pawns'] = movePawn($position, $targetDestination, $mdGameData['pawns']);
        }
        //TODO: HERE NEXT PLAYER TURN !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
        $mdGamesData[$gameId] = $mdGameData;
        $md->set('gamesData', $mdGamesData, 5 * 60);

        $data['status'] = 200;
        $data['message'] = "Successful move.";
        die(json_encode($data));
    } else if ($position < 56) { //check if entering starting fields
        //calculate the move
        switch ($playerColor) {
            case 'red':
                if ($position < 40 && $targetDestination >= 40)
                    $targetDestination += 0;
                //check if exceeding own end fields
                $targetDestination = calculateExceedEnd(43, $position, $targetDestination, $mdGameData['pawns']);
                break;
            case 'yellow':
                if ($position < 10 && $targetDestination >= 10)
                    $targetDestination += 34;
                //check if exceeding own end fields
                $targetDestination = calculateExceedEnd(47, $position, $targetDestination, $mdGameData['pawns']);
                break;
            case 'blue':
                if ($position < 20 && $targetDestination >= 20)
                    $targetDestination += 28;
                //check if exceeding own end fields
                $targetDestination = calculateExceedEnd(51, $position, $targetDestination, $mdGameData['pawns']);
                break;
            case 'red':
                if ($position < 30 && $targetDestination >= 30)
                    $targetDestination += 22;
                //check if exceeding own end fields
                $targetDestination = calculateExceedEnd(55, $position, $targetDestination, $mdGameData['pawns']);
                break;
        }
        //execute the move
        $mdGameData['pawns'] = movePawn($position, $targetDestination, $mdGameData);
        $mdGamesData[$gameId] = $mdGameData;
        $md->set('gamesData', $mdGamesData, 5 * 60);
        $data['status'] = 200;
        $data['message'] = "Successfull move.";
        die(json_encode($data));
    } else if ($position < 72) { //check coming out of base
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

function hasValidMove(int $diceValue, array $pawns, string $color): bool
{
    if ($diceValue == 1 || $diceValue == 6)  //check from starting position
        return true;

    foreach ($pawns as $pawn) {
        if ($pawn['color'] == $color && $pawn['pos'] < 56) { //check if any player pawn out of base
            return true;
        }
    }
    return false;
}

function getNextPlayerColor(string $playerColor, $gameState): string
{
    $colors = ['red', 'yellow', 'blue', 'green'];
    $targetPlayer = '';
    $colorIndex = array_search($playerColor, $colors);
    $i = $colorIndex + 1;
    while (true) {
        if ($i >= 4) $i -= 4;

        if (array_key_exists($colors[$i], $gameState))
            return $colors[$i];

        $i += 1;
    }

    return $targetPlayer;
}
