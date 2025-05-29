<?php
// admin_revenue_topsold.php
// (được include từ admin.php — đã session_start() và $conn)

// Bật debug lỗi
ini_set('display_errors',1);
error_reporting(E_ALL);

// Xác định tuần này (từ thứ Hai đến Chủ Nhật)
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end   = date('Y-m-d', strtotime('sunday this week'));

// 1) Top 7 món bán chạy tuần này
$stmt = $conn->prepare("
  SELECT i.name, SUM(ii.quantity) AS sold_qty
  FROM invoice_items ii
  JOIN items i      ON i.id        = ii.item_id
  JOIN invoices inv ON inv.id      = ii.invoice_id
  WHERE DATE(inv.created_at) BETWEEN ? AND ?
  GROUP BY i.id
  ORDER BY sold_qty DESC
  LIMIT 7
");
$stmt->bind_param("ss", $week_start, $week_end);
$stmt->execute();
$top7 = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$labels_top = array_column($top7, 'name');
$data_top   = array_map('intval', array_column($top7, 'sold_qty'));

// 2) Doanh số theo ngày trong tuần này (số lượng bán mỗi ngày)
$stmt2 = $conn->prepare("
  SELECT DATE(inv.created_at) AS day, SUM(ii.quantity) AS total_qty
  FROM invoice_items ii
  JOIN invoices inv ON inv.id = ii.invoice_id
  WHERE DATE(inv.created_at) BETWEEN ? AND ?
  GROUP BY DATE(inv.created_at)
  ORDER BY DATE(inv.created_at)
");
$stmt2->bind_param("ss", $week_start, $week_end);
$stmt2->execute();
$res2 = $stmt2->get_result();

// Khởi tạo mảng ngày trong tuần với giá trị 0
$labels_day = [];
$data_day   = [];
for ($d = strtotime($week_start); $d <= strtotime($week_end); $d += 86400) {
    $ymd = date('Y-m-d', $d);
    $labels_day[]      = date('D d', $d);
    $data_day[$ymd]    = 0;
}
// Gán lại giá trị thực từ kết quả query
while ($r = $res2->fetch_assoc()) {
    $data_day[$r['day']] = (int)$r['total_qty'];
}
$data_day_vals = array_values($data_day);
?>

<h4>Top 7 Dishes This Week</h4>
<div class="row mb-4">
  <div class="col-md-6">
    <table class="table table-striped">
      <thead class="table-dark">
        <tr><th>#</th><th>Dish</th><th>Qty Sold</th></tr>
      </thead>
      <tbody>
      <?php foreach ($top7 as $i => $r): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><?= htmlspecialchars($r['name']) ?></td>
          <td><?= intval($r['sold_qty']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="col-md-6">
    <canvas id="barChart"></canvas>
  </div>
</div>

<h4>Daily Sales Quantity (This Week)</h4>
<canvas id="lineChart" class="mb-5"></canvas>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const barCtx = document.getElementById('barChart').getContext('2d');
new Chart(barCtx, {
  type: 'bar',
  data: {
    labels: <?= json_encode($labels_top) ?>,
    datasets: [{
      label: 'Qty Sold',
      data: <?= json_encode($data_top) ?>,
      // Không chỉ định màu để tuân thủ guideline
    }]
  },
  options: {
    responsive: true,
    scales: {
      y: { beginAtZero: true }
    },
    plugins: { legend: { display: false } }
  }
});

const lineCtx = document.getElementById('lineChart').getContext('2d');
new Chart(lineCtx, {
  type: 'line',
  data: {
    labels: <?= json_encode($labels_day) ?>,
    datasets: [{
      label: 'Daily Qty',
      data: <?= json_encode($data_day_vals) ?>,
      fill: false,
      tension: 0.4
      // Không chỉ định màu để tuân thủ guideline
    }]
  },
  options: {
    responsive: true,
    scales: {
      y: { beginAtZero: true }
    },
    plugins: { legend: { position: 'bottom' } }
  }
});
</script>
