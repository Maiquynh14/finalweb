<?php
session_start();
include('db.php'); // Kết nối DB, tạo $conn

// Hiển thị flash message (nếu có) rồi xoá
if (!empty($_SESSION['flash_message'])) {
    echo '<div class="alert alert-success text-center">'
         . htmlspecialchars($_SESSION['flash_message']) .
         '</div>';
    unset($_SESSION['flash_message']);
}

// Nếu đã login rồi, chuyển thẳng sang admin.php
if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = trim($_POST['password'] ?? '');

    // Chuẩn bị truy vấn lấy mật khẩu và vai trò từ bảng users
    $sql = "SELECT id, password, role FROM users WHERE username = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $user);
        $stmt->execute();
        $result = $stmt->get_result();
        // Nếu tìm thấy user
        if ($row = $result->fetch_assoc()) {
            // Vì database đang lưu password dạng plaintext, so sánh trực tiếp
            if ($pass === $row['password'] && $row['role'] === 'admin') {
                // Đăng nhập thành công
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_user']      = $user;
                $_SESSION['admin_id']        = $row['id'];
                header('Location: admin.php');
                exit();
            } else {
                $error = 'Tên đăng nhập hoặc mật khẩu không đúng, hoặc bạn không có quyền admin.';
            }
        } else {
            $error = 'Tên đăng nhập hoặc mật khẩu không đúng.';
        }
        $stmt->close();
    } else {
        $error = 'Lỗi hệ thống, vui lòng thử lại sau.';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Đăng nhập Admin | ShinHot Pot</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Google Fonts: Pacifico for headings, Baloo for logo, Nunito for body -->
  <link href="https://fonts.googleapis.com/css2?family=Pacifico&family=Baloo+2:wght@600;700&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">

  <!-- FontAwesome for icons -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

  <!-- Bootstrap CSS -->
  <link href="css/bootstrap.min.css" rel="stylesheet">

  <style>
    .logo-text {
      font-family: 'Pacifico',cursive;
      font-weight: bold;
      font-size: 1.9rem;
      color: #fea116;
    }
    .login-card {
      max-width: 360px;
      margin: 5vh auto;
      padding: 2rem;
      background: #fff;
      border-radius: .75rem;
      box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }
    .login-card h4 {
      font-family: 'Pacifico', cursive;
      margin-bottom: 1.5rem;
    }
    .site-footer {
      padding: 3rem;
      font-size: 1.1rem;
    }
  </style>
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
      <a href="menu.php" class="navbar-brand d-flex align-items-center p-0">
        <i class="fas fa-utensils fa-2x me-2" style="color:#ff851b;"></i>
        <span class="logo-text">ShinHot Pot</span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navCollapse">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navCollapse">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item"><a href="menu.php" class="nav-link">Home</a></li>
          <li class="nav-item"><a href="login.php" class="nav-link active">Login</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Hero Header -->
  <div class="container-xxl py-5 bg-dark text-center text-white">
    <h1 class="display-4 logo-text">Admin Login</h1>
  </div>

  <!-- Login Form -->
  <div class="login-card">
    <h4 class="text-center">Log In</h4>
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
      <div class="mb-3">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" required autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary w-100">Log in</button>
    </form>
  </div>

  <!-- Footer -->
  <div class="container-fluid bg-dark text-light site-footer">
    <div class="container text-center">
      <p class="mb-0">&copy; 2025 ShinHot Pot. All rights reserved.</p>
    </div>
  </div>

  <!-- Bootstrap JS & Popper -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
