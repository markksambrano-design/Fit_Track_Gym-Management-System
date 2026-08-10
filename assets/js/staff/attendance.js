// Staff Attendance Management System
// Handles QR scanning, real-time updates, and attendance management

let isInitialized = false;

// Initialize the page
document.addEventListener('DOMContentLoaded', function() {
    if (isInitialized) return;
    isInitialized = true;
    
    console.log('Staff Attendance Page initialized');
    
    // Initialize QR Scanner
    initQRScanner();
    
    // Initialize real-time updates
    initRealTimeUpdates();
    
    // Show real-time status
    showRealTimeStatus();
    
    // Auto-refresh every 30 seconds as fallback
    setInterval(refreshAttendance, 30000);
});

// Initialize QR Scanner
function initQRScanner() {
    const startScanBtn = document.getElementById('startScanBtn');
    const scannerContainer = document.getElementById('scanner-container');
    const scannerPlaceholder = document.getElementById('scanner-placeholder');
    const video = document.getElementById('qr-video');
    const scannerStatus = document.getElementById('scanner-status');
    
    let scanning = false;
    let stream = null;
    
    if (startScanBtn) {
        startScanBtn.addEventListener('click', startScanner);
    }
    
    // Handle modal close
    const modal = document.getElementById('qrScannerModal');
    if (modal) {
        modal.addEventListener('hidden.bs.modal', function() {
            stopScanner();
        });
    }
    
    function startScanner() {
        if (scanning) return;
        
        // Request camera access
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
            .then(function(mediaStream) {
                stream = mediaStream;
                video.srcObject = mediaStream;
                video.play();
                
                scanning = true;
                scannerContainer.style.display = 'block';
                scannerPlaceholder.style.display = 'none';
                startScanBtn.textContent = 'Stop Scanning';
                startScanBtn.className = 'btn btn-danger';
                startScanBtn.onclick = stopScanner;
                
                scannerStatus.textContent = 'Camera active - Point at QR code';
                scannerStatus.className = 'mt-3 alert alert-info';
                
                // Start scanning loop
                requestAnimationFrame(tick);
            })
            .catch(function(error) {
                console.error('Camera access error:', error);
                scannerStatus.textContent = 'Camera access denied. Please allow camera permissions.';
                scannerStatus.className = 'mt-3 alert alert-danger';
            });
    }
    
    function stopScanner() {
        if (!scanning) return;
        
        scanning = false;
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }
        
        scannerContainer.style.display = 'none';
        scannerPlaceholder.style.display = 'block';
        startScanBtn.textContent = 'Start Scanning';
        startScanBtn.className = 'btn btn-success';
        startScanBtn.onclick = startScanner;
        
        scannerStatus.textContent = 'Scanner stopped';
        scannerStatus.className = 'mt-3 alert alert-secondary';
    }
    
    function tick() {
        if (!scanning) return;
        
        if (video.readyState === video.HAVE_ENOUGH_DATA) {
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(imageData.data, imageData.width, imageData.height);
            
            if (code) {
                console.log('QR Code detected:', code.data);
                processQRCode(code.data);
                return;
            }
        }
        
        requestAnimationFrame(tick);
    }
}

// Process scanned QR code
async function processQRCode(data) {
    try {
        const scannerStatus = document.getElementById('scanner-status');
        scannerStatus.textContent = 'Processing...';
        scannerStatus.className = 'mt-3 alert alert-warning';
        
        const formData = new FormData();
        formData.append('qr_data', data);
        
        const response = await fetch('../qr/scan.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (!result.success) {
            showError(result.message || 'Failed to process QR code');
            scannerStatus.textContent = 'Scan failed - ' + (result.message || 'Unknown error');
            scannerStatus.className = 'mt-3 alert alert-danger';
            return;
        }
        
        // Update attendance table with new data
        updateAttendanceTableFromQR(result);
        
        // Update stats
        updateStatsFromAction(result.action);
        
        // Show success message
        const actionText = result.action === 'time_in' ? 'checked in' : 'checked out';
        showSuccess(`Member ${actionText} successfully!`);
        
        // Update scanner status
        scannerStatus.textContent = result.action === 'time_in' ? 'Time in recorded' : 'Time out recorded';
        scannerStatus.className = 'mt-3 alert alert-success';
        
        // Close modal after 2 seconds
        setTimeout(() => {
            const modal = bootstrap.Modal.getInstance(document.getElementById('qrScannerModal'));
            if (modal) modal.hide();
        }, 2000);
        
    } catch (error) {
        console.error('Error processing QR code:', error);
        showError('Failed to process QR code. Please try again.');
        const scannerStatus = document.getElementById('scanner-status');
        scannerStatus.textContent = 'Processing failed';
        scannerStatus.className = 'mt-3 alert alert-danger';
    }
}

