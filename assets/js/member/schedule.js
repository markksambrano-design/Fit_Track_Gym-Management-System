// Schedule Management JavaScript
class ScheduleManager {
    constructor() {
        this.schedules = [];
        this.currentDate = new Date();
        this.init();
    }

    init() {
        this.loadSchedules();
        this.bindEvents();
        this.updateTodaySchedule();
    }

    bindEvents() {
        // Add schedule form submission
        const addScheduleForm = document.getElementById('addScheduleForm');
        if (addScheduleForm) {
            addScheduleForm.addEventListener('submit', (e) => this.handleAddSchedule(e));
        }

        // Schedule item clicks
        document.addEventListener('click', (e) => {
            if (e.target.closest('.schedule-item')) {
                this.handleScheduleItemClick(e.target.closest('.schedule-item'));
            }
        });

        // Category card clicks
        document.addEventListener('click', (e) => {
            if (e.target.closest('.category-card')) {
                this.handleCategoryClick(e.target.closest('.category-card'));
            }
        });

        // Export button
        const exportBtn = document.querySelector('[onclick="exportSchedule()"]');
        if (exportBtn) {
            exportBtn.onclick = () => this.exportSchedule();
        }
    }

    loadSchedules() {
        // Sample schedule data - in real app, this would come from database
        this.schedules = [
            {
                id: 1,
                type: 'cardio',
                name: 'Cardio Training',
                day: 'monday',
                time: '06:00',
                duration: 45,
                notes: 'High intensity cardio session'
            },
            {
                id: 2,
                type: 'strength',
                name: 'Strength Training',
                day: 'tuesday',
                time: '07:00',
                duration: 60,
                notes: 'Upper body focus'
            },
            {
                id: 3,
                type: 'yoga',
                name: 'Yoga',
                day: 'wednesday',
                time: '06:00',
                duration: 60,
                notes: 'Vinyasa flow'
            },
            {
                id: 4,
                type: 'pt',
                name: 'Personal Training',
                day: 'friday',
                time: '06:00',
                duration: 90,
                notes: 'One-on-one session'
            }
        ];

        this.renderSchedule();
    }

    renderSchedule() {
        this.updateWeeklySchedule();
        this.updateUpcomingSessions();
        this.updateStats();
    }

    updateWeeklySchedule() {
        const scheduleTable = document.querySelector('.table tbody');
        if (!scheduleTable) return;

        // Clear existing schedule items
        const scheduleItems = scheduleTable.querySelectorAll('.schedule-item');
        scheduleItems.forEach(item => item.remove());

        // Add schedule items to appropriate cells
        this.schedules.forEach(schedule => {
            const cell = this.getScheduleCell(schedule.day, schedule.time);
            if (cell) {
                const scheduleElement = this.createScheduleElement(schedule);
                cell.appendChild(scheduleElement);
            }
        });
    }

    getScheduleCell(day, time) {
        const dayIndex = this.getDayIndex(day);
        const timeIndex = this.getTimeIndex(time);
        
        if (dayIndex === -1 || timeIndex === -1) return null;

        const table = document.querySelector('.table tbody');
        const row = table.children[timeIndex];
        if (row) {
            return row.children[dayIndex + 1]; // +1 because first column is time
        }
        return null;
    }

    getDayIndex(day) {
        const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        return days.indexOf(day.toLowerCase());
    }

    getTimeIndex(time) {
        const times = ['06:00', '07:00', '08:00'];
        return times.indexOf(time);
    }

    createScheduleElement(schedule) {
        const div = document.createElement('div');
        div.className = `schedule-item ${this.getActivityClass(schedule.type)} text-white p-2 rounded mb-1`;
        div.innerHTML = `
            <small class="d-block fw-bold">${schedule.name}</small>
            <small>${schedule.duration} min</small>
        `;
        div.dataset.scheduleId = schedule.id;
        return div;
    }

    getActivityClass(type) {
        const classes = {
            'cardio': 'bg-primary',
            'strength': 'bg-info',
            'yoga': 'bg-success',
            'pilates': 'bg-secondary',
            'hiit': 'bg-danger',
            'swimming': 'bg-primary',
            'pt': 'bg-warning',
            'rest': 'bg-success'
        };
        return classes[type] || 'bg-primary';
    }

