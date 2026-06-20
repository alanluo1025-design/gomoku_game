<?php
require_once 'db_connect.php';

// 安全檢查
if (!isset($_SESSION['user_id']) || !isset($_GET['game_id'])) {
    header("Location: lobby.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$game_id = intval($_GET['game_id']);

// 撈取這場遊戲的詳細資訊，並抓出黑方與白方的名字
$game_sql = "
    SELECT *, 
           u1.username AS black_name, 
           u2.username AS white_name 
    FROM games g
    JOIN users u1 ON g.black_player_id = u1.id
    JOIN users u2 ON g.white_player_id = u2.id
    WHERE g.id = ? AND g.status = 'playing'
";

$stmt = $mysqli->prepare($game_sql);
$stmt->bind_param("i", $game_id);
$stmt->execute();
$game_data = $stmt->get_result()->fetch_assoc();

if (!$game_data) {
    echo "<script>alert('找不到該遊戲房間，或對局已結束。');window.location.href='lobby.php';</script>";
    exit;
}

// 判定當前玩家是黑棋、白棋還是不相干的觀戰者
$identity = 'spectator';
if ($current_user_id === $game_data['black_player_id']) {
    $identity = 'black';
} else if ($current_user_id === $game_data['white_player_id']) {
    $identity = 'white';
}

// 如果你想做前後端分離，可以在這裡載入 game.html 模板
$html_template = file_get_contents('game.html');

// 取得目前登入者的顯示名稱（用於聊天室顯示）
$my_username = '';
if ($identity === 'black') {
    $my_username = $game_data['black_name'];
} else if ($identity === 'white') {
    $my_username = $game_data['white_name'];
} else {
    $my_username = '觀戰者';
}

// 替換畫面上的基本資訊
$html_template = str_replace('{{GAME_ID}}',     $game_id,                             $html_template);
$html_template = str_replace('{{BLACK_NAME}}',  htmlspecialchars($game_data['black_name']), $html_template);
$html_template = str_replace('{{WHITE_NAME}}',  htmlspecialchars($game_data['white_name']), $html_template);
$html_template = str_replace('{{IDENTITY}}',    $identity,                            $html_template);
$html_template = str_replace('{{MY_USER_ID}}',  $current_user_id,                    $html_template);
$html_template = str_replace('{{MY_USERNAME}}', htmlspecialchars($my_username),      $html_template);

echo $html_template;

$stmt->close();
$mysqli->close();

