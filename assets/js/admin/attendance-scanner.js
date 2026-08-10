// QR Scanner Implementation
const scanBtn = document.getElementById('scanBtn');
const scannerContainer = document.getElementById('scanner-container');
const scannerPlaceholder = document.getElementById('scanner-placeholder');
const video = document.getElementById('qr-video');
const scannerStatus = document.getElementById('scanner-status');
const attendanceTableBody = document.querySelector('#attendanceTable tbody');
const refreshBtn = document.getElementById('refreshAttendance');
const saveTodayBtn = document.getElementById('saveToday');
const resetDayBtn = document.getElementById('resetDay');

let scanning = false;
let stream = null;

if (refreshBtn) {
	refreshBtn.addEventListener('click', (e) => {
		e.preventDefault();
		location.reload();
	});
}

if (saveTodayBtn) {
	saveTodayBtn.addEventListener('click', async (e) => {
		e.preventDefault();
		await postAttendanceAction('save_today');
	});
}

if (resetDayBtn) {
	resetDayBtn.addEventListener('click', async (e) => {
		e.preventDefault();
		if (!confirm('This will archive and clear today\'s attendance. Continue?')) return;
		await postAttendanceAction('reset_day');
		location.reload();
	});
}

async function postAttendanceAction(action) {
	try {
		const fd = new FormData();
		fd.append('action', action);
		const res = await fetch('../qr/attendance_actions.php', { method: 'POST', body: fd });
		const json = await res.json();
		alert(json.message || (json.success ? 'Done' : 'Failed'));
		return json;
	} catch (e) {
		console.error(e);
		alert('Action failed.');
	}
}

scanBtn.addEventListener('click', () => {
	if (scanning) {
		stopScanner();
		scanBtn.innerHTML = '<i class="fas fa-qrcode fa-sm text-white-50"></i> Scan QR Code';
	} else {
		startScanner();
		scanBtn.innerHTML = '<i class="fas fa-stop fa-sm text-white-50"></i> Stop Scanning';
	}
	scanning = !scanning;
});

function startScanner() {
	scannerPlaceholder.style.display = 'none';
	scannerContainer.style.display = 'block';

	navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } })
		.then(function (s) {
			stream = s;
			video.srcObject = stream;
			video.play();
			requestAnimationFrame(tick);
		})
		.catch(function (err) {
			scannerStatus.textContent = 'Error: ' + err.message;
			console.error(err);
		});
}

function stopScanner() {
	if (stream) {
		stream.getTracks().forEach(track => track.stop());
	}
	scannerPlaceholder.style.display = 'block';
	scannerContainer.style.display = 'none';
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
			scannerStatus.textContent = 'QR Code detected!';
			processQRCode(code.data);
			stopScanner();
			scanning = false;
			scanBtn.innerHTML = '<i class="fas fa-qrcode fa-sm text-white-50"></i> Scan QR Code';
			return;
		}
	}
	requestAnimationFrame(tick);
}

async function processQRCode(data) {
	try {
		scannerStatus.textContent = 'Processing...';
		const formData = new FormData();
		formData.append('qr_data', data);
		const res = await fetch('../qr/scan.php', { method: 'POST', body: formData });
		const json = await res.json();
		if (!json.success) {
			scannerStatus.textContent = json.message || 'Failed to log attendance';
			alert(json.message || 'Failed to log attendance');
			return;
		}
		updateAttendanceTable(json);
		scannerStatus.textContent = json.action === 'time_in' ? 'Time in recorded' : 'Time out recorded';
	} catch (e) {
		console.error(e);
		scannerStatus.textContent = 'Error while processing QR';
		alert('Error while processing QR');
	}
}

function updateAttendanceTable(payload) {
	const { member, record, action, user_type } = payload;
	// Find existing row for member for today
	let row = Array.from(attendanceTableBody.rows).find(r => r.dataset.memberId === String(member.id));
	
	// Determine user type and styling
	const isStaff = user_type === 'staff';
	const userTypeBadge = isStaff ? 'bg-info' : 'bg-success';
	const userTypeIcon = isStaff ? 'user-tie' : 'user';
	
	if (!row) {
		row = document.createElement('tr');
		row.dataset.memberId = String(member.id);
		row.dataset.userType = user_type;
		row.innerHTML = `
			<td>${member.name}</td>
			<td>
				<span class="badge ${userTypeBadge}">
					<i class="fas fa-${userTypeIcon} me-1"></i>
					${member.type}
				</span>
			</td>
			<td class="time-in">${formatDateTime(record.time_in)}</td>
			<td class="time-out">${record.time_out ? formatDateTime(record.time_out) : '-'}</td>
			<td class="status"><span class="badge ${action === 'time_in' ? 'bg-warning' : 'bg-success'}">${record.status}</span></td>
			<td>
				<button class='btn btn-sm btn-warning btn-edit-attendance' data-attendance-id='${record.id || ''}' title='Edit Attendance'><i class='fas fa-edit'></i></button>
			</td>
		`;
		attendanceTableBody.prepend(row);
	} else {
		// Update existing row
		row.querySelector('.time-in').textContent = formatDateTime(record.time_in);
		row.querySelector('.time-out').textContent = record.time_out ? formatDateTime(record.time_out) : '-';
		row.querySelector('.status').innerHTML = `<span class="badge ${action === 'time_in' ? 'bg-warning' : 'bg-success'}">${record.status}</span>`;
		
		// Update user type if needed
		const typeCell = row.querySelector('td:nth-child(2)');
		if (typeCell) {
			typeCell.innerHTML = `
				<span class="badge ${userTypeBadge}">
					<i class="fas fa-${userTypeIcon} me-1"></i>
					${member.type}
				</span>
			`;
		}
	}
}

function formatDateTime(dt) {
	if (!dt) return '-';
	try {
		const d = new Date(dt.replace(' ', 'T'));
		return d.toLocaleTimeString('en-US', { 
			hour: 'numeric', 
			minute: '2-digit', 
			hour12: true 
		});
	} catch {
		return dt;
	}
}