    updateUpcomingSessions() {
        const upcomingContainer = document.querySelector('.upcoming-sessions');
        if (!upcomingContainer) return;

        // Get next 3 upcoming sessions
        const upcoming = this.getUpcomingSessions(3);
        
        upcomingContainer.innerHTML = upcoming.map(session => `
            <div class="session-item d-flex align-items-center p-3 border rounded mb-3">
                <div class="session-time text-center me-3">
                    <div class="fw-bold ${this.getTextColor(session.type)}">${this.formatTime(session.time)}</div>
                    <small class="text-muted">${this.getTimePeriod(session.time)}</small>
                </div>
                <div class="session-details flex-grow-1">
                    <h6 class="mb-1">${session.name}</h6>
                    <p class="text-muted mb-1">${this.formatDate(session.date)}</p>
                    <span class="badge ${this.getBadgeClass(session.type)}">${session.duration} min</span>
                </div>
                <div class="session-actions">
                    <button class="btn btn-sm btn-outline-${this.getButtonColor(session.type)}" title="Edit" onclick="scheduleManager.editSchedule(${session.id})">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
            </div>
        `).join('');
    }

    getUpcomingSessions(count) {
        const today = new Date();
        const upcoming = [];

        // Generate upcoming sessions for the next 7 days
        for (let i = 0; i < 7; i++) {
            const date = new Date(today);
            date.setDate(today.getDate() + i);
            const dayName = this.getDayName(date.getDay());

            this.schedules.forEach(schedule => {
                if (schedule.day.toLowerCase() === dayName.toLowerCase()) {
                    upcoming.push({
                        ...schedule,
                        date: date
                    });
                }
            });
        }

        return upcoming.slice(0, count);
    }

    getDayName(dayIndex) {
        const days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        return days[dayIndex];
    }

    formatTime(time) {
        const [hours, minutes] = time.split(':');
        const hour = parseInt(hours);
        return hour > 12 ? hour - 12 : hour;
    }

    getTimePeriod(time) {
        const [hours] = time.split(':');
        return parseInt(hours) >= 12 ? 'PM' : 'AM';
    }

    formatDate(date) {
        const options = { weekday: 'long', month: 'short', day: 'numeric' };
        return date.toLocaleDateString('en-US', options);
    }

    getTextColor(type) {
        const colors = {
            'cardio': 'text-primary',
            'strength': 'text-info',
            'yoga': 'text-success',
            'pilates': 'text-secondary',
            'hiit': 'text-danger',
            'swimming': 'text-primary',
            'pt': 'text-warning',
            'rest': 'text-success'
        };
        return colors[type] || 'text-primary';
    }

    getBadgeClass(type) {
        const classes = {
            'cardio': 'bg-primary',
            'strength': 'bg-info',
            'yoga': 'bg-success',
            'pilates': 'bg-secondary',
            'hiit': 'bg-danger',
            'swimming': 'bg-primary',
            'pt': 'bg-warning',
            'rest': 'bg-success'
        };
        return classes[type] || 'bg-primary';
    }

    getButtonColor(type) {
        const colors = {
            'cardio': 'primary',
            'strength': 'info',
            'yoga': 'success',
            'pilates': 'secondary',
            'hiit': 'danger',
            'swimming': 'primary',
            'pt': 'warning',
            'rest': 'success'
        };
        return colors[type] || 'primary';
    }

    updateStats() {
        // Update statistics cards
        const totalWorkouts = this.schedules.filter(s => s.type !== 'pt').length;
        const groupClasses = this.schedules.filter(s => ['yoga', 'pilates', 'hiit'].includes(s.type)).length;
        const ptSessions = this.schedules.filter(s => s.type === 'pt').length;

        // Update DOM elements
        const totalElement = document.querySelector('.card h4');
        if (totalElement) totalElement.textContent = totalWorkouts;

        const groupElement = document.querySelectorAll('.card h4')[1];
        if (groupElement) groupElement.textContent = groupClasses;

        const ptElement = document.querySelectorAll('.card h4')[2];
        if (ptElement) ptElement.textContent = ptSessions;
    }

