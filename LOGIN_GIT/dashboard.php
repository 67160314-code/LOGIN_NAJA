<?php
// dashboard.php
require __DIR__ . '/config_mysqli.php'; // session + $mysqli

// 1) ตรวจสอบ login
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 2) ตรวจสอบ mysqli
if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    http_response_code(500);
    exit('Database connection not available. Check config_mysqli.php');
}
$mysqli->set_charset('utf8mb4');

// Helper: ดึงหลายแถว
function fetch_all($mysqli, $sql) {
    $res = $mysqli->query($sql);
    if (!$res) return [];
    $rows = [];
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    $res->free();
    return $rows;
}

// ดึงข้อมูลจาก Views
$monthly       = fetch_all($mysqli, "SELECT ym, net_sales FROM v_monthly_sales");
$category      = fetch_all($mysqli, "SELECT category, net_sales FROM v_sales_by_category");
$region        = fetch_all($mysqli, "SELECT region, net_sales FROM v_sales_by_region");
$topProducts   = fetch_all($mysqli, "SELECT product_name, qty_sold, net_sales FROM v_top_products");
$payment       = fetch_all($mysqli, "SELECT payment_method, net_sales FROM v_payment_share");
$hourly        = fetch_all($mysqli, "SELECT hour_of_day, net_sales FROM v_hourly_sales");
$newReturning  = fetch_all($mysqli, "SELECT date_key, new_customer_sales, returning_sales FROM v_new_vs_returning ORDER BY date_key");

