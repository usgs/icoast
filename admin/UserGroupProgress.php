<?php
require_once('../includes/userFunctions.php');
require_once('../includes/globalFunctions.php');
//require_once($dbmsConnectionPathDeep);
$dbConnectionFile = DB_file_location();
require_once($dbConnectionFile);

if (!isset($_COOKIE['userId']) || !isset($_COOKIE['authCheckCode'])) {
    print "No Cookie Data<br>Please login to iCoast first.";
//    header('Location: index.php');
    exit;
}

$userId = $_COOKIE['userId'];
$authCheckCode = $_COOKIE['authCheckCode'];

$userData = authenticate_cookie_credentials($DBH, $userId, $authCheckCode, FALSE);
if (!$userData) {
    print "Failed iCoast Authentication<br>Please logout and then back in to iCoast.";
    exit;
}
$authCheckCode = generate_cookie_credentials($DBH, $userId);

if ($userData['account_type'] != 4) {
    print "Insufficient Permissions<br>Access Denied.";
//    header('Location: index.php');
    exit;
}

$userGroupQuery = '
SELECT
u.encrypted_email,
u.encryption_data,
count(ann.annotation_id) AS classifications_completed,
igm.image_group_id,
igm.name,
igm.description,
(FLOOR(ig.group_range / show_every_nth_image)) AS image_group_count
FROM user_groups ug
INNER JOIN users u ON ug.user_id = u.user_id
INNER JOIN user_group_assignments uga ON ug.user_group_id = uga.user_group_id
INNER JOIN image_groups ig ON uga.image_group_id = ig.image_group_id
INNER JOIN image_group_metadata igm ON ig.image_group_id = igm.image_group_id
LEFT JOIN annotations ann ON ug.user_id = ann.user_id AND (ann.image_id BETWEEN ig.image_id AND (ig.image_id + (ig.group_range - 1)) AND ann.annotation_completed = 1)

GROUP BY ug.user_id
ORDER BY igm.image_group_id, image_group_count';

$userGroupResult = $DBH->query($userGroupQuery);
$userGroupProgress = $userGroupResult->fetchAll(PDO::FETCH_ASSOC);

$displayHTML = '';
$currentUserGroup = '';
foreach ($userGroupProgress as $UsersProgress) {
    $userEmail = mysql_aes_decrypt($UsersProgress['encrypted_email'], $UsersProgress['encryption_data']);
    if ($currentUserGroup != $UsersProgress['image_group_id']) {
        $currentUserGroup = $UsersProgress['image_group_id'];
        if (!empty($displayHTML)) {
            $displayHTML .= '</tbody></table>';
        }
        $displayHTML .= "<h3>Image Group Name: {$UsersProgress['name']}</h3>";
        $displayHTML .= '<table>';
        $displayHTML .= "<thead><tr><td>User</td><td>Photos Completed</td><td>Total Photos in Image Group</td></tr></thead><tbody>";
        if ($UsersProgress['classifications_completed'] == $UsersProgress['image_group_count']) {
            $displayHTML .= "<tr class='completed'><td>$userEmail</td><td>{$UsersProgress['classifications_completed']}</td><td>{$UsersProgress['image_group_count']}</td></tr>";
        } else {
            $displayHTML .= "<tr><td>$userEmail</td><td>{$UsersProgress['classifications_completed']}</td><td>{$UsersProgress['image_group_count']}</td></tr>";
        }
    } else {
        if ($UsersProgress['classifications_completed'] == $UsersProgress['image_group_count']) {
            $displayHTML .= "<tr class='completed'><td>$userEmail</td><td>{$UsersProgress['classifications_completed']}</td><td>{$UsersProgress['image_group_count']}</td></tr>";
        } else {
            $displayHTML .= "<tr><td>$userEmail</td><td>{$UsersProgress['classifications_completed']}</td><td>{$UsersProgress['image_group_count']}</td></tr>";
        }
    }
}
?>
<!DOCTYPE html>
<html>
    <head>
        <title>iCoast: User Group Progress</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            table {
                border-collapse: collapse;
                border: 2px solid black;
            }
            td {
                border: 1px solid black;
                padding: 0 5px 0 5px;
            }
            .completed {
                color: green;
            }
        </style>
    </head>
    <body>
        <?php print $displayHTML; ?>
    </body>
</html>

