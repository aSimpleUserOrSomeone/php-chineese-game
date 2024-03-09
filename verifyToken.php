<?php
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
