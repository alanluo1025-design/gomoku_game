<?php
// lobby.php (部分核心撈取程式碼與 HTML 示意)
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

// 讀取你的 lobby.html 畫面 
$html_content = file_get_contents('lobby.html');

$html_content = str_replace('{{username}}',$_SESSION['username'], $html_content);
$html_content = str_replace('{{wins}}',$_SESSION['wins'], $html_content);
$html_content = str_replace('{{losses}}',$_SESSION['losses'], $html_content);

/*
$friends_query = "
    SELECT u.id, u.username, u.wins, u.losses 
    FROM friends f
    JOIN users u ON (f.user_id = u.id AND f.friend_id = ?) OR (f.friend_id = u.id AND f.user_id = ?)
    WHERE f.status = 'accepted'
";
$stmt1 = $mysqli->prepare($friends_query);
$stmt1->bind_param("ii", $current_user_id, $current_user_id);
$stmt1->execute();
$friends_result = $stmt1->get_result();


// --- 2. 撈取「收到的好友請求名單」(friend_id 是自己 且 status = 'pending') ---
$requests_query = "
    SELECT f.id AS request_id, u.username 
    FROM friends f
    JOIN users u ON f.user_id = u.id
    WHERE f.friend_id = ? AND f.status = 'pending'
";
$stmt2 = $mysqli->prepare($requests_query);
$stmt2->bind_param("i", $current_user_id);
$stmt2->execute();
$requests_result = $stmt2->get_result();
*/
// 輸出最終畫面
echo $html_content;
?>
