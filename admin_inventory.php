<?php
// admin_inventory.php — include from admin.php (session_start & $conn already done)

// 1) Handle new Purchase Order (PO) creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['po_ing'], $_POST['po_qty'])) {
    $item_id = intval($_POST['po_ing']);
    $qty     = intval($_POST['po_qty']);

    // create a dummy “receive” invoice to model stock replenishment
    $stmt = $conn->prepare("
      INSERT INTO invoices (customer_name, customer_email, total_amount)
      VALUES ('--PO--', '--PO--', 0)
    ");
    $stmt->execute();
    $inv_id = $stmt->insert_id;

    // record into invoice_items
    $stmt2 = $conn->prepare("
      INSERT INTO invoice_items (invoice_id, item_id, quantity, price)
      VALUES (?, ?, ?, 0)
    ");
    $stmt2->bind_param("iii", $inv_id, $item_id, $qty);
    $stmt2->execute();

    $_SESSION['flash_message'] = "PO created for item #{$item_id} (qty={$qty})";
    header('Location: admin.php?page=inventory#inventory');
    exit();
}

// 2) Fetch categories of ingredients
$cats = $conn->query("SELECT DISTINCT category FROM items ORDER BY category");
$categories = [];
while ($r = $cats->fetch_assoc()) {
    $categories[] = $r['category'];
}
$sel_cat = $_GET['cat'] ?? 'All';

// 3) Query current stock by item
$base_query = "
  SELECT 
    itm.id                  AS item_id,
    itm.name,
    itm.category,
    COALESCE(po_imports.total,0) - COALESCE(sales.total,0) AS stock_quantity
  FROM items itm
  LEFT JOIN (
    SELECT ii.item_id, SUM(ii.quantity) AS total
    FROM invoices inv
    JOIN invoice_items ii ON ii.invoice_id=inv.id
    WHERE inv.customer_name='--PO--'
    GROUP BY ii.item_id
  ) AS po_imports ON po_imports.item_id = itm.id
  LEFT JOIN (
    SELECT ii.item_id, SUM(ii.quantity) AS total
    FROM invoices inv
    JOIN invoice_items ii ON ii.invoice_id=inv.id
    WHERE inv.customer_name<>'--PO--'
    GROUP BY ii.item_id
  ) AS sales ON sales.item_id = itm.id
";

if ($sel_cat !== 'All') {
    $stmt = $conn->prepare($base_query . " WHERE itm.category=? ORDER BY itm.name");
    $stmt->bind_param("s", $sel_cat);
    $stmt->execute();
    $stock_res = $stmt->get_result();
} else {
    $stock_res = $conn->query($base_query . " ORDER BY itm.category, itm.name");
}

// 4) Fetch recent “replenishment” POs
$po_res = $conn->query("
  SELECT inv.id, ii.item_id, itm.name, ii.quantity, inv.created_at
  FROM invoices inv
  JOIN invoice_items ii ON ii.invoice_id=inv.id
  JOIN items itm ON itm.id=ii.item_id
  WHERE inv.customer_name='--PO--'
  ORDER BY inv.created_at DESC
");

// 5) Summaries
// total on-hand
$total_qty = $conn->query("
  SELECT SUM(
    COALESCE(po_imports.total,0) - COALESCE(sales.total,0)
  ) AS t
  FROM items itm
  LEFT JOIN (
    SELECT ii.item_id, SUM(ii.quantity) AS total
    FROM invoices inv
    JOIN invoice_items ii ON ii.invoice_id=inv.id
    WHERE inv.customer_name='--PO--'
    GROUP BY ii.item_id
  ) po_imports ON po_imports.item_id = itm.id
  LEFT JOIN (
    SELECT ii.item_id, SUM(ii.quantity) AS total
    FROM invoices inv
    JOIN invoice_items ii ON ii.invoice_id=inv.id
    WHERE inv.customer_name<>'--PO--'
    GROUP BY ii.item_id
  ) sales ON sales.item_id = itm.id
")->fetch_assoc()['t'];
// pending PO = tổng số lượng PO gần đây
$pending_po = $conn->query("
  SELECT SUM(ii.quantity)
  FROM invoices inv
  JOIN invoice_items ii ON ii.invoice_id=inv.id
  WHERE inv.customer_name='--PO--'
")->fetch_row()[0];
$suppliers_cnt = 31;  // placeholder
$cat_cnt       = count($categories);
?>

<h2 id="inventory">Inventory Management</h2>

<?php if (!empty($_SESSION['flash_message'])): ?>
  <div class="alert alert-success text-center">
    <?= htmlspecialchars($_SESSION['flash_message']) ?>
  </div>
  <?php unset($_SESSION['flash_message']); ?>
<?php endif; ?>

<div class="row g-3 mb-4">
  <!-- Total Stock -->
  <div class="col-md-3">
    <div class="card text-center shadow-sm p-3">
      <i class="fas fa-layer-group fa-2x text-primary mb-2"></i>
      <h6>Total Stock</h6>
      <h4><?= number_format($total_qty) ?></h4>
    </div>
  </div>
  <!-- Pending PO -->
  <div class="col-md-3">
    <div class="card text-center shadow-sm p-3">
      <i class="fas fa-truck-loading fa-2x text-warning mb-2"></i>
      <h6>Pending PO</h6>
      <h4><?= number_format($pending_po) ?></h4>
    </div>
  </div>
  <!-- Suppliers -->
  <div class="col-md-3">
    <div class="card text-center shadow-sm p-3">
      <i class="fas fa-industry fa-2x text-success mb-2"></i>
      <h6>Suppliers</h6>
      <h4><?= $suppliers_cnt ?></h4>
    </div>
  </div>
  <!-- Categories -->
  <div class="col-md-3">
    <div class="card text-center shadow-sm p-3">
      <i class="fas fa-tags fa-2x text-info mb-2"></i>
      <h6>Categories</h6>
      <h4><?= $cat_cnt ?></h4>
    </div>
  </div>
</div>

<!-- Category Filter -->
<div class="mb-3">
  <div class="btn-group">
    <a href="admin.php?page=inventory&cat=All#inventory"
       class="btn btn-outline-primary <?= $sel_cat==='All'?'active':'' ?>">All</a>
    <?php foreach ($categories as $c): ?>
      <a href="admin.php?page=inventory&cat=<?= urlencode($c) ?>#inventory"
         class="btn btn-outline-primary <?= $sel_cat===$c?'active':'' ?>">
        <?= htmlspecialchars($c) ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- Current Stock -->
<h5>Current Stock <?= $sel_cat!=='All'? "(Category: ".htmlspecialchars($sel_cat).")":'' ?></h5>
<table class="table table-bordered mb-4">
  <thead class="table-light">
    <tr><th>Item</th><th>Category</th><th>On Hand</th></tr>
  </thead>
  <tbody>
    <?php while($r = $stock_res->fetch_assoc()): ?>
    <tr>
      <td><?= htmlspecialchars($r['name']) ?></td>
      <td><?= htmlspecialchars($r['category']) ?></td>
      <td><?= intval($r['stock_quantity']) ?></td>
    </tr>
    <?php endwhile; ?>
  </tbody>
</table>

<!-- Create PO Form -->
<h5>Create Purchase Order</h5>
<form method="post" class="row g-2 mb-4" action="admin.php?page=inventory#inventory">
  <div class="col-md-6">
    <select name="po_ing" class="form-select" required>
      <option value="">— Select Item —</option>
      <?php
      $all = $conn->query("SELECT id,name FROM items ORDER BY name");
      while($it = $all->fetch_assoc()):
      ?>
        <option value="<?= $it['id'] ?>"><?= htmlspecialchars($it['name']) ?></option>
      <?php endwhile; ?>
    </select>
  </div>
  <div class="col-md-3">
    <input type="number" name="po_qty" min="1" class="form-control" placeholder="Qty" required>
  </div>
  <div class="col-md-3">
    <button class="btn btn-success w-100">Create PO</button>
  </div>
</form>

<!-- Recent Purchase Orders -->
<h5>Recent Purchase Orders</h5>
<table class="table table-striped">
  <thead class="table-dark">
    <tr><th>#</th><th>Item</th><th>Qty</th><th>Created At</th></tr>
  </thead>
  <tbody>
    <?php while($po = $po_res->fetch_assoc()): ?>
    <tr>
      <td><?= $po['id'] ?></td>
      <td><?= htmlspecialchars($po['name']) ?></td>
      <td><?= intval($po['quantity']) ?></td>
      <td><?= $po['created_at'] ?></td>
    </tr>
    <?php endwhile; ?>
  </tbody>
</table>