// KPI 30 วัน
$kpis = fetch_all($mysqli, "
SELECT 
    COALESCE(SUM(net_amount),0) AS sales_30d,
    COALESCE(SUM(quantity),0) AS qty_30d,
    COALESCE(COUNT(DISTINCT customer_id),0) AS buyers_30d
FROM fact_sales
WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
");
$kpi = $kpis ? $kpis[0] : ['sales_30d'=>0,'qty_30d'=>0,'buyers_30d'=>0];

// Number format helper
function nf($n){ return number_format((float)$n, 2); }

// User display
$user_display = htmlspecialchars($_SESSION['user_name'] ?? 'User');

?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Retail DW Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<style>
body { background:#0f172a; color:#e2e8f0; min-height:100vh; }
.card { background:#111827; border:1px solid rgba(255,255,255,.06); border-radius:1rem; }
.card h5 { color:#e5e7eb; }
.kpi { font-size:1.4rem; font-weight:700; }
.sub { color:#93c5fd; font-size:.9rem; }
.grid { display:grid; gap:1rem; grid-template-columns:repeat(12,1fr); }
.col-12{grid-column:span 12;} .col-6{grid-column:span 6;} .col-4{grid-column:span 4;} .col-8{grid-column:span 8;}
@media (max-width:991px){.col-6,.col-4,.col-8{grid-column:span 12;}}
canvas{max-height:360px;width:100% !important;}
.topbar{display:flex;gap:.5rem;align-items:center;}
</style>
</head>
<body class="p-3 p-md-4">
<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="mb-0">ยอดขาย (Retail DW) — Dashboard</h2>
        <div class="topbar">
            <span class="sub">แหล่งข้อมูล: MySQL (mysqli)</span>
            <div class="vr" style="height:28px;margin:0 8px;border-color:rgba(255,255,255,.06)"></div>
            <span class="sub">👋 <?= $user_display ?></span>
            <a href="logout.php" class="btn btn-sm btn-danger ms-2">ออกจากระบบ</a>
        </div>
    </div>

    <!-- KPI -->
    <div class="grid mb-3">
        <div class="card p-3 col-4"><h5>ยอดขาย 30 วัน</h5><div class="kpi">฿<?= nf($kpi['sales_30d']) ?></div></div>
        <div class="card p-3 col-4"><h5>จำนวนชิ้นขาย 30 วัน</h5><div class="kpi"><?= number_format((int)$kpi['qty_30d']) ?> ชิ้น</div></div>
        <div class="card p-3 col-4"><h5>จำนวนผู้ซื้อ 30 วัน</h5><div class="kpi"><?= number_format((int)$kpi['buyers_30d']) ?> คน</div></div>
    </div>

    <!-- Charts -->
    <div class="grid">
        <div class="card p-3 col-8"><h5 class="mb-2">ยอดขายรายเดือน (2 ปี)</h5><canvas id="chartMonthly"></canvas></div>
        <div class="card p-3 col-4"><h5 class="mb-2">สัดส่วนยอดขายตามหมวด</h5><canvas id="chartCategory"></canvas></div>
        <div class="card p-3 col-6"><h5 class="mb-2">Top 10 สินค้าขายดี</h5><canvas id="chartTopProducts"></canvas></div>
        <div class="card p-3 col-6"><h5 class="mb-2">ยอดขายตามภูมิภาค</h5><canvas id="chartRegion"></canvas></div>
        <div class="card p-3 col-6"><h5 class="mb-2">วิธีการชำระเงิน</h5><canvas id="chartPayment"></canvas></div>
        <div class="card p-3 col-6"><h5 class="mb-2">ยอดขายรายชั่วโมง</h5><canvas id="chartHourly"></canvas></div>
        <div class="card p-3 col-12"><h5 class="mb-2">ลูกค้าใหม่ vs ลูกค้าเดิม (รายวัน)</h5><canvas id="chartNewReturning"></canvas></div>
    </div>
</div>

<script>
// PHP -> JS
const monthly = <?= json_encode(array_values($monthly), JSON_UNESCAPED_UNICODE) ?: '[]' ?>;
const category = <?= json_encode(array_values($category), JSON_UNESCAPED_UNICODE) ?: '[]' ?>;
const region = <?= json_encode(array_values($region), JSON_UNESCAPED_UNICODE) ?: '[]' ?>;
const topProducts = <?= json_encode(array_values($topProducts), JSON_UNESCAPED_UNICODE) ?: '[]' ?>;
const payment = <?= json_encode(array_values($payment), JSON_UNESCAPED_UNICODE) ?: '[]' ?>;
const hourly = <?= json_encode(array_values($hourly), JSON_UNESCAPED_UNICODE) ?: '[]' ?>;
const newReturning = <?= json_encode(array_values($newReturning), JSON_UNESCAPED_UNICODE) ?: '[]' ?>;

// helper
const toXY = (arr,x,y)=>({ labels: arr.map(o=>o[x]), values: arr.map(o=>parseFloat(o[y]||0)) });

// Monthly
(() => {
    const {labels, values} = toXY(monthly,'ym','net_sales');
    new Chart(document.getElementById('chartMonthly'), {
        type:'line',
        data:{ labels, datasets:[{ label:'ยอดขาย (฿)', data:values, tension:.25, fill:true }] },
        options:{ plugins:{ legend:{ labels:{ color:'#e5e7eb' } } }, scales:{
            x:{ ticks:{ color:'#c7d2fe' }, grid:{ color:'rgba(255,255,255,.08)' } },
            y:{ ticks:{ color:'#c7d2fe' }, grid:{ color:'rgba(255,255,255,.08)' } }
        }}
    });
})();

// Category
(() => {
    const {labels, values} = toXY(category,'category','net_sales');
    new Chart(document.getElementById('chartCategory'), {
        type:'doughnut',
        data:{ labels, datasets:[{ data:values }] },
        options:{ plugins:{ legend:{ position:'bottom', labels:{ color:'#e5e7eb' } } } }
    });
})();

// Top Products
(() => {
    const labels = topProducts.map(o=>o.product_name);
    const qty = topProducts.map(o=>parseInt(o.qty_sold||0));
    new Chart(document.getElementById('chartTopProducts'), {
        type:'bar',
        data:{ labels, datasets:[{ label:'ชิ้นที่ขาย', data:qty }] },
        options:{ indexAxis:'y', plugins:{ legend:{ labels:{ color:'#e5e7eb' } } }, scales:{
            x:{ ticks:{ color:'#c7d2fe' }, grid:{ color:'rgba(255,255,255,.08)' } },
            y:{ ticks:{ color:'#c7d2fe' }, grid:{ color:'rgba(255,255,255,.08)' } }
        }}
    });
})();

// Region
(() => {
    const {labels, values} = toXY(region,'region','net_sales');
    new Chart(document.getElementById('chartRegion'), {
        type:'bar',
        data:{ labels, datasets:[{ label:'ยอดขาย (฿)', data:values }] },
        options:{ plugins:{ legend:{ labels:{ color:'#e5e7eb' } } }, scales:{
            x:{ ticks:{ color:'#c7d2fe' }, grid:{ color:'rgba(255,255,255,.08)' } },
            y:{ ticks:{ color:'#c7d2fe' }, grid:{ color:'rgba(255,255,255,.08)' } }
        }}
    });
})();

// Payment
(() => {
    const {labels, values} = toXY(payment,'payment_method','net_sales');
    new Chart(document.getElementById('chartPayment'), {
        type:'pie',
        data:{ labels, datasets:[{ data:values }] },
        options:{ plugins:{ legend:{ position:'bottom', labels:{ color:'#e5e7eb' } } } }
    });
})();

// Hourly
(() => {
    const {labels, values} = toXY(hourly,'hour_of_day','net_sales');
    new Chart(document.getElementById('chartHourly'), {
        type:'bar',
        data:{ labels, datasets:[{ label:'ยอดขาย (฿)', data:values }] },
        options:{ plugins:{ legend:{ labels:{ color:'#e5e7eb' } } }, scales:{
            x:{ ticks:{ color:'#c7d2fe' }, grid:{ color:'rgba(255,255,255,.08)' } },
            y:{ ticks:{ color:'#c7d2fe' }, grid:{ color:'rgba(255,255,255,.08)' } }
        }}
    });
})();

// New vs Returning
(() => {
    const labels = newReturning.map(o=>o.date_key);
    const newC = newReturning.map(o=>parseFloat(o.new_customer_sales||0));
    const retC = newReturning.map(o=>parseFloat(o.returning_sales||0));
    new Chart(document.getElementById('chartNewReturning'), {
        type:'line',
        data:{ labels, datasets:[
            { label:'ลูกค้าใหม่ (฿)', data:newC, tension:.25, fill:false },
            { label:'ลูกค้าเดิม (฿)', data:retC, tension:.25, fill:false }
        ]},
        options:{ plugins:{ legend:{ labels:{ color:'#e5e7eb' } } }, scales:{
            x:{ ticks:{ color:'#c7d2fe', maxTicksLimit:12 }, grid:{ color:'rgba(255,255,255,.08)' } },
            y:{ ticks:{ color:'#c7d2fe' }, grid:{ color:'rgba(255,255,255,.08)' } }
        }}
    });
})();
</script>
</body>
</html>
