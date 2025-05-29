<?php
session_start();
include('db.php'); // Kết nối tới database và khởi tạo $conn

// Nếu vừa nhận POST từ menu.php (chưa phải confirm), redirect ngay về GET
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['confirm_order'])) {
    header('Location: order.php');
    exit();
}

// Nếu giỏ hàng trống, quay về menu
if (empty($_SESSION['cart'])) {
    header('Location: menu.php');
    exit();
}

// Xử lý khi người dùng xác nhận đơn hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_order'])) {
    header('Location: place_order.php');
    exit();
}

// Tính tổng tiền đơn
$total_price = 0;
foreach ($_SESSION['cart'] as $ci) {
    $total_price += $ci['price'] * $ci['quantity'];
}

$history = [];
if (!empty($_SESSION['customer_email'])) {
    $stmt_hist = $conn->prepare("
      SELECT
        mi.name                         AS dish,
        SUM(ii.quantity)                AS total_qty,
        SUM(ii.quantity * ii.price)     AS total_spent
      FROM invoices inv
      JOIN invoice_items ii  ON ii.invoice_id = inv.id
      JOIN items mi          ON mi.id         = ii.item_id
      WHERE inv.customer_email = ?
      GROUP BY ii.item_id
    ");
    $stmt_hist->bind_param("s", $_SESSION['customer_email']);
    $stmt_hist->execute();
    $res_hist = $stmt_hist->get_result();
    while ($row = $res_hist->fetch_assoc()) {
        $history[] = $row;
    }
    $stmt_hist->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Confirm Order - ShinHot Pot</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>

<body>
    <div class="container-xxl bg-white p-0">
        <!-- Navbar Start -->
        <div class="container-xxl position-relative p-0">
            <nav class="navbar navbar-expand-lg navbar-dark bg-dark px-4 px-lg-5 py-3 py-lg-0">
                <a href="menu.php" class="navbar-brand p-0">
                    <h1 class="text-primary m-0"><i class="fa fa-utensils me-3"></i>ShinHot Pot</h1>
                </a>
                <div class="collapse navbar-collapse" id="navbarCollapse">
                    <div class="navbar-nav ms-auto py-0 pe-4">
                        <a href="menu.php" class="nav-item nav-link">Back to Menu</a>
                    </div>
                </div>
            </nav>
        </div>
        <!-- Navbar End -->

        <!-- Hero Start -->
        <div class="container-xxl py-5 bg-dark hero-header mb-5">
            <div class="container text-center my-5 pt-5 pb-4">
                <h1 class="display-3 text-white mb-3">Order Confirmation</h1>
            </div>
        </div>
        <!-- Hero End -->

        <div class="container mb-5">
            <!-- Bảng xác nhận các mục đã chọn -->
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Product Name</th>
                            <th>Quantity</th>
                            <th>Unit price</th>
                            <th>Total amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($_SESSION['cart'] as $ci): ?>
                            <?php $subtotal = $ci['price'] * $ci['quantity']; ?>
                            <tr>
                                <td><?= htmlspecialchars($ci['name']) ?></td>
                                <td><?= intval($ci['quantity']) ?></td>
                                <td><?= number_format($ci['price'], 0, ',', '.') ?> ₫</td>
                                <td><?= number_format($subtotal, 0, ',', '.') ?> ₫</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3" class="text-end">Total</th>
                            <th><?= number_format($total_price, 0, ',', '.') ?> ₫</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <!-- Form xác nhận -->
            <form action="order.php" method="post">
                <button type="submit" name="confirm_order" class="btn btn-success">Order Confirmation</button>
            </form>
        </div>

        <!-- PREVIOUS ORDERS -->
    <?php if (!empty($history)): ?>
      <h4 class="mt-5">Your Previous Orders</h4>
      <div class="table-responsive">
        <table class="table table-striped">
          <thead class="table-light">
            <tr>
              <th>Dish</th>
              <th class="text-center">Total Qty</th>
              <th class="text-end">Total Spent</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($history as $h): ?>
              <tr>
                <td><?= htmlspecialchars($h['dish']) ?></td>
                <td class="text-center"><?= intval($h['total_qty']) ?></td>
                <td class="text-end"><?= number_format($h['total_spent'],0,',','.') ?> ₫</td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

        <!-- Footer Start -->
        <div class="container-fluid bg-dark text-light footer pt-5 mt-5 wow fadeIn" data-wow-delay="0.1s">
            <div class="container py-5">
                <div class="row g-5">
                    <div class="col-lg-3 col-md-6">
                        <h4 class="section-title ff-secondary text-start text-primary fw-normal mb-4">Company</h4>
                        <a class="btn btn-link" href="about.html">About Us</a>
                        <a class="btn btn-link" href="select_table.php">Order</a>
                        
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <h4 class="section-title ff-secondary text-start text-primary fw-normal mb-4">Contact</h4>
                        <p class="mb-2"><i class="fa fa-map-marker-alt me-3"></i>100 Vinh Phuc, Hanoi, Viet Nam</p>
                        <p class="mb-2"><i class="fa fa-phone-alt me-3"></i>+012 345 67890</p>
                        <p class="mb-2"><i class="fa fa-envelope me-3"></i>info@example.com</p>
                        <div class="d-flex pt-2">
                            <a class="btn btn-outline-light btn-social" href=""><i class="fab fa-twitter"></i></a>
                            <a class="btn btn-outline-light btn-social" href=""><i class="fab fa-facebook-f"></i></a>
                            <a class="btn btn-outline-light btn-social" href=""><i class="fab fa-youtube"></i></a>
                            <a class="btn btn-outline-light btn-social" href=""><i class="fab fa-linkedin-in"></i></a>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <h4 class="section-title ff-secondary text-start text-primary fw-normal mb-4">Opening</h4>
                        <h5 class="text-light fw-normal">Monday - Saturday</h5>
                        <p>09AM - 09PM</p>
                        <h5 class="text-light fw-normal">Sunday</h5>
                        <p>10AM - 08PM</p>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <h4 class="section-title ff-secondary text-start text-primary fw-normal mb-4">Newsletter</h4>
                        <p>Dolor amet sit justo amet elitr clita ipsum elitr est.</p>
                        <div class="position-relative mx-auto" style="max-width: 400px;">
                            <input class="form-control border-primary w-100 py-3 ps-4 pe-5" type="text" placeholder="Your email">
                            <button type="button" class="btn btn-primary py-2 position-absolute top-0 end-0 mt-2 me-2">SignUp</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="container">
                <div class="copyright">
                    <div class="row">
                    </div>
                </div>
            </div>
        </div>
        <!-- Footer End -->
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>