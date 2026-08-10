<?php
$page_title = "QR Scanner";
include 'components/header.php';
include '../includes/db.php';

$type = $_GET['type'] ?? 'members'; // 'members' or 'staff'
$redirect = $_GET['redirect'] ?? 'attendance.php'; // Where to redirect after scanning
?>

<!-- Custom Styles -->
<link rel="stylesheet" href="../assets/css/admin/attendance-scanner.css">

<!-- Modern Scanner Header -->
<div class="scanner-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-6">
                <div class="scanner-title">
                    <h1 class="scanner-main-title">
                        <i class="fas fa-qrcode scanner-icon"></i>
                        QR Code Scanner
                    </h1>
                    <p class="scanner-subtitle">Quick and accurate attendance tracking</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="scanner-controls">
                    <div class="type-selector">
                        <a href="index.php?page=scanner&type=members&redirect=<?= urlencode($redirect) ?>" 
                           class="type-btn <?= $type==='members' ? 'active' : '' ?>" data-type="members">
                            <i class="fas fa-users"></i>
                            <span>Members</span>
                        </a>
                        <a href="index.php?page=scanner&type=staff&redirect=<?= urlencode($redirect) ?>" 
                           class="type-btn <?= $type==='staff' ? 'active' : '' ?>" data-type="staff">
                            <i class="fas fa-user-tie"></i>
                            <span>Staff</span>
                        </a>
                    </div>
                    <button class="scanner-toggle-btn" id="scanBtn">
                        <i class="fas fa-qrcode"></i>
                        <span>Start Scanner</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Scanner Interface -->
<div class="scanner-main">
    <div class="container-fluid">
        <div class="row">
            <!-- Scanner Section -->
            <div class="col-lg-8">
                <div class="scanner-card">
                    <div class="scanner-card-header">
                        <div class="scanner-info">
                            <h3 class="scanner-card-title">
                                <i class="fas fa-camera"></i>
                                Live Scanner
                            </h3>
                            <div class="scanner-badge">
                                <span class="badge-text"><?= ucfirst($type) ?> Mode</span>
                            </div>
                        </div>
                        <div class="scanner-stats">
                            <div class="stat-item">
                                <i class="fas fa-eye"></i>
                                <span id="scan-count">0</span>
                                <small>Scans Today</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="scanner-content">
                        <!-- Scanner Placeholder -->
                        <div id="scanner-placeholder" class="scanner-placeholder">
                            <div class="placeholder-content">
                                <div class="qr-icon-container">
                                    <i class="fas fa-qrcode"></i>
                                </div>
                                <h4 class="placeholder-title">Scanner Ready</h4>
                                <p class="placeholder-text">Click "Start Scanner" to begin scanning <?= $type ?> QR codes</p>
                                <div class="scanner-tips">
                                    <div class="tip-item">
                                        <i class="fas fa-lightbulb"></i>
                                        <span>Ensure good lighting for better scanning</span>
                                    </div>
                                    <div class="tip-item">
                                        <i class="fas fa-mobile-alt"></i>
                                        <span>Hold QR code steady in front of camera</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Active Scanner -->
                        <div id="scanner-container" class="scanner-container" style="display: none;">
                            <div class="video-wrapper">
                                <video id="qr-video" playsinline autoplay muted></video>
                                <div class="scan-overlay">
                                    <div class="scan-frame">
                                        <div class="corner top-left"></div>
                                        <div class="corner top-right"></div>
                                        <div class="corner bottom-left"></div>
                                        <div class="corner bottom-right"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="scanner-status" id="scanner-status">
                                <i class="fas fa-circle status-indicator"></i>
                                <span class="status-text">Ready to scan <?= $type ?> QR codes</span>
                                <div class="mode-indicator">
                                    <span class="mode-badge mode-<?= $type ?>">
                                        <i class="fas fa-<?= $type === 'members' ? 'users' : 'user-tie' ?>"></i>
                                        <?= ucfirst($type) ?> Mode
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Scans Sidebar -->
            <div class="col-lg-4">
                <div class="scans-sidebar">
                    <div class="sidebar-header">
                        <h4 class="sidebar-title">
                            <i class="fas fa-history"></i>
                            Recent Scans
                        </h4>
                        <button class="clear-btn" onclick="clearScanHistory()" title="Clear scan history">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    
                    <div class="scans-content">
                        <div id="recent-scans" class="scans-list">
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>No recent scans</p>
                                <small>Start scanning to see activity here</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions Bar -->
