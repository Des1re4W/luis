
<?php
session_start();
require_once __DIR__ . '/../config/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GCash and Maya Payment QR Codes</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f8f9fa;
            padding: 30px 15px;
        }

        .qr-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            max-width: 700px;
            margin: auto;
            padding: 30px;
        }

        .qr-image {
            width: 100%;
            max-width: 250px;
            height: auto;
            border: 2px solid #eee;
            border-radius: 10px;
            padding: 8px;
            background-color: #fff;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .qr-image:hover {
            transform: scale(1.03);
        }

        .channel-box {
            background: #fafafa;
            border: 1px solid #eef0f2;
            border-radius: 12px;
            padding: 20px 12px;
            height: 100%;
        }

        .payment-info {
            background: #f0f5ff;
            border-left: 4px solid #051c3c;
            border-radius: 8px;
            padding: 15px;
            text-align: left;
        }

        .payment-step {
            color: #555;
            font-size: 14px;
            line-height: 1.7;
        }

        .btn-close-page {
            background-color: #051c3c;
            color: white;
            border: none;
        }

        .btn-close-page:hover {
            background-color: #00142c;
            color: white;
        }

        @media (max-width: 576px) {
            .qr-card {
                padding: 20px 15px;
            }

            .channel-box {
                padding: 15px 8px;
            }

            .qr-image {
                max-width: 100%;
            }
        }
    </style>
</head>

<body>

<div class="container text-center">
    <div class="qr-card">

        <h4 class="fw-bold text-dark mb-2">
            GCash and Maya Payment
        </h4>

        <p class="text-muted small mb-4">
            Scan either of the QR codes below to complete your payment.
            You may use GCash or Maya for either full payment or
            the required 50% down payment.
        </p>

        <div class="payment-info mb-4">
            <h6 class="fw-bold text-dark mb-2">
                <i class="bi bi-info-circle"></i> Payment Instructions
            </h6>

            <ol class="payment-step mb-0 ps-3">
                <li>Choose your preferred payment method: GCash or Maya.</li>
                <li>Scan the corresponding QR code below.</li>
                <li>Pay the exact amount required for your order,
                    either full payment or 50% down payment.</li>
                <li>After completing the transaction, save or take
                    a screenshot of your payment receipt.</li>
                <li>Upload your payment receipt through the checkout form.</li>
                <li>The system will automatically scan your receipt
                    using OCR to extract and validate the payment details.</li>
            </ol>
        </div>

        <div class="row g-3">

            <!-- MAYA -->
            <div class="col-6">
                <div class="channel-box">

                    <h6 class="fw-bold text-info mb-3">
                        Maya QR Code
                    </h6>

                    <img
                        src="../assets/qr/maya.jpg"
                        alt="Maya Payment QR Code"
                        class="qr-image mb-3"
                        onclick="openModal('../assets/qr/maya.jpg')"
                    >

                    <p class="small text-secondary mb-0">
                        Account Name:
                        <b>Melanie Taipan</b>
                    </p>

                </div>
            </div>

            <!-- GCASH -->
            <div class="col-6">
                <div class="channel-box">

                    <h6 class="fw-bold text-primary mb-3">
                        GCash QR Code
                    </h6>

                    <img
                        src="../assets/qr/gcash.jpg"
                        alt="GCash Payment QR Code"
                        class="qr-image mb-3"
                        onclick="openModal('../assets/qr/gcash.jpg')"
                    >

                    <p class="small text-secondary mb-0">
                        Account Name:
                        <b>R**L T.</b>
                    </p>

                </div>
            </div>

        </div>

        <div class="alert alert-warning text-start small mt-4 mb-0">
            <b>Important Reminder:</b>
            Make sure that the amount shown on your uploaded receipt
            matches the required payment amount. Receipts showing
            insufficient payment will not be accepted for checkout.
            Please ensure that the reference number and transaction
            details are visible and readable.
        </div>

        <button
            type="button"
            onclick="window.close()"
            class="btn btn-close-page btn-sm w-100 mt-4 py-2 fw-bold">
            Done Scanning (Close Window)
        </button>

    </div>
</div>

<!-- QR IMAGE MODAL -->
<div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-body p-2">
                <img
                    src=""
                    id="modalImage"
                    class="img-fluid w-100"
                    alt="Enlarged Payment QR Code"
                >
            </div>

            <div class="modal-footer justify-content-center">
                <button
                    type="button"
                    class="btn btn-secondary"
                    data-bs-dismiss="modal">
                    Close
                </button>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
function openModal(imageSrc) {
    document.getElementById('modalImage').src = imageSrc;

    var myModal = new bootstrap.Modal(
        document.getElementById('qrModal')
    );

    myModal.show();
}
</script>

</body>
</html>
