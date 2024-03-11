<?php
$GLOBALS['colors'] = ['red', 'yellow', 'blue', 'green'];
function verifyToken(array $mdUsersData, string $userName, string $userToken = ''): int
{
    //will return state
    //201 - profile found, with matching token
    //401 - profile found, without matching token
    //404 - no profile found

    if (!array_key_exists($userName, $mdUsersData))
        return 404;

    $userData = $mdUsersData[$userName];
    if ($userData['userToken'] == $userToken)
        return 201;
    else
        return 401;
}

function removeUser(string $userName, Memcached $md, int $gameId)
{
    $mdGamesData = $md->get('gamesData');
    if (!array_key_exists('actionTimestamp', $mdGamesData[$gameId]))
        return;

    $mdGameData = $mdGamesData[$gameId];
    if ($mdGamesData != null) {
        foreach ($GLOBALS['colors'] as $color) {
            if (array_key_exists($color, $mdGameData) && $mdGameData[$color]['userName'] == $userName) {
                unset($mdGameData[$color]);
                unset($mdGameData['actionTimestamp']);
            }
            $mdGamesData[$gameId] = $mdGameData;
        }

        $md->set('gamesData', $mdGamesData);
    }

    $mdUsersData = $md->get('usersData');
    if ($mdUsersData != null && array_key_exists($userName, $mdUsersData)) {
        unset($mdUsersData[$userName]);
        $md->set('usersData', $mdUsersData);
    }
}


function getNextPlayerColor(string $playerColor, $gameState): string
{
    $colors = $GLOBALS['colors'];
    $colorIndex = array_search($playerColor, $colors);
    $i = $colorIndex + 1;
    while (true) {
        if ($i >= 4) $i -= 4;

        if (array_key_exists($colors[$i], $gameState))
            return $colors[$i];

        $i += 1;
    }
}
