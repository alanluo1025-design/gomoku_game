<?php
session_start();

// 清除所有 Session 變數
$_SESSION = array();

// 銷毀 Session
session_destroy();

// 彈出提示並跳回首頁 index.html
echo "<script>
        alert('您已成功登出遊戲！');
        window.location.href = 'index.html';
      </script>";
exit;
?>
