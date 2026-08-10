document.addEventListener('DOMContentLoaded', function() {
    // Attendance Chart
    const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
    new Chart(attendanceCtx, {
        type: 'line',
        data: {
            labels: weeklyData.map(item => item.day),
            datasets: [{
                label: 'Members',
                data: weeklyData.map(item => item.count),
                borderColor: '#4e73df',
                backgroundColor: 'rgba(78, 115, 223, 0.1)',
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: Math.max(...weeklyData.map(item => item.count)) + 10
                }
            }
        }
    });

    // Membership Chart
    const membershipCtx = document.getElementById('membershipChart').getContext('2d');
    new Chart(membershipCtx, {
        type: 'doughnut',
        data: {
            labels: Object.keys(membershipData),
            datasets: [{
                data: Object.values(membershipData),
                backgroundColor: ['#4e73df', '#1cc88a'],
                hoverBorderColor: '#fff'
            }]
        },
        options: {
            maintainAspectRatio: false,
            cutout: '75%',
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
});