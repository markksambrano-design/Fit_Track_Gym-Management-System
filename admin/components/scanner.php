<?php
// QR Scanner Component
// This file contains the QR scanner functionality that can be included in any admin page

// Get the current user type from URL parameter or default to 'members'
$scannerType = $_GET['type'] ?? 'members';
$scannerView = $_GET['view'] ?? 'today';
?>

<!-- QR Scanner Section -->
<div class="col-lg-6">
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-qrcode me-2"></i>QR Scanner
                <span class="badge bg-<?= $scannerType==='staff' ? 'info' : 'success' ?> ms-2"><?= ucfirst($scannerType) ?></span>
            </h6>
        </div>
        <div class="card-body text-center">
            <div id="scanner-container" style="display: none;">
                <video id="qr-video" width="100%" playsinline></video>
                <div class="alert alert-info mt-3" id="scanner-status">
                    Ready to scan <?= $scannerType ?> QR codes
                </div>
            </div>
            <div id="scanner-placeholder" class="py-5">
                <i class="fas fa-qrcode fa-5x text-gray-300 mb-3"></i>
                <h4 class="text-gray-500">QR Scanner Inactive</h4>
                <p class="text-muted">Click the "Scan QR Code" button to activate the scanner</p>
                <div class="alert alert-<?= $scannerType==='staff' ? 'info' : 'success' ?> mt-3">
                    <i class="fas fa-<?= $scannerType==='staff' ? 'user-tie' : 'users' ?> me-2"></i>
                    Currently set to scan <strong><?= ucfirst($scannerType) ?></strong> QR codes
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scanner Button (should be placed in the header area of the parent page) -->
<?php if ($scannerView === 'today'): ?>
<button class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" id="scanBtn">
    <i class="fas fa-qrcode fa-sm text-white-50"></i> Scan QR Code
</button>
<?php endif; ?>

<!-- Scanner Scripts -->
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script src="../assets/js/admin/attendance-scanner.js"></script>
