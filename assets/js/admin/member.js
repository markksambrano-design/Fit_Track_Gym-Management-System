// Enable Bootstrap Tooltips
const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
tooltipTriggerList.map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

// Delegated handlers for view - handle all tables in the page
const table = document;

const viewModal = new bootstrap.Modal(document.getElementById('viewMemberModal'));
const editModal = new bootstrap.Modal(document.getElementById('editMemberModal'));
const memberDetails = document.getElementById('memberDetails');

function rowFor(memberId) {
	return document.querySelector(`tr[data-member-id="${memberId}"]`);
}

async function fetchMember(memberId) {
	const res = await fetch(`actions/members_actions.php?action=view&member_id=${encodeURIComponent(memberId)}`);
	return res.json();
}

// View
table.addEventListener('click', async (e) => {
	const btn = e.target.closest('.btn-view');
	if (!btn) return;
	const memberId = btn.dataset.memberId;
	
	// Show modal immediately with loading state
	document.getElementById('memberDetails').innerHTML = `
		<div class="text-center py-5">
			<div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
				<span class="visually-hidden">Loading...</span>
			</div>
			<p class="mt-3 text-muted">Loading member details...</p>
		</div>
	`;
	
	// Show modal immediately for better UX
	viewModal.show();
	
	try {
		const json = await fetchMember(memberId);
		if (!json.success) { 
			document.getElementById('memberDetails').innerHTML = `
				<div class="alert alert-danger">
					<i class="fas fa-exclamation-triangle me-2"></i>
					${json.message || 'Failed to load member data'}
				</div>
			`;
			return; 
		}
	
		const m = json.member;
		currentMemberData = m; // Store current member data for edit functionality
		const isExpired = m.expired_date && new Date(m.expired_date) < new Date();
		
		// Calculate membership duration text
		let durationText = '';
		if (m.membership_type === 'regular' && m.membership_duration) {
			durationText = m.membership_duration == 12 ? '1 Year' : `${m.membership_duration} Month${m.membership_duration > 1 ? 's' : ''}`;
		} else if (m.membership_type === 'session') {
			durationText = '1 Day';
		}
		
		memberDetails.innerHTML = `
		<div class="profile-header">
			<div class="profile-avatar">
				${m.photo ? `<img src="../../uploads/member_photos/${m.photo}" alt="Profile Picture" class="profile-picture" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">` : '<i class="fas fa-user"></i>'}
				${m.photo ? '<i class="fas fa-user" style="display:none;"></i>' : ''}
			</div>
			<div class="profile-info">
				<h4>${m.first_name} ${m.last_name}</h4>
				<p class="member-id">Member #${m.member_id}</p>
				<div class="status-badge ${isExpired ? 'expired' : 'active'}">
					<i class="fas fa-circle"></i>
					${isExpired ? 'Expired' : 'Active'}
				</div>
			</div>
		</div>
		
		<div class="info-grid">
			<div class="info-card">
				<div class="card-header">
					<i class="fas fa-user"></i>
					<span>Personal Information</span>
				</div>
				<div class="card-content">
					<div class="info-item">
						<label>Email Address</label>
						<span>${m.email}</span>
					</div>
					<div class="info-item">
						<label>Phone Number</label>
						<span>${m.phone || 'Not provided'}</span>
					</div>
					<div class="info-item">
						<label>Gender</label>
						<span>${m.gender || 'Not specified'}</span>
					</div>
					<div class="info-item">
						<label>Age</label>
						<span>${m.age ? m.age + ' years old' : 'Not specified'}</span>
					</div>
				</div>
			</div>
			
			<div class="info-card">
				<div class="card-header">
					<i class="fas fa-id-card"></i>
					<span>Membership Details</span>
				</div>
				<div class="card-content">
					<div class="info-item">
						<label>Membership Type</label>
						<span class="membership-type">${m.membership_type.charAt(0).toUpperCase() + m.membership_type.slice(1)}</span>
					</div>
					<div class="info-item">
						<label>Duration</label>
						<span>${m.membership_duration ? m.membership_duration + ' Month(s)' : 'Not specified'}</span>
					</div>
					<div class="info-item">
						<label>Join Date</label>
						<span>${new Date(m.join_date).toLocaleDateString()}</span>
					</div>
					<div class="info-item">
						<label>Expiry Date</label>
						<span class="${isExpired ? 'expired' : ''}">${new Date(m.expired_date).toLocaleDateString()}</span>
					</div>
				</div>
			</div>
			
			<div class="info-card full-width">
				<div class="card-header">
					<i class="fas fa-map-marker-alt"></i>
					<span>Address</span>
				</div>
				<div class="card-content">
					<div class="address-content">
						${m.address || 'No address provided'}
					</div>
				</div>
			</div>
		</div>
	`;
	} catch (error) {
		console.error('Error loading member details:', error);
		document.getElementById('memberDetails').innerHTML = `
			<div class="alert alert-danger">
				<i class="fas fa-exclamation-triangle me-2"></i>
				An error occurred while loading member details. Please try again.
			</div>
		`;
	}
});

// Edit Member functionality
let currentMemberData = null;

// Edit button click handler
document.getElementById('editMemberBtn').addEventListener('click', function() {
	if (!currentMemberData) return;
	
	// Populate edit form with current member data
	document.getElementById('edit_member_id').value = currentMemberData.member_id;
	document.getElementById('edit_first_name').value = currentMemberData.first_name;
	document.getElementById('edit_last_name').value = currentMemberData.last_name;
	document.getElementById('edit_email').value = currentMemberData.email;
	document.getElementById('edit_phone').value = currentMemberData.phone || '';
	document.getElementById('edit_gender').value = currentMemberData.gender || '';
	document.getElementById('edit_membership_type').value = currentMemberData.membership_type;
	document.getElementById('edit_join_date').value = currentMemberData.join_date;
	document.getElementById('edit_address').value = currentMemberData.address || '';
	
	// Show edit modal
	editModal.show();
});

// Save member changes
document.getElementById('saveMemberBtn').addEventListener('click', async function() {
	const form = document.getElementById('editMemberForm');
	const formData = new FormData(form);
	formData.append('action', 'update');
	
	const btn = this;
	const originalBtnText = btn.innerHTML;
	
	// Show loading state
	btn.innerHTML = `
		<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...
	`;
	btn.disabled = true;
	
	try {
		const response = await fetch('actions/members_actions.php', {
			method: 'POST',
			body: formData
		});
		
		const data = await response.json();
		
		if (data.success) {
			// Close edit modal
			editModal.hide();
			
			// Update current member data
			currentMemberData = data.member;
			
			// Refresh the view modal with updated data
			await refreshMemberView(currentMemberData.member_id);
			
			// Show success message
			alert('Member profile updated successfully!');
			
			// Reload page to update the table
			window.location.reload();
		} else {
			throw new Error(data.message || 'Update failed');
		}
	} catch (error) {
		console.error('Error:', error);
		alert(error.message || 'An error occurred while updating member profile');
	} finally {
		btn.innerHTML = originalBtnText;
		btn.disabled = false;
	}
});

// Function to refresh member view after edit
async function refreshMemberView(memberId) {
	try {
		const json = await fetchMember(memberId);
		if (json.success) {
			currentMemberData = json.member;
			// Re-populate the view modal with updated data
			const m = json.member;
			const isExpired = m.expired_date && new Date(m.expired_date) < new Date();
			
			// Calculate membership duration text
			let durationText = '';
			if (m.membership_type === 'regular' && m.membership_duration) {
				durationText = m.membership_duration == 12 ? '1 Year' : `${m.membership_duration} Month${m.membership_duration > 1 ? 's' : ''}`;
			} else if (m.membership_type === 'session') {
				durationText = '1 Day';
			}
			
			memberDetails.innerHTML = `
			<div class="profile-header">
				<div class="profile-avatar">
					${m.photo ? `<img src="../../uploads/member_photos/${m.photo}" alt="Profile Picture" class="profile-picture" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">` : '<i class="fas fa-user"></i>'}
					${m.photo ? '<i class="fas fa-user" style="display:none;"></i>' : ''}
				</div>
				<div class="profile-info">
					<h4>${m.first_name} ${m.last_name}</h4>
					<p class="member-id">Member #${m.member_id}</p>
					<div class="status-badge ${isExpired ? 'expired' : 'active'}">
						<i class="fas fa-circle"></i>
						${isExpired ? 'Expired' : 'Active'}
					</div>
				</div>
			</div>
			
			<div class="info-grid">
				<div class="info-card">
					<div class="card-header">
						<i class="fas fa-user"></i>
						<span>Personal Information</span>
					</div>
					<div class="card-content">
						<div class="info-item">
							<label>Email Address</label>
							<span>${m.email}</span>
						</div>
						<div class="info-item">
							<label>Phone Number</label>
							<span>${m.phone || 'Not provided'}</span>
						</div>
						<div class="info-item">
							<label>Gender</label>
							<span>${m.gender || 'Not specified'}</span>
						</div>
					</div>
				</div>
				
				<div class="info-card">
					<div class="card-header">
						<i class="fas fa-id-card"></i>
						<span>Membership Details</span>
					</div>
					<div class="card-content">
						<div class="info-item">
							<label>Membership Type</label>
							<span class="membership-type">${m.membership_type.charAt(0).toUpperCase() + m.membership_type.slice(1)}</span>
						</div>
						<div class="info-item">
							<label>Duration</label>
							<span>${m.membership_duration ? m.membership_duration + ' Month(s)' : 'Not specified'}</span>
						</div>
						<div class="info-item">
							<label>Join Date</label>
							<span>${new Date(m.join_date).toLocaleDateString()}</span>
						</div>
						<div class="info-item">
							<label>Expiry Date</label>
							<span class="${isExpired ? 'expired' : ''}">${new Date(m.expired_date).toLocaleDateString()}</span>
						</div>
					</div>
				</div>
				
				<div class="info-card full-width">
					<div class="card-header">
						<i class="fas fa-map-marker-alt"></i>
						<span>Address</span>
					</div>
					<div class="card-content">
						<div class="address-content">
							${m.address || 'No address provided'}
						</div>
					</div>
				</div>
			</div>
		`;
		}
	} catch (error) {
		console.error('Error refreshing member view:', error);
	}
}
