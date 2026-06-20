<?php
// forfeit.php - 主動認輸，對手獲勝
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '未登入'], JSON_UNESCAPED_UNICODE);
    exit();
}

$user_id = intval($_SESSION['user_id']);
$game_id = isset($_POST['game_id']) ? intval($_POST['game_id']) : 0;

if ($game_id <= 0) {
    echo json_encode(['success' => false, 'message' => '參數錯誤'], JSON_UNESCAPED_UNICODE);
    exit();
}

// 查詢該對局，確認是進行中（playing）且此人是玩家之一
$stmt = $mysqli->prepare("SELECT black_player_id, white_player_id FROM games WHERE id = ? AND status = 'playing'");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$game = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$game) {
    echo json_encode(['success' => false, 'message' => '遊戲不存在或已結束'], JSON_UNESCAPED_UNICODE);
    exit();
}

$black_id = intval($game['black_player_id']);
$white_id = intval($game['white_player_id']);

// 確認此人是該局玩家
if ($user_id !== $black_id && $user_id !== $white_id) {
    echo json_encode(['success' => false, 'message' => '您不是此遊戲的玩家'], JSON_UNESCAPED_UNICODE);
    exit();
}

// 認輸者離開，對手獲勝
$winner_id = ($user_id === $black_id) ? $white_id : $black_id;
$loser_id  = $user_id;

// 更新遊戲狀態為 finished，記錄贏家
$stmt_game = $mysqli->prepare("UPDATE games SET status = 'finished', winner_id = ? WHERE id = ?");
$stmt_game->bind_param("ii", $winner_id, $game_id);
$stmt_game->execute();
$stmt_game->close();

// 贏家勝場 +1
$stmt_win = $mysqli->prepare("UPDATE users SET wins = wins + 1 WHERE id = ?");
$stmt_win->bind_param("i", $winner_id);
$stmt_win->execute();
$stmt_win->close();

// 輸家敗場 +1
$stmt_loss = $mysqli->prepare("UPDATE users SET losses = losses + 1 WHERE id = ?");
$stmt_loss->bind_param("i", $loser_id);
$stmt_loss->execute();
$stmt_loss->close();

// 紀錄此局為「認輸結束」，供對手前端彈出視窗
$mysqli->query("CREATE TABLE IF NOT EXISTS forfeits (game_id INT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$stmt_forfeit = $mysqli->prepare("INSERT IGNORE INTO forfeits (game_id) VALUES (?)");
$stmt_forfeit->bind_param("i", $game_id);
$stmt_forfeit->execute();
$stmt_forfeit->close();

echo json_encode(['success' => true, 'message' => '已認輸，對手獲得勝利。'], JSON_UNESCAPED_UNICODE);
$mysqli->close();
