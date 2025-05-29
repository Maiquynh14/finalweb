<?php
// admin_orders.php – phần module, đã session_start() và include db.php ở admin.php

// Bảo vệ trang
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

// --- 1) Phân trang ---
$pageSize = 10;
$page     = max(1, intval($_GET['pg'] ?? 1));
$offset   = ($page - 1) * $pageSize;

// Đếm tổng số “orders” (tức invoices)
$resCount    = $conn->query("SELECT COUNT(*) FROM invoices");
$totalOrders = (int)$resCount->fetch_row()[0];
$totalPages  = ceil($totalOrders / $pageSize);

// --- 2) Lấy danh sách invoices ---
$stmt = $conn->prepare("
  SELECT id, customer_name, customer_email, created_at, total_amount
  FROM invoices
  ORDER BY created_at DESC
  LIMIT ? OFFSET ?
");
$stmt->bind_param("ii", $pageSize, $offset);
$stmt->execute();
$res_orders = $stmt->get_result();
?>

<h2 id="orders">Orders Management</h2>

<table class="table table-striped table-hover">
  <thead class="table-dark">
    <tr>
      <th>#</th>
      <th>Customer</th>
      <th>Email</th>
      <th>Date</th>
      <th class="text-end">Total</th>
      <th>Details</th>
    </tr>
  </thead>
  <tbody id="orders-accordion">
    <?php while ($o = $res_orders->fetch_assoc()): ?>
    <tr>
      <td><?= $o['id'] ?></td>
      <td><?= htmlspecialchars($o['customer_name']) ?></td>
      <td><?= htmlspecialchars($o['customer_email']) ?></td>
      <td><?= $o['created_at'] ?></td>
      <td class="text-end"><?= number_format($o['total_amount'],0,',','.') ?> ₫</td>
      <td>
        <button class="btn btn-sm btn-info"
                data-bs-toggle="collapse"
                data-bs-target="#details-<?= $o['id'] ?>"
                aria-expanded="false">
          View
        </button>
      </td>
    </tr>
    <tr>
      <td colspan="6" class="p-0 border-0">
        <div id="details-<?= $o['id'] ?>"
             class="collapse"
             data-bs-parent="#orders-accordion">
          <table class="table mb-0">
            <thead>
              <tr>
                <th>Dish</th><th>Qty</th><th class="text-end">Unit Price</th><th class="text-end">Line Total</th>
              </tr>
            </thead>
            <tbody>
            <?php
              $stmt2 = $conn->prepare("
                SELECT i.name, ii.quantity, ii.price
                FROM invoice_items ii
                JOIN items i ON ii.item_id = i.id
                WHERE ii.invoice_id = ?
              ");
              $stmt2->bind_param("i", $o['id']);
              $stmt2->execute();
              $items = $stmt2->get_result();
              while ($it = $items->fetch_assoc()):
                $lineTotal = $it['quantity'] * $it['price'];
            ?>
              <tr>
                <td><?= htmlspecialchars($it['name']) ?></td>
                <td><?= intval($it['quantity']) ?></td>
                <td class="text-end"><?= number_format($it['price'],0,',','.') ?> ₫</td>
                <td class="text-end"><?= number_format($lineTotal,0,',','.') ?> ₫</td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </td>
    </tr>
    <?php endwhile; ?>
  </tbody>
</table>

<!-- Pagination -->
<nav aria-label="Orders pagination">
  <ul class="pagination justify-content-center">
    <li class="page-item <?= $page<=1?'disabled':'' ?>">
      <a class="page-link" href="?page=orders&pg=<?= $page-1 ?>">Previous</a>
    </li>
    <?php for($p=1; $p<=$totalPages; $p++): ?>
      <li class="page-item <?= $p===$page?'active':'' ?>">
        <a class="page-link" href="?page=orders&pg=<?= $p ?>"><?= $p ?></a>
      </li>
    <?php endfor; ?>
    <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
      <a class="page-link" href="?page=orders&pg=<?= $page+1 ?>">Next</a>
    </li>
  </ul>
</nav>