<div class="quick-actions">
    <div class="container-fluid">
        <div class="actions-content">
            <div class="action-item">
                <i class="fas fa-info-circle"></i>
                <span>Scanner Status: <strong id="global-status">Ready</strong></span>
            </div>
            <div class="action-item">
                <i class="fas fa-clock"></i>
                <span>Last Scan: <strong id="last-scan-time">Never</strong></span>
            </div>
            <div class="action-item">
                <i class="fas fa-chart-line"></i>
                <span>Success Rate: <strong id="success-rate">100%</strong></span>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script>
// QR Scanner Implementation for dedicated scanner page
const scanBtn = document.getElementById('scanBtn');
const scannerContainer = document.getElementById('scanner-container');
const scannerPlaceholder = document.getElementById('scanner-placeholder');
const video = document.getElementById('qr-video');
const scannerStatus = document.getElementById('scanner-status');
const recentScans = document.getElementById('recent-scans');
const globalStatus = document.getElementById('global-status');
const lastScanTime = document.getElementById('last-scan-time');
const successRate = document.getElementById('success-rate');

let scanning = false;
let stream = null;
let scanHistory = [];
let animationFrameId = null;
let totalScans = 0;
let successfulScans = 0;

// Get current type from URL
const currentType = '<?= $type ?>';

// Load scan history from localStorage on page load
function loadScanHistory() {
    const historyKey = `scannerHistory_${currentType}`;
    const savedHistory = localStorage.getItem(historyKey);
    if (savedHistory) {
        try {
            scanHistory = JSON.parse(savedHistory);
            
            // Clean up old entries (older than 24 hours)
            const now = new Date();
            const oneDayAgo = now.getTime() - (24 * 60 * 60 * 1000);
            
            scanHistory = scanHistory.filter(scan => {
                const scanTime = new Date(scan.timestamp);
                return scanTime.getTime() > oneDayAgo;
            });
            
            updateScanHistoryDisplay();
        } catch (e) {
            console.error('Error loading scan history:', e);
            scanHistory = [];
        }
    }
}

// Save scan history to localStorage
function saveScanHistory() {
    try {
        const historyKey = `scannerHistory_${currentType}`;
        localStorage.setItem(historyKey, JSON.stringify(scanHistory));
    } catch (e) {
        console.error('Error saving scan history:', e);
    }
}


