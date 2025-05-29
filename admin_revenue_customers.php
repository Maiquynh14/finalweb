<?php
// admin_revenue_customers.php

// 1) Include database connection
include 'db.php';

// 2) Pagination & search
$page_size = 10;
$page_num  = max(1, intval($_GET['p'] ?? 1));
$offset    = ($page_num - 1) * $page_size;
$search    = trim($_GET['q'] ?? '');

// Đếm tổng số khách hàng duy nhất từ invoices
if ($search !== '') {
    $like = '%' . $conn->real_escape_string($search) . '%';
    $qr = $conn->prepare("
      SELECT COUNT(DISTINCT customer_email) AS cnt
      FROM invoices
      WHERE customer_name LIKE ?
    ");
    $qr->bind_param("s", $like);
    $qr->execute();
    $total_customers = (int)$qr->get_result()->fetch_assoc()['cnt'];
} else {
    $res = $conn->query("
      SELECT COUNT(DISTINCT customer_email) AS cnt 
      FROM invoices
    ");
    $total_customers = (int)$res->fetch_assoc()['cnt'];
}

// 3) Lấy danh sách khách + aggregate
if ($search !== '') {
    $stmt = $conn->prepare("
      SELECT 
        inv.customer_name,
        inv.customer_email,
        MAX(inv.created_at)   AS last_order,
        SUM(ii.quantity)      AS total_items,
        SUM(inv.total_amount) AS total_spent
      FROM invoices inv
      JOIN invoice_items ii 
        ON ii.invoice_id = inv.id
      WHERE inv.customer_name LIKE ?
      GROUP BY inv.customer_email
      ORDER BY last_order DESC
      LIMIT ?, ?
    ");
    $stmt->bind_param("sii", $like, $offset, $page_size);
} else {
    $stmt = $conn->prepare("
      SELECT 
        inv.customer_name,
        inv.customer_email,
        MAX(inv.created_at)   AS last_order,
        SUM(ii.quantity)      AS total_items,
        SUM(inv.total_amount) AS total_spent
      FROM invoices inv
      JOIN invoice_items ii 
        ON ii.invoice_id = inv.id
      GROUP BY inv.customer_email
      ORDER BY last_order DESC
      LIMIT ?, ?
    ");
    $stmt->bind_param("ii", $offset, $page_size);
}
$stmt->execute();
$customers = $stmt->get_result();
?>

<div class="card shadow-sm mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="mb-0">Customer Overview</h5>
    <small class="text-muted">Total: <?= $total_customers ?></small>
    <form class="d-flex" method="get" action="">
      <input type="hidden" name="page" value="revenue_customers">
      <input class="form-control form-control-sm me-2" type="search" name="q" placeholder="Search by name"
             value="<?= htmlspecialchars($search) ?>">
      <button class="btn btn-sm btn-primary">Search</button>
    </form>
  </div>

  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead class="table-light">
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Last Order</th>
          <th>Items</th>
          <th>Spent</th>
          <th>History</th>
        </tr>
      </thead>
      <tbody id="customer-accordion">
      <?php if ($customers->num_rows === 0): ?>
        <tr><td colspan="6" class="text-center py-4">No customers found.</td></tr>
      <?php else: ?>
        <?php while ($c = $customers->fetch_assoc()): ?>
          <?php 
            // Lấy chi tiết đơn hàng cho mỗi khách
            $hist = $conn->prepare("
              SELECT i.name, SUM(ii.quantity) AS qty, 
                     SUM(ii.quantity * ii.price) AS spent
              FROM invoices inv
              JOIN invoice_items ii ON ii.invoice_id=inv.id
              JOIN items i ON i.id=ii.item_id
              WHERE inv.customer_email = ?
              GROUP BY ii.item_id
            ");
            $hist->bind_param("s", $c['customer_email']);
            $hist->execute();
            $res_hist = $hist->get_result();
          ?>
          <tr>
            <td><?= htmlspecialchars($c['customer_name']) ?></td>
            <td><?= htmlspecialchars($c['customer_email']) ?></td>
            <td><?= date('Y-m-d H:i', strtotime($c['last_order'])) ?></td>
            <td><?= number_format($c['total_items']) ?></td>
            <td><?= number_format($c['total_spent'],0,',','.') ?> ₫</td>
            <td class="text-nowrap">
              <button class="btn btn-sm btn-info"
                      data-bs-toggle="collapse"
                      data-bs-target="#hist-<?= md5($c['customer_email']) ?>"
                      aria-expanded="false">
                View
              </button>
            </td>
          </tr>
          <tr class="collapse" id="hist-<?= md5($c['customer_email']) ?>" data-bs-parent="#customer-accordion">
            <td colspan="6" class="p-0">
              <table class="table mb-0">
                <thead class="table-light">
                  <tr><th>Dish</th><th>Qty</th><th class="text-end">Total Spent</th></tr>
                </thead>
                <tbody>
                <?php while ($row = $res_hist->fetch_assoc()): ?>
                  <tr>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= intval($row['qty']) ?></td>
                    <td class="text-end"><?= number_format($row['spent'],0,',','.') ?> ₫</td>
                  </tr>
                <?php endwhile; ?>
                </tbody>
              </table>
            </td>
          </tr>
        <?php endwhile; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($offset + $page_size < $total_customers): ?>
    <div class="card-footer text-center">
      <a href="?page=revenue_customers&p=<?= $page_num+1 ?>&q=<?= urlencode($search) ?>"
         class="btn btn-outline-primary btn-sm">Load More</a>
    </div>
  <?php endif; ?>
</div>
