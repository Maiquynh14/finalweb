<?php
// admin_revenue.php

// 1) Lấy dữ liệu doanh thu
// – Hôm nay
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT SUM(total_price) AS total FROM orders WHERE DATE(order_date)=?");
$stmt->bind_param("s",$today);
$stmt->execute(); $today_rev = (float)$stmt->get_result()->fetch_assoc()['total'];

// – Tuần (7 ngày gần nhất), nhóm theo ngày
$data_days = [];
$labels_days = [];
for($i=6;$i>=0;$i--){
  $d = date('Y-m-d',strtotime("-{$i} days"));
  $labels_days[] = date('d/m', strtotime($d));
  $stmt = $conn->prepare("SELECT SUM(total_price) AS total FROM orders WHERE DATE(order_date)=?");
  $stmt->bind_param("s",$d);
  $stmt->execute();
  $data_days[] = (float)$stmt->get_result()->fetch_assoc()['total'];
}

// – Tháng (12 tháng gần nhất), nhóm theo tháng
$data_months = [];
$labels_months = [];
for($i=11;$i>=0;$i--){
  $m = date('Y-m-01',strtotime("-{$i} months"));
  $labels_months[] = date('M y', strtotime($m));
  $stmt = $conn->prepare("SELECT SUM(total_price) AS total FROM orders WHERE DATE_FORMAT(order_date,'%Y-%m')=DATE_FORMAT(?, '%Y-%m')");
  $stmt->bind_param("s",$m);
  $stmt->execute();
  $data_months[] = (float)$stmt->get_result()->fetch_assoc()['total'];
}

// – Top 5 sản phẩm bán chạy (số lượng)
$top_products = [];
$res = $conn->query("
  SELECT i.name, SUM(oi.quantity) AS sold
  FROM order_items oi
  JOIN items i ON oi.item_id=i.id
  GROUP BY i.id
  ORDER BY sold DESC
  LIMIT 5
");
while($r=$res->fetch_assoc()){
  $top_products[] = $r;
}

// – Số khách unique hôm nay
$res = $conn->prepare("SELECT COUNT(DISTINCT customer_email) AS cnt FROM orders WHERE DATE(order_date)=?");
$res->bind_param("s",$today); $res->execute();
$unique_customers = (int)$res->get_result()->fetch_assoc()['cnt'];
?>
<h2>Revenue Dashboard</h2>

<div class="row gy-3">
  <div class="col-md-4">
    <div class="card p-3 text-center">
      <h5>Today Revenue</h5>
      <h3><?= number_format($today_rev) ?> ₫</h3>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card p-3 text-center">
      <h5>Unique Customers Today</h5>
      <h3><?= $unique_customers ?></h3>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card p-3 text-center">
      <h5>Avg Spend per Customer</h5>
      <h3>
        <?php
          if($unique_customers>0){
            echo number_format($today_rev/$unique_customers) . " ₫";
          } else echo "-";
        ?>
      </h3>
    </div>
  </div>
</div>

<hr>

<div class="row gy-4">
  <div class="col-lg-6">
    <canvas id="chartDaily"></canvas>
  </div>
  <div class="col-lg-6">
    <canvas id="chartMonthly"></canvas>
  </div>
</div>

<hr>

<h4>Top 5 Best-Sellers</h4>
<ul class="list-group mb-4">
  <?php foreach($top_products as $p): ?>
    <li class="list-group-item d-flex justify-content-between">
      <?= htmlspecialchars($p['name']) ?>
      <span><?= $p['sold'] ?> pcs</span>
    </li>
  <?php endforeach; ?>
</ul>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Daily revenue chart
new Chart(document.getElementById('chartDaily'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($labels_days) ?>,
    datasets: [{
      label: 'Daily Revenue',
      data: <?= json_encode($data_days) ?>,
      backgroundColor: 'rgba(54,162,235,0.5)'
    }]
  },
  options:{responsive:true, scales:{y:{beginAtZero:true}}}
});

// Monthly revenue chart
new Chart(document.getElementById('chartMonthly'), {
  type: 'line',
  data: {
    labels: <?= json_encode($labels_months) ?>,
    datasets: [{
      label: 'Monthly Revenue',
      data: <?= json_encode($data_months) ?>,
      borderColor: 'rgba(255,159,64,0.8)',
      fill: false
    }]
  },
  options:{responsive:true, scales:{y:{beginAtZero:true}}}
});
</script>
