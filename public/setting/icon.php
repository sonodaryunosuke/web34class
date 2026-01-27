<?php
session_start();
if (empty($_SESSION['login_user_id'])) {
  header("HTTP/1.1 302 Found");
  header("Location: /login.php");
  exit;
}

// 簡易CSRFトークン（任意）
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$dbh = new PDO('mysql:host=mysql;dbname=example_db;charset=utf8mb4', 'root', '', [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// ログイン中ユーザー取得
$select_sth = $dbh->prepare("SELECT * FROM users WHERE id = :id");
$select_sth->execute([':id' => $_SESSION['login_user_id']]);
$user = $select_sth->fetch();
if (!$user) {
  header("HTTP/1.1 302 Found");
  header("Location: /login.php");
  exit;
}

// POST 処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // CSRF 確認（任意）
  if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header("HTTP/1.1 400 Bad Request");
    echo "不正なリクエストです。";
    exit;
  }

  $updates = [];
  $params  = [':id' => $user['id']];

  // === 画像の更新（画像が実際に選択された場合のみ） ===
  if (!empty($_POST['image_base64'])) {
    // data URL から MIME を抽出
    if (!preg_match('#^data:(image/(png|jpeg|webp));base64,#i', $_POST['image_base64'], $m)) {
      header("HTTP/1.1 400 Bad Request");
      echo "対応していない画像形式です。PNG/JPEG/WEBPのみアップロード可能です。";
      exit;
    }
    $mime = strtolower($m[1]);
    $ext  = ($mime === 'image/png') ? 'png' : (($mime === 'image/jpeg') ? 'jpg' : 'webp');

    $base64 = preg_replace('#^data:image/(png|jpeg|webp);base64,#i', '', $_POST['image_base64']);
    $image_binary = base64_decode($base64, true);
    if ($image_binary === false) {
      header("HTTP/1.1 400 Bad Request");
      echo "画像データのデコードに失敗しました。";
      exit;
    }

    // 保存先
    $uploadDir = '/var/www/upload/image';
    if (!is_dir($uploadDir)) {
      if (!mkdir($uploadDir, 0755, true)) {
        header("HTTP/1.1 500 Internal Server Error");
        echo "アップロードディレクトリを作成できません。";
        exit;
      }
    }

    // ファイル名
    $image_filename = time() . '_' . bin2hex(random_bytes(16)) . '.' . $ext;
    $filepath = $uploadDir . '/' . $image_filename;

    if (file_put_contents($filepath, $image_binary) === false) {
      header("HTTP/1.1 500 Internal Server Error");
      echo "画像の保存に失敗しました。";
      exit;
    }

    $updates[] = 'icon_filename = :icon_filename';
    $params[':icon_filename'] = $image_filename;
  }

  // === 自己紹介の更新（1000字） ===
  if (isset($_POST['bio'])) {
    $bio = mb_substr((string)$_POST['bio'], 0, 1000);
    $updates[] = 'bio = :bio';
    $params[':bio'] = $bio;
  }

  // 何かしら変更があるときだけ UPDATE
  if ($updates) {
    $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = :id';
    $update_sth = $dbh->prepare($sql);
    $update_sth->execute($params);
  }

  header("HTTP/1.1 302 Found");
  header("Location: ./icon.php");
  exit;
}
?>
<!doctype html>
<html lang="ja">
<meta charset="utf-8">
<title>アイコン画像設定/変更</title>
<body>
<h1>アイコン画像設定/変更</h1>

<div>
  <?php if(empty($user['icon_filename'])): ?>
    現在未設定
  <?php else: ?>
    <img src="/image/<?= htmlspecialchars($user['icon_filename'], ENT_QUOTES, 'UTF-8') ?>?v=<?= time() ?>"
         alt="icon"
         style="height:5em;width:5em;border-radius:50%;object-fit:cover;">
  <?php endif; ?>
</div>

<form method="POST">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
  <div style="margin:1em 0;">
    <label>アイコン画像（PNG/JPEG/WEBP）</label><br>
    <input type="file" accept="image/*" name="image" id="imageInput">
  </div>

  <div style="margin:1em 0;">
    <label for="bio">自己紹介（1000文字まで）</label><br>
    <textarea id="bio" name="bio" rows="8" cols="80" maxlength="1000"><?= htmlspecialchars($user['bio'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
  </div>

  <input id="imageBase64Input" type="hidden" name="image_base64"> <!-- base64送信用 -->
  <canvas id="imageCanvas" style="display:none;"></canvas>          <!-- 縮小用 -->

  <button type="submit">保存する</button>
</form>

<hr>

<h2>現在の自己紹介</h2>
<div style="white-space: pre-wrap;">
  <?php if (empty($user['bio'])): ?>
    まだ自己紹介が設定されていません。
  <?php else: ?>
    <?= nl2br(htmlspecialchars($user['bio'], ENT_QUOTES, 'UTF-8')) ?>
  <?php endif; ?>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const imageInput = document.getElementById("imageInput");
  const imageBase64Input = document.getElementById("imageBase64Input");
  const canvas = document.getElementById("imageCanvas");

  imageInput.addEventListener("change", () => {
    imageBase64Input.value = ""; // クリアしておく
    if (imageInput.files.length < 1) return;

    const file = imageInput.files[0];
    if (!file.type.startsWith('image/')) return;

    // 画像縮小処理
    const reader = new FileReader();
    const image = new Image();

    reader.onload = () => {
      image.onload = () => {
        const originalWidth = image.naturalWidth;
        const originalHeight = image.naturalHeight;
        const maxLength = 1000;

        if (originalWidth <= maxLength && originalHeight <= maxLength) {
          canvas.width = originalWidth;
          canvas.height = originalHeight;
        } else if (originalWidth > originalHeight) {
          canvas.width = maxLength;
          canvas.height = Math.round(maxLength * originalHeight / originalWidth);
        } else {
          canvas.width = Math.round(maxLength * originalWidth / originalHeight);
          canvas.height = maxLength;
        }

        const ctx = canvas.getContext("2d");
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(image, 0, 0, canvas.width, canvas.height);

        // 出力フォーマットは PNG（可逆・無損失）※必要なら image/jpeg に変更可
        imageBase64Input.value = canvas.toDataURL("image/png");
      };
      image.src = reader.result;
    };
    reader.readAsDataURL(file);
  });
});
</script>
</body>
</html>
