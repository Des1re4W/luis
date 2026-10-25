<?php
// Start session if not already started in db.php
require_once __DIR__ . '/../config/db.php';

// Initialize variables to avoid undefined variable warnings
$category = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? '';
$is_home = (!isset($_GET['view']) && $category == '' && !isset($_GET['sort']));
$sort_label = '';

// Handle your main product listing query safely using PDO (matching your db.php connection)
try {
    $sql = "SELECT p.id, p.product_name, p.category, p.price, p.stock, p.image, 
                   MIN(v.price) as min_price, MAX(v.price) as max_price, 
                   COUNT(v.id) as variant_count, 
                   COALESCE(SUM(oi.quantity), 0) as total_sold
            FROM products
            LEFT JOIN product_variants v ON p.id = v.product_id
            LEFT JOIN order_items oi ON p.id = oi.product_id
            WHERE 1=1";

    $params = [];

    if ($category != '') {
        $sql .= " AND p.category = :category";
        $params[':category'] = $category;
    }

    $sql .= "GROUP BY p.id";

    // Sorting logic
    if ($sort == 'price_low') {
        $sql .= " ORDER BY LEAST(p.price, COALESCE(MIN(v.price), p.price)) ASC";
        $sort_label = "(Price: Low to High)";
    } elseif ($sort == 'price_high') {
        $sql .= " ORDER BY GREATEST(p.price, COALESCE(MAX(v.price), p.price)) DESC";
        $sort_label = "(Price: High to Low)";
    } elseif ($sort == 'az') {
        $sql .= " ORDER BY p.product_name ASC";
        $sort_label = "(A-Z)";
    } elseif ($sort == 'za') {
        $sql .= " ORDER BY p.product_name DESC";
        $sort_label = "(Z-A)";
    } elseif ($sort == 'newest') {
        $sql .= " ORDER BY p.id DESC";
        $sort_label = "(Newest Arrivals)";
    } elseif ($sort == 'oldest') {
        $sql .= " ORDER BY p.id ASC";
        $sort_label = "(Oldest Stock)";
    } else {
        $sql .= " ORDER BY total_sold DESC"; // Default top selling
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Dashboard product load error: ' . $e->getMessage());
    $products = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard | R. Taipan Hardware</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background:#f8f9fa; }
        .navbar-custom { background:#051c3c; padding: 15px 0; }
        .nav-link { color: rgba(255,255,255,0.9) !important; font-weight: 500; }
        .nav-link:hover { color: #214a9c !important; }
        .dropdown-menu { border: none; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .product-card { transition: 0.3s; border: none; border-radius: 12px; overflow: hidden; background: #fff; }
        .product-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .product-img { height: 200px; object-fit: cover; }
        #imageZoomModal {z-index: 2050 !important;}
        #imageZoomModal .modal-backdrop {z-index: 2040 !important;}
    </style>
</head>
<body>
<div id="notif-container" style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 9999;"></div>  
<?php if (isset($_SESSION['message'])) { ?>
    <div class="alert alert-success alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x shadow" 
         style="z-index:9999; min-width:320px; max-width: 90%; margin-top: 20px; display: flex; align-items: center; justify-content: space-between;" 
         role="alert">
        <span style="flex-grow: 1; text-align: center; font-weight: 500;">
            <?php echo $_SESSION['message']; ?>
        </span>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="margin-left: 15px;"></button>
    </div>
<?php unset($_SESSION['message']); } ?>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="dashboard.php">
            <img src="../assets/logo.jpg" style="width:40px; height:40px; border-radius:50%; border:2px solid white;">
            <strong>R. Taipan Hardware</strong>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="dashboard.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="dashboard.php?view=all">All Products</a></li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="catDropdown" role="button" data-bs-toggle="dropdown">Categories</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="dashboard.php?category=Tools">Tools</a></li>
                        <li><a class="dropdown-item" href="dashboard.php?category=Electrical">Electrical</a></li>
                        <li><a class="dropdown-item" href="dashboard.php?category=Construction">Construction</a></li>
                        <li><a class="dropdown-item" href="dashboard.php?category=Plumbing">Plumbing</a></li>
                        <li><a class="dropdown-item" href="dashboard.php?category=Paint and Supplies">Paint & Supplies</a></li>
                        <li><a class="dropdown-item" href="dashboard.php?category=Chemicals">Chemicals</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-warning fw-bold" href="#" id="sortDropdown" role="button" data-bs-toggle="dropdown">Sort By</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="?sort=price_low&category=<?php echo $category; ?>">Price: Low to High</a></li>
                        <li><a class="dropdown-item" href="?sort=price_high&category=<?php echo $category; ?>">Price: High to Low</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="?sort=az&category=<?php echo $category; ?>">A-Z (Name)</a></li>
                        <li><a class="dropdown-item" href="?sort=za&category=<?php echo $category; ?>">Z-A (Name)</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="?sort=newest&category=<?php echo $category; ?>">Newest Arrivals</a></li>
                        <li><a class="dropdown-item" href="?sort=oldest&category=<?php echo $category; ?>">Oldest Stock</a></li>
                    </ul>
                </li>
                <li class="nav-item"><a class="nav-link" href="aboutus.php">About Us</a></li>
                <li class="nav-item"><a class="nav-link" href="#footer">Contact</a></li>
            </ul>

            <form class="d-flex me-3" onsubmit="return false;">
                <input class="form-control form-control-sm me-2" type="search" id="searchInput" placeholder="Search...">
            </form>
            <br>
            <div class="d-flex align-items-center">
                <a href="cart.php" class="btn btn-warning btn-sm position-relative me-3">
                    🛒 Cart
                    <?php 
                        $cartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
                        if ($cartCount > 0) { 
                    ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?php echo $cartCount; ?>
                        </span>
                    <?php } ?>
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="modal fade" id="imageZoomModal" tabindex="-1" style="z-index: 2050;">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content bg-transparent border-0">
      <div class="text-center">
        <img id="zoomImage" src="" class="img-fluid rounded shadow" style="max-height: 85vh;">
      </div>
      <div class="text-center mt-3">
        <button type="button" class="btn px-4 py-2 fw-bold shadow"
            style="background:#6c757d; color:#fff; border:0;"
            data-bs-dismiss="modal">
            Close
        </button>
      </div>
    </div>
  </div>
</div>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>
            <?php 
                if($category != '') { echo "Category: " . htmlspecialchars($category); } 
                else if(isset($_GET['view'])) { echo "All Products"; } 
                else if($is_home) { echo "🔥 Top Selling Items"; } 
                else { echo "Product List"; }
                if($sort != '') { echo " <span class='text-muted' style='font-size: 16px;'>$sort_label</span>"; }
            ?>
        </h4>
    </div>

    <div class="row row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4" id="productResults">
        <?php if (!empty($products)) { ?>
            <?php foreach($products as $row) { 
                $price_display = "₱" . number_format($row['min_price'], 2);
                if($row['variant_count'] > 0 && $row['min_price'] != $row['max_price']) {
                    $price_display .= " - ₱" . number_format($row['max_price'], 2);
                }
            ?>
            <div class="col">
                <div class="card product-card shadow-sm h-100">
                    <img src="../<?php echo !empty($row['image']) ? $row['image'] : 'assets/no-image.jpg'; ?>" class="card-img-top product-img">
                    <div class="card-body d-flex flex-column text-center">
                        <h6 class="card-title fw-bold text-dark mb-1"><?php echo htmlspecialchars($row['product_name']); ?></h6>
                        <p class="text-success fw-bold fs-6 mb-1"><?php echo $price_display; ?></p>
                        <div class="d-flex justify-content-between small mb-3">
                            <span class="text-muted italic"><?php echo $row['total_sold']; ?> sold</span>
                        </div>
                        <div class="mt-auto">
                            <button type="button" class="btn btn-outline-primary w-100 py-2 fw-bold" onclick="openProductModal(<?php echo $row['id']; ?>)">
                                <i class="bi bi-eye"></i> View Product
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php } ?>
        <?php } else { ?>
            <div class="col-12 text-center py-5">
                <div class="alert alert-light border shadow-sm">No products found!</div>
            </div>
        <?php } ?>
    </div>
</div>

<div class="modal fade" id="productDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; overflow: hidden; border: none;">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 pt-2">
                <div id="modalLoading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted small">Loading product details...</p>
                </div>
                
                <div id="modalProductContent" class="row d-none">
                    <div class="col-md-6 text-center mb-3 mb-md-0">
                        <div class="p-2 border rounded bg-light d-flex align-items-center justify-content-center" style="height: 330px;">
                            <img id="modalMainImage" 
                                src="" 
                                class="img-fluid"
                                style="max-height: 100%; object-fit: contain; cursor: pointer;"
                                onclick="openModalImageZoom()">
                        </div>
                    </div>
                    
                    <div class="col-md-6 d-flex flex-column">
                        <span id="modalCategory" class="badge bg-secondary align-self-start mb-2"></span>
                        <h4 id="modalProductName" class="fw-bold mb-1"></h4>
                        
                        <h3 class="text-success fw-bold mb-0">₱<span id="modalDisplayPrice">0.00</span></h3>
                        <p class="text-muted small mb-2">Available Stock: <span id="modalDisplayStock" class="fw-bold">0</span></p>
                        
                        <div class="mb-2 bg-light p-2 rounded border d-flex justify-content-between align-items-center">
                            <span class="text-secondary small fw-bold">Total:</span>
                            <span class="text-success fw-bold fs-5">₱<span id="modalTotalPrice">0.00</span></span>
                        </div>

                        <hr class="my-1">
                        <div class="small text-muted mb-2" style="max-height: 90px; overflow-y: auto;">
                            <strong>Product Description:</strong><br>
                            <span id="modalDescription" class="text-dark"></span>
                        </div>
                        <hr class="my-1">

                        <div class="mt-auto">
                            <div class="mb-2" id="variantDropdownContainer">
                                <label class="form-label small fw-bold mb-1">Select Variant:</label>
                                <select id="modalVariantSelect" class="form-select form-select-sm" onchange="updateModalVariantDetails()">
                                </select>
                            </div>

                            <div class="mb-3" style="max-width: 120px;">
                                <label class="form-label small fw-bold mb-1">Quantity:</label>
                                <input type="number" id="modalQtyInput" class="form-control form-control-sm" value="1" min="1" oninput="calculateLiveTotal()" onchange="validateQtyInput()">
                            </div>

                            <a href="#" id="modalAddToCartBtn" class="btn btn-warning w-100 mb-2 py-2 fw-bold">Add to Cart</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="notif-box" style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 9999; display: none;">
    <div class="alert alert-success shadow d-flex align-items-center" role="alert" style="padding: 10px 20px;">
        <i class="bi bi-check-circle-fill me-2"></i>
        <span>Successfully added to cart!</span>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// LIVE SEARCH ENGINE
function liveSearch(){
    let searchValue = document.getElementById("searchInput").value;
    let xhr = new XMLHttpRequest();
    xhr.open("POST","livesearch.php",true);
    xhr.setRequestHeader("Content-type","application/x-www-form-urlencoded");
    xhr.onload = function(){
        document.getElementById("productResults").innerHTML = this.responseText;
    };
    xhr.send("search=" + encodeURIComponent(searchValue));
}
document.getElementById("searchInput").addEventListener("keyup", liveSearch);

// DYNAMIC AJAX MODAL PARSER
function openProductModal(productId) {
    var myModal = new bootstrap.Modal(document.getElementById('productDetailsModal'));
    document.getElementById('modalLoading').classList.remove('d-none');
    document.getElementById('modalProductContent').classList.add('d-none');
    myModal.show();

   fetch(`getProductDetails.php?id=${productId}`)
    .then(response => response.json())
    .then(data => {
        if(data.error) {
            alert(data.error);
            myModal.hide();
            return;
        }

        var select = document.getElementById('modalVariantSelect');
        var dropdownContainer = document.getElementById('variantDropdownContainer');
        select.setAttribute('data-product-id', data.product.id);
        
        document.getElementById('modalProductName').innerText = data.product.product_name;
        document.getElementById('modalCategory').innerText = data.product.category;
        
        var mainProductImgPath = data.product.image ? '../' + data.product.image : '../assets/no-image.jpg';
        document.getElementById('modalMainImage').src = mainProductImgPath;

        select.innerHTML = "";

        if(!data.variants || data.variants.length === 0) {
            dropdownContainer.classList.add('d-none'); 
            
            document.getElementById('modalDescription').innerText = data.product.product_description || "No description available.";
            document.getElementById('modalDisplayPrice').innerText = parseFloat(data.product.price).toLocaleString(undefined, {minimumFractionDigits: 2});
            document.getElementById('modalDisplayStock').innerText = data.product.stock;
            
            var qtyInput = document.getElementById('modalQtyInput');
            if(parseInt(data.product.stock) <= 0) {
                qtyInput.value = 0; qtyInput.disabled = true;
            } else {
                qtyInput.disabled = false; qtyInput.max = data.product.stock; qtyInput.value = 1;
            }
            
            calculateLiveTotal();
            
        } else {
            dropdownContainer.classList.remove('d-none'); 
            
            var defaultOpt = document.createElement('option');
            defaultOpt.value = "MAIN_PRODUCT";
            
            var mainItemName = data.product.product_name.toUpperCase();
            defaultOpt.innerText = `${mainItemName} (₱${parseFloat(data.product.price).toLocaleString(undefined, {minimumFractionDigits:2})})`;
            
            defaultOpt.setAttribute('data-price', data.product.price);
            defaultOpt.setAttribute('data-stock', data.product.stock);
            
            var mainDesc = data.product.product_description ? data.product.product_description : "No description available.";
            defaultOpt.setAttribute('data-desc', mainDesc);
            defaultOpt.setAttribute('data-image', mainProductImgPath); 
            select.appendChild(defaultOpt);

            data.variants.forEach(v => {
                var opt = document.createElement('option');
                opt.value = v.id;
                opt.innerText = v.variant_name.toUpperCase();
                opt.setAttribute('data-price', v.price);
                opt.setAttribute('data-stock', v.stock);
                
                var vDesc = (v.variant_description && v.variant_description.trim() !== "") ? v.variant_description : data.product.product_description;
                opt.setAttribute('data-desc', vDesc ? vDesc : "No description available.");
                
                var vImg = v.variant_image ? '../' + v.variant_image : mainProductImgPath;
                opt.setAttribute('data-image', vImg);
                
                select.appendChild(opt);
            });

            updateModalVariantDetails();
        }

        document.getElementById('modalLoading').classList.add('d-none');
        document.getElementById('modalProductContent').classList.remove('d-none');
    })
    .catch(err => {
        console.error(err);
        alert("Error loading product data.");
        myModal.hide();
    });
}

function showNotification(message) {
    const container = document.getElementById('notif-container');
    const alert = document.createElement('div');
    alert.className = 'alert alert-success shadow';
    alert.innerHTML = message;
    container.appendChild(alert);

    setTimeout(() => {
        alert.remove();
    }, 3000);
}

function updateModalVariantDetails() {
    var select = document.getElementById('modalVariantSelect');
    if(select.options.length === 0 || select.value === "") return;

    var selectedOption = select.options[select.selectedIndex];
    var variantName = selectedOption.innerText;
    var baseProductName = document.getElementById('modalProductName').innerText;

    if (select.value === "MAIN_PRODUCT") {
        document.getElementById('modalProductName').innerText = baseProductName;
    } else {
        document.getElementById('modalProductName').innerText = variantName;
    }
    
    var price = parseFloat(selectedOption.getAttribute('data-price')).toFixed(2);
    var stock = parseInt(selectedOption.getAttribute('data-stock'));
    var description = selectedOption.getAttribute('data-desc');
    var imageSrc = selectedOption.getAttribute('data-image'); 
    
    document.getElementById('modalDescription').innerText = description;
    document.getElementById('modalDisplayPrice').innerText = parseFloat(price).toLocaleString(undefined, {minimumFractionDigits: 2});
    document.getElementById('modalDisplayStock').innerText = stock;
    
    if(imageSrc) {
        document.getElementById('modalMainImage').src = imageSrc;
    }
    
    var qtyInput = document.getElementById('modalQtyInput');
    if(stock <= 0) {
        qtyInput.max = 0; qtyInput.value = 0; qtyInput.disabled = true;
    } else {
        qtyInput.disabled = false; qtyInput.max = stock;
        if(parseInt(qtyInput.value) <= 0 || qtyInput.value == "") { qtyInput.value = 1; }
    }
    calculateLiveTotal();
}

function calculateLiveTotal() {
    var priceText = document.getElementById('modalDisplayPrice').innerText.replace(/,/g, '');
    var price = parseFloat(priceText) || 0;
    
    var qtyInput = document.getElementById('modalQtyInput');
    
    if (qtyInput.value === "") {
        document.getElementById('modalTotalPrice').innerText = "0.00";
        updateLinkParameters();
        return;
    }

    var qty = parseInt(qtyInput.value) || 0;
    var maxStock = parseInt(qtyInput.max) || 0;
    
    if (qty > maxStock) {
        qty = maxStock;
        qtyInput.value = maxStock;
    }

    var total = price * qty;
    document.getElementById('modalTotalPrice').innerText = total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    updateLinkParameters();
}

function validateQtyInput() {
    var qtyInput = document.getElementById('modalQtyInput');
    var maxStock = parseInt(qtyInput.max) || 0;
    var qty = parseInt(qtyInput.value) || 0;

    if (qty < 1 && maxStock > 0) {
        qtyInput.value = 1;
        calculateLiveTotal();
    }
}

function updateLinkParameters() {
    var select = document.getElementById('modalVariantSelect');
    var dropdownContainer = document.getElementById('variantDropdownContainer');
    var productId = select.getAttribute('data-product-id');
    var qty = document.getElementById('modalQtyInput').value;

    if(dropdownContainer.classList.contains('d-none') || select.value === "MAIN_PRODUCT") {
        document.getElementById('modalAddToCartBtn').href = `addtocart.php?id=${productId}&qty=${qty}`;
    } else {
        var variantId = select.value;
        document.getElementById('modalAddToCartBtn').href = `addtocart.php?id=${productId}&variant_id=${variantId}&qty=${qty}`;
    }
}

function openModalImageZoom() {
    const imgSrc = document.getElementById('modalMainImage').src;
    document.getElementById('zoomImage').src = imgSrc;

    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());

    var modal = new bootstrap.Modal(document.getElementById('imageZoomModal'), {
        backdrop: true,
        keyboard: true
    });

    modal.show();
}

const urlParams = new URLSearchParams(window.location.search);
if (urlParams.has('notif') && urlParams.get('notif') === 'added') {
    const notif = document.getElementById('notif-box');
    notif.style.display = 'block';
    
    setTimeout(() => {
        notif.style.display = 'none';
        window.history.replaceState({}, document.title, window.location.pathname);
    }, 3000);
}
</script>

<footer id="footer" class="bg-dark text-white mt-5 pt-5 pb-3">
    <div class="container">
        <div class="row">
            <div class="col-md-3 text-center text-md-start">
                <img src="../assets/logo.jpg" style="width:70px; height:70px; border-radius:50%; border:2px solid #ccc;">
                <h5 class="mt-2">R. Taipan Construction Supply and Services</h5>
                <p class="text-muted small">Your trusted supplier of roofing and construction materials.</p>
            </div>
            <div class="col-md-3">
                <h5>Contact Us</h5>
                <p class="small">📞 0907 456 5632<br>📧 rtaipancssopc@gmail.com</p><br> 
                <h5>Store Hours</h5>
                <p class="small">⏰ 8:00AM - 5PM | MON. - SAT.</p>
            </div>
            <div class="col-md-3">
                <h5>Branches</h5>
                <p class="small mb-0">📍 190 Cabrera Rd. Sitio Burol Brgy. San Juan Taytay, Rizal (Main Branch)</p>
                <a href="https://www.google.com/maps/place/Ronel+Steel+Roof+Trading+and+Hardware+Supply/@14.5602582,121.1374471,17z/data=!3m1!4b1!4m6!3m5!1s0x3397c727501f019b:0xe2dfa8a1b3aec65f!8m2!3d14.5602583!4d121.142318!16s%2Fg%2F11t2lf3b8r?entry=ttu&g_ep=EgoyMDI2MDQyOS4wIKXMDSoASAFQAw%3D%3D" target="_blank" class="text-info small d-block mb-2">View Map</a>
                <p class="small mb-0">📍 Gerona-Guimba Rd. Purok Masagana Brgy. Abagon Gerona Tarlac</p>
                <a href="https://maps.app.goo.gl/EV3Bm6fdCaLFzzFi9" target="_blank" class="text-info small">View Map</a>
                <p class="small mb-0">📍 Sitio Bato-bato 1 Brgy. San Juan Taytay, Rizal</p>
                <a href="https://www.google.com/maps/place/R.+TAIPAN+CONSTRUCTION+SUPPLY+%26+SERVICES/@14.5644077,121.148692,149m/data=!3m1!1e3!4m15!1m8!3m7!1s0x3397c742f1b3202b:0x1fac35e429b5b598!2sCabrera+Rd,+Taytay,+1920+Rizal!3b1!8m2!3d14.5645132!4d121.1468918!16s%2Fg%2F1tg76l83!3m5!1s0x3397c70059cfff9b:0xd3229729a93f8cf5!8m2!3d14.5643595!4d121.1495273!16s%2Fg%2F11yrsfnwtl?entry=ttu&g_ep=EgoyMDI2MDQyOS4wIKXMDSoASAFQAw%3D%3D" class="text-info small">View Map</a>
            </div>
            <div class="col-md-3 mb-4">
                <h5>Follow Us on Facebook</h5>
                <a href="https://www.facebook.com/share/1Acq7mfhEZ/?mibextid=wwXIfr" class="text-info small">View Facebook Page</a><br><br>
                 <h5>Need Help?</h5>
                <a href="customerService.php" target="_blank" class="btn btn-primary btn-sm shadow-sm">
                    <i class="bi bi-telephone-fill"></i> Customer Service
                </a>
            </div>
        </div>
        <hr>
        <div class="text-center small">© 2026 R. Taipan Hardware Store. All Rights Reserved.</div>
    </div>
</footer>
</div>
</body>
</html>