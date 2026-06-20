<?php
require_once 'db_connect.php';

// 1. 安全檢查：未登入或沒傳送好友 ID 就踢回大廳
if (!isset($_SESSION['user_id']) || !isset($_POST['friend_id'])) {
    header("Location: lobby.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$friend_id = intval($_POST['friend_id']);

// 不能挑戰自己
if ($current_user_id === $friend_id) {
    echo "<script>alert('不能挑戰自己喔！'); window.location.href='lobby.php';</script>";
    exit;
}

// 2. 檢查雙方是否已經有「進行中(playing)」的房間，避免重複開房
$check_room_sql = "
    SELECT id FROM games 
    WHERE ((black_player_id = ? AND white_player_id = ?) 
       OR (black_player_id = ? AND white_player_id = ?)) 
      AND status = 'playing'
    LIMIT 1
";
$stmt_check = $mysqli->prepare($check_room_sql);
$stmt_check->bind_param("iiii", $current_user_id, $friend_id, $friend_id, $current_user_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows > 0) {
    // 如果已經有房間了，直接取得該房間 ID 並導向遊戲頁面
    $room = $result_check->fetch_assoc();
    $game_id = $room['id'];
    $stmt_check->close();
    $mysqli->close();
    header("Location: game.php?game_id=" . $game_id);
    exit;
}
$stmt_check->close();


// 3. 如果沒有進行中的房間，則建立一個全新房間
// 發起人預設為黑棋(black_player_id)，被挑戰者為白棋(white_player_id)
// current_turn 填寫黑棋的 ID，代表黑棋先下
$status = 'playing';
$insert_game_sql = "
    INSERT INTO games (black_player_id, white_player_id, current_turn, status) 
    VALUES (?, ?, ?, ?)
";

$stmt_insert = $mysqli->prepare($insert_game_sql);
// 第三個參數 current_turn 代入 $current_user_id (黑棋)
$stmt_insert->bind_param("iiis", $current_user_id, $friend_id, $current_user_id, $status);

if ($stmt_insert->execute()) {
    // 取得剛剛自動生成的遊戲房間 ID (game_id)
    $new_game_id = $mysqli->insert_id;
    
    $stmt_insert->close();
    $mysqli->close();
    
    // 成功建立後，直接帶玩家進入遊戲房間，並傳遞房間 ID
    header("Location: game.php?game_id=" . $new_game_id);
    exit;
} else {
    $stmt_insert->close();
    $mysqli->close();
    echo "<script>alert('建立房間失敗，請稍後再試。'); window.location.href='lobby.php';</script>";
    exit;
}
?>