// Initialize real-time updates
function initRealTimeUpdates() {
    // Show live indicator
    const liveIndicator = document.getElementById('live-indicator');
    if (liveIndicator) {
        liveIndicator.style.display = 'inline-block';
    }
    
    // Check for new attendance every 10 seconds
    setInterval(checkForNewAttendance, 10000);
    
    console.log('Real-time updates active');
}

// Check for new attendance data
async function checkForNewAttendance() {
    try {
        const response = await fetch(`../qr/check_database.php?date=${new Date().toISOString().split('T')[0]}`);
        const result = await response.json();
        
        if (result.success) {
            updateAttendanceTableFromDatabase(result.attendance);
            updateStats(result.stats);
        }
    } catch (error) {
        console.error('Error checking for new attendance:', error);
    }
}

// Update attendance table from QR scan
function updateAttendanceTableFromQR(payload) {
    const tableBody = document.getElementById('attendanceTableBody');
    if (!tableBody) return;
    
    // Check if member already exists in table
    const existingRow = tableBody.querySelector(`tr[data-member-id="${payload.member_id}"]`);
    
    if (existingRow) {
        // Update existing row
        updateExistingRow(existingRow, payload);
    } else {
        // Add new row
        addNewRow(payload);
    }
    
    console.log('Attendance table updated from QR scan');
}

// Update existing row
function updateExistingRow(row, payload) {
    const timeOutCell = row.querySelector('.time-out');
    const durationCell = row.querySelector('.duration');
    const statusCell = row.querySelector('.status');
    
    if (payload.action === 'time_out') {
        // Update time out
        timeOutCell.innerHTML = `<span class="text-info">${formatTime(payload.time_out)}</span>`;
        
        // Calculate and update duration
        const timeIn = new Date(payload.time_in);
        const timeOut = new Date(payload.time_out);
        const duration = timeOut - timeIn;
        const hours = Math.floor(duration / (1000 * 60 * 60));
        const minutes = Math.floor((duration % (1000 * 60 * 60)) / (1000 * 60));
        durationCell.innerHTML = `<span class="text-success">${hours}h ${minutes}m</span>`;
        
        // Update status
        statusCell.innerHTML = `<span class="badge bg-success"><i class="fas fa-check me-1"></i>Completed</span>`;
        
        // Remove today's highlight
        row.classList.remove('table-warning');
    }
}

