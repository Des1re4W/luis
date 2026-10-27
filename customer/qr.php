<?php
session_start();
require_once __DIR__ . '/../config/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan QR Code for Downpayment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; padding: 30px 0; }
        .qr-card {
            background: white; border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            max-width: 650px; margin: auto; padding: 25px;
        }
        .qr-image {
            max-width: 100%; height: auto;
            border: 2px solid #eee; border-radius: 10px;
            padding: 8px; background-color: #fff;
            cursor: pointer; /* Para malaman na clickable */
            transition: transform 0.2s;
        }
        .qr-image:hover { transform: scale(1.02); }
        .channel-box {
            background: #fafafa; border: 1px solid #eef0f2;
            border-radius: 12px; padding: 15px; height: 100%;
        }
    </style>
</head>
<body>

<div class="container text-center">
    <div class="qr-card">
        <h4 class="fw-bold text-dark mb-1">Downpayment Channels</h4>
        <p class="text-muted small mb-4">
           Please scan either of the QR codes below to pay your required 50% down payment. 
           After completing the payment, take a screenshot or photo of the payment receipt and 
           upload it through the checkout form.
        </p>
        
        <div class="row g-3">          
            <div class="col-6">
                <div class="channel-box">
                    <h6 class="fw-bold text-info mb-2">💳 Maya QR Code</h6>
                    <img src="../assets/qr/maya.jpg" alt="Maya QR Code" class="qr-image mb-2" onclick="openModal('../assets/qr/maya.jpg')">             
                    <p class="small text-secondary mb-0">Name: <b>Melanie Taipan</b></p>
                </div>
            </div>
            <div class="col-6">
                <div class="channel-box">
                    <h6 class="fw-bold text-primary mb-2">💰 GCash QR Code</h6>
                    <img src="../assets/qr/gcash.jpg" alt="GCash QR Code" class="qr-image mb-2" onclick="openModal('../assets/qr/gcash.jpg')">
                    <p class="small text-secondary mb-0">Name: <b>R**L T.</b></p>
                </div>
            </div>
        </div>
        
        <button onclick="window.close()" class="btn btn-secondary btn-sm w-100 mt-4 py-2 fw-bold">
            Done Scanning (Close Window)
        </button>
    </div>
</div>

<div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body p-0">
        <img src="" id="modalImage" class="img-fluid w-100">
      </div>
      <div class="modal-footer justify-content-center">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openModal(imageSrc) {
    document.getElementById('modalImage').src = imageSrc;
    var myModal = new bootstrap.Modal(document.getElementById('qrModal'));
    myModal.show();
}
</script>
</body>
</html>