    updateTodaySchedule() {
        const today = new Date();
        const dayName = this.getDayName(today.getDay());
        const todaySchedules = this.schedules.filter(s => 
            s.day.toLowerCase() === dayName.toLowerCase()
        );

        // Update today's schedule on dashboard if it exists
        const todayScheduleContainer = document.querySelector('.today-schedule');
        if (todayScheduleContainer) {
            todayScheduleContainer.innerHTML = todaySchedules.map(schedule => `
                <div class="d-flex align-items-center p-3 border rounded mb-3">
                    <div class="bg-${this.getButtonColor(schedule.type)} bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                        <i class="fas fa-${this.getActivityIcon(schedule.type)} text-${this.getButtonColor(schedule.type)}"></i>
                    </div>
                    <div>
                        <h6 class="mb-1">${schedule.name}</h6>
                        <p class="text-muted mb-0">${schedule.time} - ${schedule.duration} min</p>
                    </div>
                </div>
            `).join('');
        }
    }

    getActivityIcon(type) {
        const icons = {
            'cardio': 'dumbbell',
            'strength': 'dumbbell',
            'yoga': 'users',
            'pilates': 'users',
            'hiit': 'fire',
            'swimming': 'swimming-pool',
            'pt': 'user-tie',
            'rest': 'bed'
        };
        return icons[type] || 'dumbbell';
    }

    handleAddSchedule(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        
        const newSchedule = {
            id: Date.now(),
            type: formData.get('activity'),
            name: this.getActivityName(formData.get('activity')),
            day: formData.get('day'),
            time: formData.get('time'),
            duration: parseInt(formData.get('duration')),
            notes: formData.get('notes') || ''
        };

        this.schedules.push(newSchedule);
        this.renderSchedule();
        
        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('addScheduleModal'));
        if (modal) modal.hide();
        
        // Show success message
        this.showNotification('Schedule added successfully!', 'success');
        
        // Reset form
        e.target.reset();
    }

    getActivityName(type) {
        const names = {
            'cardio': 'Cardio Training',
            'strength': 'Strength Training',
            'yoga': 'Yoga',
            'pilates': 'Pilates',
            'hiit': 'HIIT',
            'swimming': 'Swimming',
            'pt': 'Personal Training',
            'rest': 'Rest Day'
        };
        return names[type] || 'Workout';
    }

    handleScheduleItemClick(element) {
        const scheduleId = element.dataset.scheduleId;
        const schedule = this.schedules.find(s => s.id == scheduleId);
        
        if (schedule) {
            this.showScheduleDetails(schedule);
        }
    }

    handleCategoryClick(element) {
        const category = element.querySelector('h5').textContent.toLowerCase();
        
        if (category.includes('workout')) {
            this.showWorkoutRoutines();
        } else if (category.includes('group')) {
            this.showGroupClasses();
        } else if (category.includes('personal')) {
            this.showPersonalTraining();
        }
    }

    showScheduleDetails(schedule) {
        const modal = new bootstrap.Modal(document.getElementById('scheduleDetailsModal'));
        // Implementation for showing schedule details
        console.log('Show schedule details:', schedule);
    }

    showWorkoutRoutines() {
        // Implementation for showing workout routines
        console.log('Show workout routines');
    }

    showGroupClasses() {
        // Implementation for showing group classes
        console.log('Show group classes');
    }

    showPersonalTraining() {
        // Implementation for showing personal training
        console.log('Show personal training');
    }

    editSchedule(scheduleId) {
        const schedule = this.schedules.find(s => s.id == scheduleId);
        if (schedule) {
            this.populateEditForm(schedule);
            const modal = new bootstrap.Modal(document.getElementById('addScheduleModal'));
            modal.show();
        }
    }

    populateEditForm(schedule) {
        const form = document.getElementById('addScheduleForm');
        if (form) {
            form.querySelector('[name="activity"]').value = schedule.type;
            form.querySelector('[name="day"]').value = schedule.day;
            form.querySelector('[name="time"]').value = schedule.time;
            form.querySelector('[name="duration"]').value = schedule.duration;
            form.querySelector('[name="notes"]').value = schedule.notes;
        }
    }

    exportSchedule() {
        const data = {
            schedules: this.schedules,
            exportDate: new Date().toISOString(),
            member: 'Current Member'
        };

        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `schedule-${new Date().toISOString().split('T')[0]}.json`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        this.showNotification('Schedule exported successfully!', 'success');
    }

    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        document.body.appendChild(notification);

        // Auto remove after 3 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 3000);
    }
}

// Initialize schedule manager when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.scheduleManager = new ScheduleManager();
});

// Global function for export (for onclick handlers)
function exportSchedule() {
    if (window.scheduleManager) {
        window.scheduleManager.exportSchedule();
    }
}


