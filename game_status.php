<?php
require_once 'db_connect.php';

// 【重要】告訴瀏覽器：這個檔案回傳的不是網頁，而是 JSON 格式的純資料
header('Content-Type: application/json');

// 1. 安全檢查：未登入或缺少 game_id 則回傳錯誤
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => '未授權或參數錯誤']);
    exit;
}

// game_id 可以來自 GET 或 POST
$game_id = 0;
if (isset($_GET['game_id'])) {
    $game_id = intval($_GET['game_id']);
} elseif (isset($_POST['game_id'])) {
    $game_id = intval($_POST['game_id']);
}

if ($game_id <= 0) {
    echo json_encode(['error' => '無效的 game_id']);
    exit;
}

// 2. 撈取目前這場遊戲的房間狀態 [cite: 7]
// 包含黑白方是誰、當前輪到誰、房間狀態以及獲勝者 [cite: 8, 9, 10, 11]
$game_sql = "SELECT black_player_id, white_player_id, current_turn, status, winner_id FROM games WHERE id = ?";
$stmt = $mysqli->prepare($game_sql);
$stmt->bind_param("i", $game_id);
$stmt->execute();
$game_result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$game_result) {
    echo json_encode(['error' => '找不到該遊戲房間']);
    exit;
}

// 解析房間狀態變數（全部強制轉 int，避免 MySQL 字串 vs PHP 整數的 === 比較失敗）
$black_id        = intval($game_result['black_player_id']);
$white_id        = intval($game_result['white_player_id']);
$status          = $game_result['status'];
$current_turn_id = intval($game_result['current_turn']);
$winner_id       = $game_result['winner_id'] !== null ? intval($game_result['winner_id']) : null;

// 判定當前該誰落子（將資料庫的數字 ID 轉換為前端 JavaScript 需要的 'black' 或 'white'）
$current_turn_identity = null;
if ($current_turn_id === $black_id) {
    $current_turn_identity = 'black';
} else if ($current_turn_id === $white_id) {
    $current_turn_identity = 'white';
}

// 判定贏家是誰
$winner_identity = null;
if ($winner_id) {
    if ($winner_id === $black_id) {
        $winner_identity = 'black';
    } else if ($winner_id === $white_id) {
        $winner_identity = 'white';
    }
} else if ($status === 'finished' && is_null($winner_id)) {
    // 如果房間狀態是已結束，但沒有贏家，代表這局平手
    $winner_identity = 'draw';
}

// 3. 撈取所有落子紀錄，並拼裝成 15x15 的二維陣列 [cite: 13]
// 初始化一個 15x15 的空陣列，預設每個格子都是 null
$board = array_fill(0, 15, array_fill(0, 15, null));

// 按照落子順序 (move_number) 從 moves 資料表抓取棋子座標 [cite: 16]
$moves_sql = "SELECT player_id, pos_x, pos_y FROM moves WHERE game_id = ? ORDER BY move_number ASC";
$stmt_moves = $mysqli->prepare($moves_sql);
$stmt_moves->bind_param("i", $game_id);
$stmt_moves->execute();
$moves_result = $stmt_moves->get_result();

while ($move = $moves_result->fetch_assoc()) {
    $r = $move['pos_x']; // x 座標對應 Row [cite: 16]
    $c = $move['pos_y']; // y 座標對應 Col [cite: 17]
    
    // 判斷這個座標上的棋子是黑方還是白方下的
    $stone_color = ($move['player_id'] === $black_id) ? 'black' : 'white';
    
    // 將棋子顏色填入對應的陣列格子中
    $board[$r][$c] = $stone_color;
}

$stmt_moves->close();

// 檢查是否為認輸
$is_forfeit = false;
if ($status === 'finished') {
    // 若 forfeits 資料表不存在會拋錯，我們這裡用一個簡單的 query 確認
    $check_forfeit = $mysqli->query("SHOW TABLES LIKE 'forfeits'");
    if ($check_forfeit && $check_forfeit->num_rows > 0) {
        $stmt_check = $mysqli->prepare("SELECT 1 FROM forfeits WHERE game_id = ?");
        $stmt_check->bind_param("i", $game_id);
        $stmt_check->execute();
        if ($stmt_check->get_result()->fetch_assoc()) {
            $is_forfeit = true;
        }
        $stmt_check->close();
    }
}

// 處理發送聊天訊息
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['msg_text'])) {
    $user_id = intval($_SESSION['user_id']);
    $msg_text = isset($_POST['msg_text']) ? trim($_POST['msg_text']) : '';

    if (!empty($msg_text) && mb_strlen($msg_text) <= 200) {
        $mysqli->query("CREATE TABLE IF NOT EXISTS room_msg (id INT AUTO_INCREMENT PRIMARY KEY, game_id INT, user_id INT, username VARCHAR(50), msg_text TEXT, posted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_game_id (game_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $stmt = $mysqli->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $usr = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($usr) {
            $stmt = $mysqli->prepare("INSERT INTO room_msg (game_id, user_id, username, msg_text) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $game_id, $user_id, $usr['username'], $msg_text);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// 獲取聊天訊息
$messages = [];
$last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : (isset($_POST['last_id']) ? intval($_POST['last_id']) : 0);

$mysqli->query("CREATE TABLE IF NOT EXISTS room_msg (id INT AUTO_INCREMENT PRIMARY KEY, game_id INT, user_id INT, username VARCHAR(50), msg_text TEXT, posted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_game_id (game_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$stmt = $mysqli->prepare("SELECT id, user_id, username, msg_text, posted_at FROM room_msg WHERE game_id = ? AND id > ? ORDER BY id ASC LIMIT 50");
$stmt->bind_param("ii", $game_id, $last_id);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $messages[] = [
        'id' => (int)$row['id'],
        'user_id' => (int)$row['user_id'],
        'username' => $row['username'],
        'message' => $row['msg_text'],
        'created_at' => $row['posted_at']
    ];
}
$stmt->close();

// 4. 將所有準備好的資料打包成陣列
$response = [
    'game_status' => $status,
    'current_turn_identity' => $current_turn_identity,
    'winner' => $winner_identity,
    'is_forfeit' => $is_forfeit,
    'board' => $board,
    'messages' => $messages
];

// 最後使用 json_encode 轉換成 JSON 格式並印出，交給前端的 Fetch API 接收處理
echo json_encode($response);

$mysqli->close();
