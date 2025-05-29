<?php
// admin_revenue_sales.php – include từ admin.php (đã session_start & include db.php)
include 'db.php';

/* --- 0) Xử lý AJAX detail: nếu có ?detail=… trả về HTML và exit --- */
if (isset($_GET['detail'])) {
    $today = $_GET['date'] ?? date('Y-m-d');
    $key   = $_GET['detail'];
    header('Content-Type: text/html; charset=UTF-8');

    echo '<div class="table-responsive">';
    echo '<table class="table table-sm mb-0">';

    switch ($key) {

        case 'sales':
            echo '<thead><tr><th>Invoice ID</th><th>Customer</th><th class="text-end">Amount</th></tr></thead><tbody>';
            $stmt = $conn->prepare("
                SELECT id, customer_name, total_amount
                FROM invoices
                WHERE DATE(created_at)=?
                ORDER BY created_at DESC
            ");
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $rs = $stmt->get_result();
            while ($r = $rs->fetch_assoc()) {
                printf(
                    '<tr><td>%d</td><td>%s</td><td class="text-end">%s ₫</td></tr>',
                    $r['id'],
                    htmlspecialchars($r['customer_name']),
                    number_format($r['total_amount'], 0, ',', '.')
                );
            }
            echo '</tbody>';
            break;

        case 'dishes':
            echo '<thead><tr><th>Dish</th><th class="text-center">Qty</th></tr></thead><tbody>';
            $stmt = $conn->prepare("
                SELECT i.name, SUM(ii.quantity) AS qty
                FROM invoices inv
                JOIN invoice_items ii ON ii.invoice_id=inv.id
                JOIN items i          ON i.id=ii.item_id
                WHERE DATE(inv.created_at)=?
                GROUP BY ii.item_id
                ORDER BY qty DESC
            ");
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $rs = $stmt->get_result();
            while ($r = $rs->fetch_assoc()) {
                printf(
                    '<tr><td>%s</td><td class="text-center">%d</td></tr>',
                    htmlspecialchars($r['name']),
                    $r['qty']
                );
            }
            echo '</tbody>';
            break;

        case 'orders':
            echo '<thead><tr><th>Invoice ID</th><th>Customer</th></tr></thead><tbody>';
            $stmt = $conn->prepare("
                SELECT id, customer_name
                FROM invoices
                WHERE DATE(created_at)=?
                ORDER BY created_at DESC
            ");
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $rs = $stmt->get_result();
            while ($r = $rs->fetch_assoc()) {
                printf(
                    '<tr><td>%d</td><td>%s</td></tr>',
                    $r['id'],
                    htmlspecialchars($r['customer_name'])
                );
            }
            echo '</tbody>';
            break;

        case 'new':
            echo '<thead><tr><th>Customer</th><th>Email</th></tr></thead><tbody>';
            $stmt = $conn->prepare("
                SELECT DISTINCT customer_name, customer_email
                FROM invoices
                WHERE DATE(created_at)=?
            ");
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $rs = $stmt->get_result();
            while ($r = $rs->fetch_assoc()) {
                printf(
                    '<tr><td>%s</td><td>%s</td></tr>',
                    htmlspecialchars($r['customer_name']),
                    htmlspecialchars($r['customer_email'])
                );
            }
            echo '</tbody>';
            break;

        default:
            echo '<tr><td colspan="3" class="text-danger">Unknown detail key.</td></tr>';
            break;
    }

    echo '</table></div>';
    exit;
}

/* --- 1) Lấy tham số date & range --- */
$range     = intval($_GET['range']  ?? 7);
$today     = $_GET['date'] ?? date('Y-m-d');
if (!in_array($range, [7,30,90])) $range = 7;
$yesterday = date('Y-m-d', strtotime("$today -1 day"));

/* --- 2) Các hàm tiện ích --- */
function get_total_sales($conn, $date) {
    $stmt = $conn->prepare("SELECT SUM(total_amount) AS s FROM invoices WHERE DATE(created_at)=?");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    return (float)$stmt->get_result()->fetch_assoc()['s'];
}
function get_total_orders($conn, $date) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM invoices WHERE DATE(created_at)=?");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['c'];
}
function get_dishes_sold($conn, $date) {
    $stmt = $conn->prepare("
        SELECT SUM(ii.quantity) AS q
        FROM invoices inv
        JOIN invoice_items ii ON ii.invoice_id=inv.id
        WHERE DATE(inv.created_at)=?
    ");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['q'];
}
function get_new_customers($conn, $date) {
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT customer_email) AS c
        FROM invoices
        WHERE DATE(created_at)=?
    ");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['c'];
}
function pct_change($new, $old) {
    if ($old <= 0) return 0;
    return round((($new - $old) / $old) * 100, 1);
}

/* --- 3) Tính số liệu cho Today & Yesterday --- */
$total_sales   = get_total_sales($conn, $today);
$total_orders  = get_total_orders($conn, $today);
$dishes_sold   = get_dishes_sold($conn, $today);
$new_customers = get_new_customers($conn, $today);

$chg_sales     = pct_change($total_sales,   get_total_sales($conn, $yesterday));
$chg_orders    = pct_change($total_orders,  get_total_orders($conn, $yesterday));
$chg_dishes    = pct_change($dishes_sold,   get_dishes_sold($conn, $yesterday));
$chg_customers = pct_change($new_customers, get_new_customers($conn, $yesterday));

/* --- 4) Dữ liệu cho charts --- */
$labels    = $data_cur = $data_prev = [];
for ($i = $range - 1; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $labels[] = date('d M', strtotime($d));

    // This period
    $stmt = $conn->prepare("SELECT SUM(total_amount) AS s FROM invoices WHERE DATE(created_at)=?");
    $stmt->bind_param("s", $d);
    $stmt->execute();
    $data_cur[] = (float)$stmt->get_result()->fetch_assoc()['s'];

    // Previous period
    $d2 = date('Y-m-d', strtotime("-".($i + $range)." days"));
    $stmt->bind_param("s", $d2);
    $stmt->execute();
    $data_prev[] = (float)$stmt->get_result()->fetch_assoc()['s'];
}

/* --- 5) Recent transactions (Today) --- */
$recent = $conn->query("
    SELECT customer_name, total_amount
    FROM invoices
    WHERE DATE(created_at)= '$today'
    ORDER BY created_at DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
?>

<!-- Form chọn ngày -->
<form method="get" class="mb-4">
  <input type="hidden" name="page" value="revenue_sales">
  <div class="d-flex gap-2 align-items-center">
    <label class="m-0">Choose date:</label>
    <input type="date" name="date" class="form-control form-control-sm w-auto"
           value="<?= htmlspecialchars($today) ?>">
    <select name="range" class="form-select form-select-sm w-auto">
      <option value="7"  <?= $range===7  ? 'selected' : '' ?>>7 days</option>
      <option value="30" <?= $range===30 ? 'selected' : '' ?>>30 days</option>
      <option value="90" <?= $range===90 ? 'selected' : '' ?>>90 days</option>
    </select>
    <button class="btn btn-primary btn-sm">View</button>
  </div>
</form>

<!-- Cards -->
<div class="row gy-4 mb-4">
  <?php
    $cards = [
      ['key'=>'sales','title'=>'Total Sales','value'=>$total_sales,'chg'=>$chg_sales,'suffix'=>' ₫'],
      ['key'=>'dishes','title'=>'Dishes Sold','value'=>$dishes_sold,'chg'=>$chg_dishes,'suffix'=>''],
      ['key'=>'orders','title'=>'Total Orders','value'=>$total_orders,'chg'=>$chg_orders,'suffix'=>''],
      ['key'=>'new','title'=>'New Customers','value'=>$new_customers,'chg'=>$chg_customers,'suffix'=>''],
    ];
    foreach($cards as $c): ?>
    <div class="col-md-3">
      <div class="card shadow-sm"
           role="button"
           data-bs-toggle="modal"
           data-bs-target="#detailModal"
           onclick="showDetail('<?= $c['key'] ?>')">
        <div class="card-body">
          <h6><?= $c['title'] ?></h6>
          <h3><?= number_format($c['value'],0,',','.') ?><?= $c['suffix'] ?></h3>
          <small class="text-<?= $c['chg']>=0?'success':'danger' ?>">
            <?= ($c['chg']>=0?'+':'').$c['chg'] ?>% from yesterday
          </small>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Charts -->
<div class="d-flex justify-content-between align-items-center mb-3">
  <h5>Analysis</h5>
</div>
<canvas id="salesChart" height="120"></canvas>

<!-- Recent Transactions -->
<div class="row gy-4 mt-5">
  <div class="col-lg-6">
    <h5>Recent Transactions</h5>
    <table class="table">
      <thead><tr><th>Customer</th><th class="text-end">Amount</th></tr></thead>
      <tbody>
        <?php foreach($recent as $t): ?>
          <tr>
            <td><?= htmlspecialchars($t['customer_name']) ?></td>
            <td class="text-end"><?= number_format($t['total_amount'],0,',','.') ?> ₫</td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="col-lg-6">
    <h5>Weekly Sales</h5>
    <canvas id="weeklyChart" height="220"></canvas>
  </div>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="detailTitle"></h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="detailContent"></div>
    </div>
  </div>
</div>

<!-- JS: Chart.js and Detail AJAX -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  // Main chart
  new Chart(document.getElementById('salesChart'), {
    type: 'line',
    data: {
      labels: <?= json_encode($labels) ?>,
      datasets: [
        { label:'Last Period', data:<?= json_encode($data_prev) ?>, borderDash:[5,5], fill:false },
        { label:'This Period', data:<?= json_encode($data_cur) ?>, fill:false, tension:0.4 }
      ]
    },
    options: { scales:{ y:{ beginAtZero:true }}, plugins:{ legend:{ position:'bottom' }} }
  });
  // Secondary chart
  new Chart(document.getElementById('weeklyChart'), {
    type:'line',
    data:{ labels: <?= json_encode($labels) ?>, datasets:[{ data:<?= json_encode($data_cur) ?>, fill:true, tension:0.4 }] },
    options:{ scales:{ y:{ display:false }}, plugins:{ legend:{ display:false }} }
  });

  // Show detail in modal
  function showDetail(key) {
    const date = document.querySelector("input[name='date']").value;
    const titles = { sales:'All Invoices', dishes:'Dishes Sold', orders:'Invoice List', new:'New Customers' };
    document.getElementById('detailTitle').innerText = titles[key]||'Details';
    document.getElementById('detailContent').innerHTML = '<div class="text-center py-5 text-muted">Loading…</div>';
    fetch(`admin_revenue_sales.php?date=${date}&detail=${key}`, { credentials:'same-origin' })
      .then(r=>r.text()).then(html=>document.getElementById('detailContent').innerHTML=html);
  }
</script>