// Load scan history when page loads
document.addEventListener('DOMContentLoaded', () => {
    loadScanHistory();
    
    
    if (scanHistory.length > 0) {
        // Show a brief notification that scan history was loaded
        const notification = document.createElement('div');
        notification.className = 'alert alert-info alert-dismissible fade show position-fixed';
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 300px;';
        notification.innerHTML = `
            <i class="fas fa-history me-2"></i>
            Loaded ${scanHistory.length} recent scan${scanHistory.length > 1 ? 's' : ''}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(notification);
        
        // Auto-remove after 3 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 3000);
    }
});

scanBtn.addEventListener('click', () => {
    if (scanning) {
        stopScanner();
        updateScanButton(false);
    } else {
        startScanner();
        updateScanButton(true);
    }
    scanning = !scanning;
});

// Update scan button appearance
function updateScanButton(isScanning) {
    const icon = scanBtn.querySelector('i');
    const text = scanBtn.querySelector('span');
    
    if (isScanning) {
        icon.className = 'fas fa-stop';
        text.textContent = 'Stop Scanner';
        scanBtn.classList.add('scanning');
    } else {
        icon.className = 'fas fa-qrcode';
        text.textContent = 'Start Scanner';
        scanBtn.classList.remove('scanning');
    }
}

// Update global status
function updateGlobalStatus(status, type = 'info') {
    if (globalStatus) {
        globalStatus.textContent = status;
        globalStatus.className = `status-${type}`;
    }
}

// Update last scan time
function updateLastScanTime() {
    if (lastScanTime) {
        lastScanTime.textContent = new Date().toLocaleTimeString('en-US', { 
            hour: 'numeric', 
            minute: '2-digit', 
            hour12: true 
        });
    }
}

// Update success rate
function updateSuccessRate() {
    if (successRate && totalScans > 0) {
        const rate = Math.round((successfulScans / totalScans) * 100);
        successRate.textContent = `${rate}%`;
    }
}

function startScanner() {
    scannerPlaceholder.style.display = 'none';
    scannerContainer.style.display = 'block';
    updateScannerStatus('Starting camera...', 'loading');
    updateGlobalStatus('Starting Camera', 'loading');

    // Check if jsQR is available
    if (typeof jsQR === 'undefined') {
        updateScannerStatus('Error: QR scanner library not loaded', 'error');
        updateGlobalStatus('Library Error', 'error');
        console.error('jsQR library not found');
        return;
    }

    navigator.mediaDevices.getUserMedia({ 
        video: { 
            facingMode: "environment",
            width: { ideal: 1280 },
            height: { ideal: 720 }
        } 
    })
    .then(function (s) {
        stream = s;
        video.srcObject = stream;
        video.play();
        updateScannerStatus('Camera started. Point at QR code...', 'ready');
        updateGlobalStatus('Scanning Active', 'success');
        animationFrameId = requestAnimationFrame(tick);
    })
    .catch(function (err) {
        console.error('Camera error:', err);
        let errorMessage = 'Error: ' + err.message;
        let statusType = 'error';
        
        if (err.name === 'NotAllowedError') {
            errorMessage = 'Error: Camera permission denied. Please allow camera access.';
        } else if (err.name === 'NotFoundError') {
            errorMessage = 'Error: No camera found on this device.';
        }
        
        updateScannerStatus(errorMessage, statusType);
        updateGlobalStatus('Camera Error', 'error');
    });
}

function stopScanner() {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
        stream = null;
    }
    if (animationFrameId) {
        cancelAnimationFrame(animationFrameId);
        animationFrameId = null;
    }
    scannerPlaceholder.style.display = 'block';
    scannerContainer.style.display = 'none';
    updateScannerStatus('Scanner stopped', 'stopped');
    updateGlobalStatus('Scanner Stopped', 'info');
}

// Update scanner status with visual indicators
function updateScannerStatus(message, type = 'info') {
    const statusText = scannerStatus.querySelector('.status-text');
    const statusIndicator = scannerStatus.querySelector('.status-indicator');
    
    if (statusText) {
        statusText.textContent = message;
    }
    
    if (statusIndicator) {
        statusIndicator.className = `fas fa-circle status-indicator status-${type}`;
    }
}

function tick() {
    if (video.readyState === video.HAVE_ENOUGH_DATA) {
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const code = jsQR(imageData.data, imageData.width, imageData.height);

        if (code) {
            updateScannerStatus('QR Code detected!', 'success');
            updateGlobalStatus('Processing QR Code', 'loading');
            processQRCode(code.data);
            stopScanner();
            scanning = false;
            updateScanButton(false);
            return;
        }
    }
    requestAnimationFrame(tick);
}

async function processQRCode(data) {
    try {
        updateScannerStatus('Processing...', 'loading');
        totalScans++;
        
        const formData = new FormData();
        formData.append('qr_data', data);
        formData.append('type', currentType);
        
        const res = await fetch('../qr/scan.php', { method: 'POST', body: formData });
        const json = await res.json();
        
        if (!json.success) {
            let errorMessage = json.message || 'Failed to log attendance';
            
            // Show more specific error messages for type mismatches
            if (json.message && json.message.includes('staff QR code')) {
                errorMessage = '❌ Wrong QR Code Type!\nThis is a staff QR code.\nPlease switch to Staff mode or scan a member QR code.';
            } else if (json.message && json.message.includes('member QR code')) {
                errorMessage = '❌ Wrong QR Code Type!\nThis is a member QR code.\nPlease switch to Member mode or scan a staff QR code.';
            }
            
            updateScannerStatus(errorMessage, 'error');
            updateGlobalStatus('Scan Failed', 'error');
            addToScanHistory({
                success: false,
                message: errorMessage,
                timestamp: new Date().toISOString()
            });
            
            // Send error message to parent window
            if (window.opener && !window.opener.closed) {
                window.opener.postMessage({
                    type: 'scanResult',
                    success: false,
                    message: json.message || 'Failed to log attendance'
                }, '*');
            }
            updateSuccessRate();
            return;
        }
        
        // Successful scan
        successfulScans++;
        updateLastScanTime();
        updateSuccessRate();
        
        // Add to scan history
        addToScanHistory({
            success: true,
            member: json.member,
            action: json.action,
            timestamp: new Date().toISOString()
        });
        
        const successMessage = json.action === 'time_in' ? 'Time in recorded successfully!' : 'Time out recorded successfully!';
        updateScannerStatus(successMessage, 'success');
        updateGlobalStatus('Scan Successful', 'success');
        
        
        // Send message to parent window and refresh attendance
        if (window.opener && !window.opener.closed) {
            window.opener.sessionStorage.setItem('refreshAttendance', 'true');
            window.opener.postMessage({
                type: 'scanResult',
                success: true,
                action: json.action,
                member: json.member
            }, '*');
        }
        
    } catch (e) {
        console.error(e);
        updateScannerStatus('Error while processing QR', 'error');
        updateGlobalStatus('Processing Error', 'error');
        addToScanHistory({
            success: false,
            message: 'Error while processing QR',
            timestamp: new Date().toISOString()
        });
        updateSuccessRate();
    }
}

function addToScanHistory(scanData) {
    scanHistory.unshift(scanData);
    if (scanHistory.length > 10) {
        scanHistory.pop(); // Keep only last 10 scans
    }
    saveScanHistory(); // Save to localStorage
    updateScanHistoryDisplay();
}

// Format timestamp for display (better date format + time without seconds)
function formatScanTimestamp(timestamp) {
    if (!timestamp) return '-';
    try {
        const date = new Date(timestamp);
        const dateStr = date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
        const timeStr = date.toLocaleTimeString('en-US', { 
            hour: 'numeric', 
            minute: '2-digit', 
            hour12: true 
        });
        return `${dateStr}, ${timeStr}`;
    } catch (e) {
        return timestamp;
    }
}

function updateScanHistoryDisplay() {
    // Update scan count badge
    const scanCountBadge = document.getElementById('scan-count');
    if (scanCountBadge) {
        scanCountBadge.textContent = scanHistory.length;
    }
    
    if (scanHistory.length === 0) {
        recentScans.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No recent scans</p>
                <small>Start scanning to see activity here</small>
            </div>
        `;
        return;
    }
    
    let html = '<div class="scans-list">';
    scanHistory.forEach((scan, index) => {
        if (scan.success) {
            html += `
                <div class="scan-item success">
                    <div class="scan-icon">
                        <i class="fas fa-${scan.action === 'time_in' ? 'sign-in-alt' : 'sign-out-alt'}"></i>
                    </div>
                    <div class="scan-details">
                        <div class="scan-name">${scan.member.name}</div>
                        <div class="scan-action">${scan.action === 'time_in' ? 'Time In' : 'Time Out'}</div>
                        <div class="scan-time">${formatScanTimestamp(scan.timestamp)}</div>
                    </div>
                    <div class="scan-badge ${scan.action === 'time_in' ? 'time-in' : 'time-out'}">
                        ${scan.action === 'time_in' ? 'IN' : 'OUT'}
                    </div>
                </div>
            `;
        } else {
            html += `
                <div class="scan-item error">
                    <div class="scan-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="scan-details">
                        <div class="scan-name">Scan Failed</div>
                        <div class="scan-action">${scan.message}</div>
                        <div class="scan-time">${formatScanTimestamp(scan.timestamp)}</div>
                    </div>
                    <div class="scan-badge error">
                        FAIL
                    </div>
                </div>
            `;
        }
    });
    html += '</div>';
    recentScans.innerHTML = html;
}

// Function to close scanner window
function closeScanner() {
    if (window.opener && !window.opener.closed) {
        window.opener.focus();
    }
    window.close();
}

// Function to clear scan history
function clearScanHistory() {
    scanHistory = [];
    const historyKey = `scannerHistory_${currentType}`;
    localStorage.removeItem(historyKey);
    updateScanHistoryDisplay();
}

// Initialize scan history display
updateScanHistoryDisplay();
</script>

<?php include 'components/footer.php'; ?>