// Add new row
function addNewRow(payload) {
    const tableBody = document.getElementById('attendanceTableBody');
    if (!tableBody) return;
    
    const newRow = document.createElement('tr');
    newRow.className = 'table-warning'; // Highlight as today's record
    newRow.setAttribute('data-member-id', payload.member_id);
    
    const isToday = new Date(payload.date).toDateString() === new Date().toDateString();
    
    newRow.innerHTML = `
        <td>
            <div class="d-flex align-items-center">
                <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-2" 
                     style="width: 40px; height: 40px;">
                    <i class="fas fa-user"></i>
                </div>
                <div>
                    <strong>${payload.first_name} ${payload.last_name}</strong>
                    <br>
                    <small class="text-muted">${payload.membership_type || 'Standard'}</small>
                </div>
            </div>
        </td>
        <td>
            <span class="badge bg-secondary">${payload.member_code}</span>
        </td>
        <td>
            <strong>${formatDate(payload.date)}</strong>
            ${isToday ? '<span class="badge bg-warning ms-1">Today</span>' : ''}
        </td>
        <td class="time-in">
            <span class="text-primary">${formatTime(payload.time_in)}</span>
        </td>
        <td class="time-out">
            <span class="text-info">-</span>
        </td>
        <td class="duration">
            <span class="text-success">-</span>
        </td>
        <td>
            <span class="badge bg-warning">
                <i class="fas fa-clock me-1"></i>Active
            </span>
        </td>
        <td>
            <div class="btn-group btn-group-sm" role="group">
                <button class="btn btn-outline-primary" onclick="viewDetails(${payload.attendance_id})">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="btn btn-outline-success" onclick="checkOut(${payload.attendance_id})">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </div>
        </td>
    `;
    
    // Add row with animation
    newRow.style.opacity = '0';
    newRow.style.transform = 'translateY(-20px)';
    tableBody.insertBefore(newRow, tableBody.firstChild);
    
    // Animate in
    setTimeout(() => {
        newRow.style.transition = 'all 0.3s ease';
        newRow.style.opacity = '1';
        newRow.style.transform = 'translateY(0)';
    }, 10);
}

// Update attendance table from database
function updateAttendanceTableFromDatabase(attendanceData) {
    // This function can be used to update the entire table
    // when polling for new data
    console.log('Attendance table updated from database');
}

// Update stats from action
function updateStatsFromAction(action) {
    const totalElement = document.getElementById('stats-total');
    const activeElement = document.getElementById('stats-active');
    const completedElement = document.getElementById('stats-completed');
    
    if (action === 'time_in') {
        if (totalElement) totalElement.textContent = parseInt(totalElement.textContent || 0) + 1;
        if (activeElement) activeElement.textContent = parseInt(activeElement.textContent || 0) + 1;
    } else if (action === 'time_out') {
        if (activeElement) activeElement.textContent = parseInt(activeElement.textContent || 0) - 1;
        if (completedElement) completedElement.textContent = parseInt(completedElement.textContent || 0) + 1;
    }
}

// Update stats display
function updateStats(stats) {
    const totalElement = document.getElementById('stats-total');
    const activeElement = document.getElementById('stats-active');
    const completedElement = document.getElementById('stats-completed');
    
    if (totalElement) totalElement.textContent = stats.total || 0;
    if (activeElement) activeElement.textContent = stats.active || 0;
    if (completedElement) completedElement.textContent = stats.completed || 0;
}

// Show real-time status
function showRealTimeStatus() {
    const statusElement = document.getElementById('realtime-status');
    if (statusElement) {
        statusElement.style.display = 'block';
    }
}

// Refresh attendance data
function refreshAttendance() {
    location.reload();
}

// Complete today's session
function completeTodaySession() {
    // This would typically call an API to complete the session
    alert('Session completion functionality would be implemented here');
}

// View attendance details
function viewDetails(attendanceId) {
    alert(`View details for attendance ID: ${attendanceId}`);
}

// Check out member
function checkOut(attendanceId) {
    if (confirm('Check out this member?')) {
        // This would typically call an API to check out the member
        alert(`Check out functionality for attendance ID: ${attendanceId} would be implemented here`);
    }
}

// Export attendance data
function exportAttendance() {
    alert('Export functionality would be implemented here');
}

// Print attendance data
function printAttendance() {
    window.print();
}

// Show success message
function showSuccess(message) {
    // Create success alert
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-success alert-dismissible fade show position-fixed';
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        <i class="fas fa-check-circle me-2"></i>${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    // Auto-remove after 3 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 3000);
}

// Show error message
function showError(message) {
    // Create error alert
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-danger alert-dismissible fade show position-fixed';
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        <i class="fas fa-exclamation-circle me-2"></i>${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

// Utility functions
function formatTime(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        hour12: true 
    });
}

function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric' 
    });
}

// Export functions for external use
window.StaffAttendance = {
    updateAttendanceTableFromQR: updateAttendanceTableFromQR
};
