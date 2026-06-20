<?php
require_once 'db_connect.php';

// 設定回傳格式為 JSON
header('Content-Type: application/json');

// 1. 基本安全與參數檢查
if (!isset($_SESSION['user_id']) || !isset($_POST['game_id']) || !isset($_POST['row']) || !isset($_POST['col'])) {
    echo json_encode(['status' => 'error', 'message' => '參數錯誤或未登入']);
    exit;
}

$user_id    = intval($_SESSION['user_id']);
$game_id    = intval($_POST['game_id']);
$pos_x      = intval($_POST['row']);
$pos_y      = intval($_POST['col']);

// 檢查座標是否超出 15x15 棋盤範圍
if ($pos_x < 0 || $pos_x > 14 || $pos_y < 0 || $pos_y > 14) {
    echo json_encode(['status' => 'error', 'message' => '落子位置無效']);
    exit;
}

// 2. 撈取遊戲房間狀態
$game_sql = "SELECT black_player_id, white_player_id, current_turn, status FROM games WHERE id = ?";
$stmt = $mysqli->prepare($game_sql);
$stmt->bind_param("i", $game_id);
$stmt->execute();
$game = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$game) {
    echo json_encode(['status' => 'error', 'message' => '找不到遊戲房間']);
    exit;
}

if ($game['status'] === 'finished') {
    echo json_encode(['status' => 'error', 'message' => '遊戲已結束']);
    exit;
}

if ($game['current_turn'] !== $user_id) {
    echo json_encode(['status' => 'error', 'message' => '還沒輪到你下棋喔！']);
    exit;
}

// 3. 檢查該格子是否已經有棋子了
$check_move_sql = "SELECT id FROM moves WHERE game_id = ? AND pos_x = ? AND pos_y = ?";
$stmt_check = $mysqli->prepare($check_move_sql);
$stmt_check->bind_param("iii", $game_id, $pos_x, $pos_y);
$stmt_check->execute();
if ($stmt_check->get_result()->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => '這個位置已經有棋子了！']);
    $stmt_check->close();
    exit;
}
$stmt_check->close();

// 4. 取得當前是第幾手棋 (move_number)
$move_num_sql = "SELECT IFNULL(MAX(move_number), 0) + 1 AS next_move FROM moves WHERE game_id = ?";
$stmt_num = $mysqli->prepare($move_num_sql);
$stmt_num->bind_param("i", $game_id);
$stmt_num->execute();
$move_number = $stmt_num->get_result()->fetch_assoc()['next_move'];
$stmt_num->close();

// 5. 寫入新的落子紀錄
$insert_move_sql = "INSERT INTO moves (game_id, player_id, move_number, pos_x, pos_y) VALUES (?, ?, ?, ?, ?)";
$stmt_insert = $mysqli->prepare($insert_move_sql);
$stmt_insert->bind_param("iiiii", $game_id, $user_id, $move_number, $pos_x, $pos_y);
$stmt_insert->execute();
$stmt_insert->close();

// --------------------------------------------------------
// 6. 核心演算法：判定是否連成五子 (勝負判定)
// --------------------------------------------------------
// 先把包含剛下這顆棋子在內的所有該局棋子撈出來，還原出 15x15 的陣列
$board = array_fill(0, 15, array_fill(0, 15, null));
$all_moves_sql = "SELECT player_id, pos_x, pos_y FROM moves WHERE game_id = ?";
$stmt_all = $mysqli->prepare($all_moves_sql);
$stmt_all->bind_param("i", $game_id);
$stmt_all->execute();
$result_all = $stmt_all->get_result();
while ($m = $result_all->fetch_assoc()) {
    $board[$m['pos_x']][$m['pos_y']] = $m['player_id'];
}
$stmt_all->close();

// 設定要檢查的 4 個方向 (橫、豎、左斜、右斜)
$directions = [
    [[0, 1], [0, -1]],   // 橫向 (右, 左)
    [[1, 0], [-1, 0]],   // 豎向 (下, 上)
    [[1, 1], [-1, -1]],  // 右斜 (右下, 左上)
    [[1, -1], [-1, 1]]   // 左斜 (右上, 左下)
];

$is_win = false;

// 從剛下的這顆棋子向四周延伸檢查
foreach ($directions as $dir) {
    $count = 1; // 包含自己剛下的一顆
    foreach ($dir as $d) {
        $r = $pos_x + $d[0];
        $c = $pos_y + $d[1];
        // 如果下一個格子沒超界，且棋子是自己的，計數就 +1，繼續往同方向找
        while ($r >= 0 && $r < 15 && $c >= 0 && $c < 15 && $board[$r][$c] === $user_id) {
            $count++;
            $r += $d[0];
            $c += $d[1];
        }
    }
    // 若該方向連續達到 5 顆以上，判定勝利
    if ($count >= 5) {
        $is_win = true;
        break;
    }
}

// 7. 更新房間狀態 (Games)
if ($is_win) {
    // 玩家獲勝：更新遊戲狀態為 finished，記錄贏家
    $stmt_update = $mysqli->prepare("UPDATE games SET status = 'finished', winner_id = ? WHERE id = ?");
    $stmt_update->bind_param("ii", $user_id, $game_id);
    $stmt_update->execute();
    $stmt_update->close();

    // 同步更新贏家的勝場 +1
    $stmt_win = $mysqli->prepare("UPDATE users SET wins = wins + 1 WHERE id = ?");
    $stmt_win->bind_param("i", $user_id);
    $stmt_win->execute();
    $stmt_win->close();

    // 同步更新輸家的敗場 +1
    $loser_id = ($user_id === intval($game['black_player_id']))
        ? intval($game['white_player_id'])
        : intval($game['black_player_id']);
    $stmt_loss = $mysqli->prepare("UPDATE users SET losses = losses + 1 WHERE id = ?");
    $stmt_loss->bind_param("i", $loser_id);
    $stmt_loss->execute();
    $stmt_loss->close();

} else if ($move_number >= 225) {
    // 15x15 棋盤滿了，平手（不計勝敗場）
    $stmt_update = $mysqli->prepare("UPDATE games SET status = 'finished' WHERE id = ?");
    $stmt_update->bind_param("i", $game_id);
    $stmt_update->execute();
    $stmt_update->close();
} else {
    // 遊戲繼續，換對手下棋
    // 使用 intval() 確保型別一致，避免 string vs int 的 === 比較錯誤
    $next_turn = ($user_id === intval($game['black_player_id']))
        ? intval($game['white_player_id'])
        : intval($game['black_player_id']);
    $stmt_update = $mysqli->prepare("UPDATE games SET current_turn = ? WHERE id = ?");
    $stmt_update->bind_param("ii", $next_turn, $game_id);
    $stmt_update->execute();
    $stmt_update->close();
}

$mysqli->close();

// 8. 回傳成功訊息給前端
echo json_encode(['status' => 'success']);
