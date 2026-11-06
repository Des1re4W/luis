<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== "cashier") {
    header("Location: ../login.php");
    exit();
}

$notif_query = $pdo->query("
    SELECT COUNT(*) AS total
    FROM orders
    WHERE sent_to_cashier = 1
");
$notif_data = $notif_query->fetch(PDO::FETCH_ASSOC);
$order_count = $notif_data['total'] ?? 0;
// ========================================================
// PRODUCTS QUERY (Fixed Variant Name Concatenation)
// ========================================================
$products_stmt = $pdo->query("
    SELECT
        p.id,
        p.product_name,
        p.price,
        p.stock,
        p.image,
        'main' AS type
    FROM products p
    WHERE p.is_archived = 0
    
    UNION ALL
    
    SELECT
        v.id,
        v.variant_name AS product_name,
        v.price,
        v.stock,
        COALESCE(NULLIF(v.variant_image, ''), p.image) AS image,
        'variant' AS type
    FROM product_variants v
    JOIN products p ON v.product_id = p.id
    WHERE p.is_archived = 0
    
    ORDER BY product_name ASC
");
$products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function para mag-resolve ng Image Src tulad sa Admin & Customer Dashboard
function resolveProductImage($raw_image) {
    $img_src = '../assets/no-image.jpg';
    if (!empty($raw_image)) {
        $image_value = trim($raw_image);
        
        if (stripos($image_value, 'data:image/') === 0) {
            $img_src = $image_value;
        } elseif (filter_var($image_value, FILTER_VALIDATE_URL)) {
            $parsed_url = parse_url($image_value);
            $host = strtolower($parsed_url['host'] ?? '');
            
            if ($host === 'drive.google.com' || $host === 'docs.google.com') {
                parse_str($parsed_url['query'] ?? '', $query_params);
                $file_id = '';
                if (!empty($query_params['id'])) {
                    $file_id = $query_params['id'];
                } elseif (preg_match('#/file/d/([A-Za-z0-9_-]+)#', $parsed_url['path'] ?? '', $matches)) {
                    $file_id = $matches[1];
                }
                if ($file_id !== '') {
                    $img_src = '../drive-image.php?id=' . urlencode($file_id);
                }
            } else {
                $img_src = $image_value;
            }
        } elseif (preg_match('/^[A-Za-z0-9_-]{20,}$/', $image_value)) {
            $img_src = '../drive-image.php?id=' . urlencode($image_value);
        } else {
            $image_path = ltrim($image_value, '/');
            if (file_exists(__DIR__ . '/../' . $image_path)) {
                $img_src = '../' . htmlspecialchars($image_path, ENT_QUOTES, 'UTF-8');
            }
        }
    }
    return $img_src;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cashier POS | R. Taipan Hardware</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<style>
*{box-sizing:border-box}
html,body{margin:0;padding:0;width:100%;height:100%}
body{background:#f4f6f9;overflow:hidden}
.navbar-custom{background:#051c3c!important;min-height:72px;padding:10px 20px}
.navbar-title{font-size:1.05rem;white-space:nowrap}
.navbar-tools{display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-wrap:wrap}
.search-box{width:210px}
.main-wrapper{display:flex;width:100%;height:calc(100vh - 72px);min-height:0}
.product-section{flex:1;min-width:0;overflow-y:auto;padding:18px}
.product-item{transition:.2s}
.product-card{border:0;border-radius:12px;background:#fff;transition:.2s;height:100%}
.product-card:hover{transform:translateY(-3px);box-shadow:0 5px 15px rgba(0,0,0,.10)!important}
.prod-img{width:100%;height:160px;object-fit:cover;border-radius:9px;background:#f1f1f1}
.product-name{font-size:14px;line-height:1.3;min-height:36px}
.product-price{font-size:15px}
.product-stock{font-size:12px}
.add-cart-btn{min-height:38px}
.cart-section{width:400px;min-width:400px;background:#fff;border-left:1px solid #ddd;display:flex;flex-direction:column;padding:18px;min-height:0}
.cart-header{flex-shrink:0}
.cart-items{flex:1;min-height:0;overflow-y:auto;margin-right:-5px;padding-right:5px}
.cart-items table{margin-bottom:0}
.cart-items table td{vertical-align:middle;font-size:13px;padding:7px 4px}
.cart-footer{flex-shrink:0;border-top:1px solid #ddd;padding-top:12px}
.qty-btn{width:28px;height:28px;padding:0;line-height:1}
.cart-product-name{max-width:130px;word-break:break-word}
.checkout-btn,.clear-btn{min-height:42px}
.empty-cart{text-align:center;color:#999;padding:40px 10px}

@media(max-width:1199px){
.cart-section{width:350px;min-width:350px}
.prod-img{height:145px}
.product-section{padding:14px}
.search-box{width:180px}
}

@media(max-width:991px){
body{overflow:hidden}
.navbar-custom{min-height:auto;padding:10px 12px}
.navbar-title span{font-size:14px!important}
.navbar-tools{width:100%;margin-top:8px;justify-content:flex-start}
.search-box{flex:1;min-width:150px;width:auto}
.main-wrapper{height:calc(100vh - 110px)}
.cart-section{width:330px;min-width:330px;padding:12px}
.prod-img{height:135px}
}

@media(max-width:767px){
body{overflow:hidden}
.navbar-custom{min-height:105px;padding:8px 10px}
.navbar-custom .container-fluid{display:block}
.navbar-title{width:100%;justify-content:center}
.navbar-title img{width:30px!important;height:30px!important}
.navbar-title span{font-size:13px!important;white-space:normal}
.navbar-tools{margin-top:7px;justify-content:center;gap:5px}
.search-box{width:100%;flex:1 1 100%;height:34px}
.navbar-tools .btn{font-size:11px;padding:6px 9px}
.navbar-tools>span{font-size:11px!important;padding:0 3px!important}
.navbar-tools .text-danger{font-size:11px}
.main-wrapper{display:block;height:calc(100vh - 105px);position:relative}
.product-section{
width:100%;
height:100%;
padding:10px;
padding-bottom:55vh;
overflow-y:auto;
-webkit-overflow-scrolling:touch
}
.product-section .row{--bs-gutter-x:.5rem;--bs-gutter-y:.5rem}
.product-item{width:50%}
.product-card{padding:9px!important}
.prod-img{height:120px;margin-bottom:8px!important}
.product-name{font-size:12px;min-height:31px;margin-bottom:4px!important}
.product-price{font-size:13px}
.product-stock{font-size:11px}
.add-cart-btn{font-size:11px;min-height:34px;margin-top:7px!important}
.cart-section{
position:fixed;
z-index:1000;
left:0;
right:0;
bottom:0;
width:100%;
min-width:100%;
height:45vh;
max-height:45vh;
padding:9px 10px;
border-left:0;
border-top:2px solid #ddd;
box-shadow:0 -4px 15px rgba(0,0,0,.12);
border-radius:14px 14px 0 0
}
.cart-header{margin-bottom:6px!important}
.cart-header h5{font-size:14px;margin-bottom:7px!important}
#customerName{font-size:12px;padding:6px 9px;margin-bottom:7px!important}
.cart-items{overflow-y:auto;min-height:70px}
.cart-items table td{font-size:11px;padding:4px 2px}
.cart-product-name{max-width:95px}
.qty-btn{width:24px;height:24px;font-size:11px}
.cart-items .mx-2{margin-left:4px!important;margin-right:4px!important}
.cart-footer{padding-top:7px}
.cart-footer h5,.cart-footer h4{font-size:14px;margin-bottom:4px!important}
#amountPaid{font-size:12px;padding:6px 9px;margin-bottom:5px!important}
.cart-footer .text-danger{font-size:11px;margin-bottom:5px!important}
.checkout-btn,.clear-btn{min-height:34px;padding:5px;font-size:11px}
.empty-cart{padding:15px 5px;font-size:12px}
}

@media(max-width:380px){
.navbar-title span{font-size:12px!important}
.product-section{
padding:7px;
padding-bottom:55vh
}
.product-card{padding:7px!important}
.prod-img{height:105px}
.product-name{font-size:11px}
.product-price{font-size:12px}
.add-cart-btn{font-size:10px}
.cart-section{
height:45vh;
max-height:45vh
}
}
</style>
</head>
<body>

<nav class="navbar navbar-dark navbar-custom">
<div class="container-fluid">
<h2 class="text-white fw-bold navbar-title d-flex align-items-center gap-2 m-0">
<img src="../assets/logo.jpg" style="width:36px;height:36px;border-radius:50%;border:2px solid white;" alt="Logo" onerror="this.src='../assets/no-image.jpg';">
<span>R. Taipan Hardware - Cashier Dashboard</span>
</h2>
<div class="navbar-tools">
<input type="text" id="liveSearch" class="form-control form-control-sm search-box" placeholder="Search products..." onkeyup="filterProducts()">
<a href="onlineOrders.php" class="btn btn-outline-light btn-sm position-relative">
<i class="bi bi-globe"></i> <span>Online Orders</span>
<?php if($order_count>0): ?>
<span class="badge bg-danger position-absolute top-0 start-100 translate-middle"><?php echo $order_count; ?></span>
<?php endif; ?>
</a>
<a href="profile.php" class="btn btn-outline-light btn-sm">
<i class="bi bi-person-badge"></i> <span>My Profile</span>
</a>
<span class="text-white small">
    Welcome, <b><?php echo htmlspecialchars($_SESSION['username']); ?>!</b>
</span>
<a href="../logout.php" class="btn btn-link text-danger text-decoration-none p-1" onclick="return confirm('Are you sure you want to log out?')">
<i class="bi bi-box-arrow-right"></i> Logout
</a>
</div>
</div>
</nav>

<div class="main-wrapper">

<div class="product-section">
<div class="row g-2" id="product-list">

<?php if(!empty($products)): ?>

<?php foreach($products as $row): ?>

<?php
$imagePath = resolveProductImage($row['image']);
$productName = htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8');
$jsName = json_encode($row['product_name']);
$productId = ($row['type'] == "variant" ? "v_" : "m_") . $row['id'];
?>

<div class="col-6 col-sm-4 col-md-4 col-lg-3 product-item" data-name="<?php echo strtolower($row['product_name']); ?>">

<div class="card product-card shadow-sm p-3">

<img
src="<?php echo htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8'); ?>"
class="prod-img mb-3"
alt="<?php echo $productName; ?>"
onerror="this.onerror=null; this.src='../assets/no-image.jpg';">

<h6
class="fw-bold text-truncate product-name"
title="<?php echo $productName; ?>">
<?php echo $productName; ?>
</h6>

<p class="text-primary fw-bold product-price mb-1">
₱<?php echo number_format((float)$row['price'], 2); ?>
</p>

<small class="text-muted product-stock">
Stock: <?php echo (int)$row['stock']; ?>
</small>

<button
class="btn btn-primary btn-sm w-100 mt-3 add-cart-btn"
onclick='addToCart(
"<?php echo $productId; ?>",
<?php echo $jsName; ?>,
<?php echo (float)$row['price']; ?>,
<?php echo (int)$row['stock']; ?>
)'>
<i class="bi bi-cart-plus"></i> Add to Cart
</button>

</div>
</div>

<?php endforeach; ?>

<?php else: ?>

<div class="col-12 d-flex justify-content-center align-items-center" style="min-height:60vh;">
<div class="alert alert-light border shadow-sm mb-0 text-center">
No products found!
</div>
</div>

<?php endif; ?>

</div>
</div>

<div class="cart-section">

<div class="cart-header">
<h5 class="fw-bold mb-3">
<i class="bi bi-cart3"></i> Current Order
</h5>

<input
type="text"
id="customerName"
class="form-control mb-3"
placeholder="Sold To">
</div>

<div class="cart-items">
<table class="table align-middle">
<tbody id="cart-table"></tbody>
</table>
</div>

<div class="cart-footer">

<div class="d-flex justify-content-between align-items-center mb-2">
<h5 class="fw-bold mb-0">TOTAL</h5>
<h4 class="text-primary fw-bold mb-0">
₱<span id="total">0.00</span>
</h4>
</div>

<input
type="number"
id="amountPaid"
class="form-control mb-2"
placeholder="Paid Amount"
min="0"
step="0.01"
oninput="calcChange()">

<div class="d-flex justify-content-between mb-3 text-danger fw-bold">
<span>CHANGE</span>
<span>₱<span id="change">0.00</span></span>
</div>

<button
id="checkout-btn"
class="btn btn-success w-100 checkout-btn"
onclick="checkout()"
disabled>
<i class="bi bi-printer"></i> CHECKOUT & PRINT
</button>

<button
class="btn btn-outline-danger w-100 mt-2 clear-btn"
onclick="clearCart()">
<i class="bi bi-trash"></i> CLEAR CART
</button>

</div>
</div>
</div>

<script>
let cart=[];

function addToCart(id,name,price,stock){
stock=parseInt(stock)||0;
price=parseFloat(price)||0;

if(stock<=0){
alert("Out of stock!");
return;
}

let item=cart.find(i=>i.id===id);

if(item){
if(item.qty>=stock){
alert("Maximum available stock reached.");
return;
}
item.qty++;
}else{
cart.push({
id:id,
name:name,
price:price,
qty:1,
stock_limit:stock
});
}

renderCart();
}

function clearCart(){
if(!cart.length)return;

if(!confirm("Are you sure you want to clear the cart?"))return;

cart=[];
document.getElementById("customerName").value="";
document.getElementById("amountPaid").value="";
document.getElementById("change").innerText="0.00";

renderCart();
}

function renderCart(){
let html="";
let total=0;

if(cart.length===0){

html=`
<tr>
<td colspan="3">
<div class="empty-cart">
<i class="bi bi-cart-x fs-3 d-block mb-2"></i>
Cart is empty
</div>
</td>
</tr>`;

}else{

cart.forEach(item=>{

let subtotal=item.price*item.qty;
total+=subtotal;

html+=`
<tr>
<td class="cart-product-name">
${escapeHtml(item.name)}
</td>

<td class="text-center text-nowrap">

<button
class="btn btn-outline-secondary btn-sm qty-btn"
onclick="updateQty('${item.id}',-1)">
−
</button>

<span class="mx-2 fw-bold">
${item.qty}
</span>

<button
class="btn btn-outline-secondary btn-sm qty-btn"
onclick="updateQty('${item.id}',1)">
+
</button>

</td>

<td class="text-end fw-bold text-nowrap">
₱${subtotal.toFixed(2)}
</td>

</tr>`;

});

}

document.getElementById("cart-table").innerHTML=html;
document.getElementById("total").innerText=total.toFixed(2);
document.getElementById("checkout-btn").disabled=cart.length===0;

calcChange();
}

function updateQty(id,delta){

let item=cart.find(i=>i.id===id);

if(!item)return;

item.qty+=delta;

if(item.qty>item.stock_limit){
item.qty=item.stock_limit;
alert("Maximum available stock reached.");
}

if(item.qty<=0){
cart=cart.filter(i=>i.id!==id);
}

renderCart();
}

function calcChange(){

let total=parseFloat(
document.getElementById("total").innerText
)||0;

let paid=parseFloat(
document.getElementById("amountPaid").value
)||0;

let change=paid>=total?paid-total:0;

document.getElementById("change").innerText=
change.toFixed(2);
}

function printReceipt(transactionId){
    let cartHTML = "";

    cart.forEach(item => {
        let subtotal = item.price * item.qty;
        let name = escapeHtml(item.name);
        let qtyPrice = `${item.qty} x Php ${item.price.toFixed(2)}`;
        let subStr = `Php ${subtotal.toFixed(2)}`;
        
        // Manual spacing para maghiwalay sa kanan at kaliwa sa Text Only driver
        let spacesNeeded = Math.max(1, 32 - (qtyPrice.length + subStr.length));
        let spaceStr = " ".repeat(spacesNeeded);

        cartHTML += `<b>${name}</b>\n${qtyPrice}${spaceStr}<b>${subStr}</b>\n`;
    });

    let today = new Date();
    let formattedDate = today.toLocaleDateString("en-PH", {
        year: "numeric", month: "2-digit", day: "2-digit"
    }) + " " + today.toLocaleTimeString("en-PH", {
        hour: "2-digit", minute: "2-digit", second: "2-digit"
    });

    let cashierName = <?php echo json_encode($_SESSION['username']); ?>;
    let soldTo = document.getElementById("customerName").value.trim() || "Walk-in Customer";
    let total = parseFloat(document.getElementById("total").innerText) || 0;
    let paid = parseFloat(document.getElementById("amountPaid").value) || 0;
    let change = paid - total;

    // Helper function para sa alignment sa kanan at kaliwa gamit ang ASCII spaces
    function formatLine(left, right, isBold = false) {
        let l = String(left);
        let r = String(right);
        let spaceCount = Math.max(1, 32 - (l.length + r.length));
        let line = l + " ".repeat(spaceCount) + r;
        return isBold ? `<b>${line}</b>` : line;
    }

    let oldFrame = document.getElementById("receipt-print-frame");
    if (oldFrame) oldFrame.remove();

    let iframe = document.createElement("iframe");
    iframe.id = "receipt-print-frame";
    iframe.style.position = "fixed";
    iframe.style.right = "0";
    iframe.style.bottom = "0";
    iframe.style.width = "0";
    iframe.style.height = "0";
    iframe.style.border = "0";
    iframe.style.visibility = "hidden";
    document.body.appendChild(iframe);

    let printDocument = iframe.contentWindow.document;
    printDocument.open();

    printDocument.write(`
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Receipt</title>
<style>
@page {
    size: 58mm auto;
    margin: 0mm;
}
* {
    box-sizing: border-box;
}
html, body {
    margin: 0;
    padding: 0;
    width: 100%;
    background: #fff;
    color: #000;
}
pre.receipt {
    font-family: 'Courier New', Courier, monospace;
    font-size: 10px;
    line-height: 1.3;
    white-space: pre-wrap;
    word-break: break-all;
    margin: 0;
    padding: 2mm 1mm;
}
.center {
    text-align: center;
    display: block;
}
@media print {
    html, body {
        width: 100%;
        height: auto;
    }
}
</style>
</head>
<body>

<pre class="receipt">
<span class="center"><b><font size="3">R. TAIPAN HARDWARE</font></b>
R. Taipan Construction Supply
and Services, OPC
190 Cabrera Rd. Sitio Burol
Brgy. San Juan, Taytay, Rizal
Contact: 0907 456 5632

<b>*** OFFICIAL RECEIPT ***</b></span>
--------------------------------
${formatLine("Transaction No:", transactionId)}
${formatLine("Date:", formattedDate)}
${formatLine("Cashier:", cashierName)}
${formatLine("Sold To:", soldTo)}
--------------------------------
${cartHTML}--------------------------------
${formatLine("TOTAL AMOUNT:", "Php " + total.toFixed(2), true)}
${formatLine("Amount Paid:", "Php " + paid.toFixed(2))}
${formatLine("CHANGE:", "Php " + change.toFixed(2), true)}
--------------------------------
<span class="center">Thank you for your purchase!
Please come again.</span>
</pre>

</body>
</html>
`);

    printDocument.close();

    setTimeout(() => {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
        setTimeout(() => { iframe.remove(); }, 1000);
    }, 300);
}

function checkout(){

let total=
parseFloat(document.getElementById("total").innerText)||0;

let paid=
parseFloat(document.getElementById("amountPaid").value)||0;

if(cart.length===0){
alert("Cart is empty.");
return;
}

if(paid<total){
alert(
"Invalid payment amount. Payment must be equal to or greater than the total."
);
return;
}

const checkoutBtn=
document.getElementById("checkout-btn");

checkoutBtn.disabled=true;

checkoutBtn.innerHTML=
'<span class="spinner-border spinner-border-sm"></span> Processing...';

fetch("pos_checkout.php",{
method:"POST",
headers:{
"Content-Type":"application/json"
},
body:JSON.stringify({
cart:cart,
total:total,
paid:paid,
change:paid-total
})
})

.then(response=>response.json())

.then(data=>{

if(data.status==="success"){

printReceipt(data.transaction_id);

setTimeout(()=>{
location.reload();
},1000);

}else{

alert(data.message||"Checkout failed.");

checkoutBtn.disabled=false;

checkoutBtn.innerHTML=
'<i class="bi bi-printer"></i> CHECKOUT & PRINT';

}

})

.catch(error=>{

console.error(error);

alert(
"Checkout failed. Please try again."
);

checkoutBtn.disabled=false;

checkoutBtn.innerHTML=
'<i class="bi bi-printer"></i> CHECKOUT & PRINT';

});

}

function filterProducts(){

let searchInput=
document.getElementById("liveSearch")
.value
.toLowerCase()
.trim();

document.querySelectorAll(".product-item")
.forEach(product=>{

let productName=
product.getAttribute("data-name")||"";

product.style.display=
productName.includes(searchInput)
?""
:"none";

});

}

function escapeHtml(text){

const div=document.createElement("div");

div.textContent=text??"";

return div.innerHTML;
}

renderCart();
</script>

</body>
</html>