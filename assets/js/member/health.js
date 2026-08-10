/**
 * Health Tracker JavaScript
 * Enhanced functionality for the health tracking page
 */

document.addEventListener('DOMContentLoaded', function() {
    initializeHealthTracker();
});

function initializeHealthTracker() {
    // Initialize real-time BMI calculation
    initializeBMICalculator();
    
    // Initialize form validation
    initializeFormValidation();
    
    // Initialize progress animations
    initializeProgressAnimations();
    
    // Initialize tooltips and help text
    initializeTooltips();
    
    // Initialize auto-save functionality
    initializeAutoSave();
    
    // Initialize responsive charts
    initializeResponsiveCharts();
}

/**
 * Real-time BMI Calculator
 */
function initializeBMICalculator() {
    const heightInput = document.getElementById('height');
    const weightInput = document.getElementById('weight');
    const bmiDisplay = document.getElementById('bmi-preview');
    
    if (heightInput && weightInput) {
        // Create BMI preview element if it doesn't exist
        if (!bmiDisplay) {
            const bmiPreview = document.createElement('div');
            bmiPreview.id = 'bmi-preview';
            bmiPreview.className = 'bmi-preview';
            bmiPreview.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: rgba(59, 130, 246, 0.9);
                color: white;
                padding: 15px 20px;
                border-radius: 12px;
                font-weight: 600;
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255, 255, 255, 0.2);
                z-index: 1000;
                opacity: 0;
                transform: translateY(-20px);
                transition: all 0.3s ease;
                max-width: 250px;
            `;
            document.body.appendChild(bmiPreview);
        }
        
        function calculateBMI() {
            const height = parseFloat(heightInput.value);
            const weight = parseFloat(weightInput.value);
            
            if (height > 0 && weight > 0) {
                const heightInMeters = height / 100;
                const bmi = weight / (heightInMeters * heightInMeters);
                const category = getBMICategory(bmi);
                
                const preview = document.getElementById('bmi-preview');
                preview.innerHTML = `
                    <div style="font-size: 1.2rem; margin-bottom: 5px;">BMI: ${bmi.toFixed(1)}</div>
                    <div style="font-size: 0.9rem; opacity: 0.9;">${category.name}</div>
                `;
                preview.style.background = category.color;
                preview.style.opacity = '1';
                preview.style.transform = 'translateY(0)';
                
                // Auto-hide after 5 seconds
                setTimeout(() => {
                    preview.style.opacity = '0';
                    preview.style.transform = 'translateY(-20px)';
                }, 5000);
            }
        }
        
        heightInput.addEventListener('input', calculateBMI);
        weightInput.addEventListener('input', calculateBMI);
    }
}

/**
 * Get BMI Category with color
 */
function getBMICategory(bmi) {
    if (bmi < 18.5) {
        return { name: 'Underweight', color: 'rgba(59, 130, 246, 0.9)' };
    } else if (bmi < 24.9) {
        return { name: 'Normal Weight', color: 'rgba(16, 185, 129, 0.9)' };
    } else if (bmi < 29.9) {
        return { name: 'Overweight', color: 'rgba(245, 158, 11, 0.9)' };
    } else {
        return { name: 'Obese', color: 'rgba(239, 68, 68, 0.9)' };
    }
}

/**
 * Form Validation
 */
function initializeFormValidation() {
    const form = document.querySelector('.health-form');
    if (!form) return;
    
    const inputs = form.querySelectorAll('input[required], textarea[required]');
    
    inputs.forEach(input => {
        input.addEventListener('blur', validateInput);
        input.addEventListener('input', clearValidationError);
    });
    
    form.addEventListener('submit', validateForm);
}

function validateInput(event) {
    const input = event.target;
    const value = input.value.trim();
    
    // Remove existing error styling
    clearValidationError(event);
    
    // Validate based on input type
    switch (input.name) {
        case 'height':
            if (value && (parseFloat(value) < 100 || parseFloat(value) > 250)) {
                showValidationError(input, 'Height must be between 100-250 cm');
            }
            break;
        case 'weight':
            if (value && (parseFloat(value) < 20 || parseFloat(value) > 300)) {
                showValidationError(input, 'Weight must be between 20-300 kg');
            }
            break;
        case 'body_fat':
            if (value && (parseFloat(value) < 1 || parseFloat(value) > 50)) {
                showValidationError(input, 'Body fat must be between 1-50%');
            }
            break;
        case 'muscle_mass':
            if (value && (parseFloat(value) < 1 || parseFloat(value) > 100)) {
                showValidationError(input, 'Muscle mass must be between 1-100 kg');
            }
            break;
    }
}

function showValidationError(input, message) {
    input.style.borderColor = '#ef4444';
    input.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.1)';
    
    // Create or update error message
    let errorElement = input.parentNode.querySelector('.validation-error');
    if (!errorElement) {
        errorElement = document.createElement('div');
        errorElement.className = 'validation-error';
        errorElement.style.cssText = `
            color: #fca5a5;
            font-size: 0.8rem;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        `;
        input.parentNode.appendChild(errorElement);
    }
    
    errorElement.innerHTML = `<i class="fas fa-exclamation-triangle"></i>${message}`;
}

function clearValidationError(event) {
    const input = event.target;
    input.style.borderColor = '';
    input.style.boxShadow = '';
    
    const errorElement = input.parentNode.querySelector('.validation-error');
    if (errorElement) {
        errorElement.remove();
    }
}

function validateForm(event) {
    const form = event.target;
    const inputs = form.querySelectorAll('input[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            showValidationError(input, 'This field is required');
            isValid = false;
        } else {
            validateInput({ target: input });
        }
    });
    
    if (!isValid) {
        event.preventDefault();
        
        // Scroll to first error
        const firstError = form.querySelector('.validation-error');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
}

/**
 * Progress Animations
 */
function initializeProgressAnimations() {
    // Animate progress values
    const progressValues = document.querySelectorAll('.progress-value');
    
    progressValues.forEach(value => {
        const text = value.textContent;
        const numericValue = parseFloat(text.replace(/[^\d.-]/g, ''));
        
        if (!isNaN(numericValue)) {
            animateNumber(value, 0, numericValue, 1000);
        }
    });
    
    // Animate metric values
    const metricValues = document.querySelectorAll('.metric-value, .activity-value');
    
    metricValues.forEach(value => {
        const text = value.textContent;
        const numericValue = parseFloat(text.replace(/[^\d.-]/g, ''));
        
        if (!isNaN(numericValue) && numericValue > 0) {
            animateNumber(value, 0, numericValue, 1500);
        }
    });
}

function animateNumber(element, start, end, duration) {
    const startTime = performance.now();
    const suffix = element.textContent.replace(/[\d.-]/g, '');
    
    function updateNumber(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        // Easing function
        const easeOutQuart = 1 - Math.pow(1 - progress, 4);
        const current = start + (end - start) * easeOutQuart;
        
        element.textContent = current.toFixed(element.textContent.includes('.') ? 2 : 0) + suffix;
        
        if (progress < 1) {
            requestAnimationFrame(updateNumber);
        }
    }
    
    requestAnimationFrame(updateNumber);
}

/**
 * Tooltips and Help Text
 */
function initializeTooltips() {
    // Add help tooltips to form inputs
    const helpTexts = {
        'height': 'Enter your height in centimeters (100-250 cm)',
        'weight': 'Enter your current weight in kilograms (20-300 kg)',
        'body_fat': 'Optional: Your body fat percentage (1-50%)',
        'muscle_mass': 'Optional: Your muscle mass in kilograms (1-100 kg)',
        'notes': 'Optional: Add any notes about your health status or goals'
    };
    
    Object.keys(helpTexts).forEach(inputName => {
        const input = document.querySelector(`[name="${inputName}"]`);
        if (input) {
            addTooltip(input, helpTexts[inputName]);
        }
    });
}

function addTooltip(element, text) {
    const tooltip = document.createElement('div');
    tooltip.className = 'health-tooltip';
    tooltip.textContent = text;
    tooltip.style.cssText = `
        position: absolute;
        background: rgba(0, 0, 0, 0.9);
        color: white;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 0.8rem;
        z-index: 1000;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s ease;
        max-width: 200px;
        word-wrap: break-word;
    `;
    
    element.parentNode.style.position = 'relative';
    element.parentNode.appendChild(tooltip);
    
    element.addEventListener('mouseenter', () => {
        tooltip.style.opacity = '1';
    });
    
    element.addEventListener('mouseleave', () => {
        tooltip.style.opacity = '0';
    });
    
    element.addEventListener('focus', () => {
        tooltip.style.opacity = '1';
    });
    
    element.addEventListener('blur', () => {
        tooltip.style.opacity = '0';
    });
}

/**
 * Auto-save functionality
 */
function initializeAutoSave() {
    const form = document.querySelector('.health-form');
    if (!form) return;
    
    const inputs = form.querySelectorAll('input, textarea');
    let autoSaveTimeout;
    
    inputs.forEach(input => {
        input.addEventListener('input', () => {
            clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(() => {
                saveFormData();
            }, 2000); // Auto-save after 2 seconds of inactivity
        });
    });
    
    // Save form data to localStorage
    function saveFormData() {
        const formData = new FormData(form);
        const data = {};
        
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }
        
        localStorage.setItem('healthFormData', JSON.stringify(data));
        showAutoSaveIndicator();
    }
    
    // Load saved data on page load
    const savedData = localStorage.getItem('healthFormData');
    if (savedData) {
        try {
            const data = JSON.parse(savedData);
            Object.keys(data).forEach(key => {
                const input = form.querySelector(`[name="${key}"]`);
                if (input && !input.value) {
                    input.value = data[key];
                }
            });
        } catch (e) {
            console.error('Error loading saved form data:', e);
        }
    }
    
    // Clear saved data on successful form submission
    form.addEventListener('submit', () => {
        localStorage.removeItem('healthFormData');
    });
}

function showAutoSaveIndicator() {
    let indicator = document.getElementById('auto-save-indicator');
    if (!indicator) {
        indicator = document.createElement('div');
        indicator.id = 'auto-save-indicator';
        indicator.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(16, 185, 129, 0.9);
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 0.9rem;
            z-index: 1000;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.3s ease;
        `;
        document.body.appendChild(indicator);
    }
    
    indicator.innerHTML = '<i class="fas fa-save"></i> Auto-saved';
    indicator.style.opacity = '1';
    indicator.style.transform = 'translateY(0)';
    
    setTimeout(() => {
        indicator.style.opacity = '0';
        indicator.style.transform = 'translateY(20px)';
    }, 2000);
}

/**
 * Responsive Charts
 */
function initializeResponsiveCharts() {
    // Handle chart resize on window resize
    window.addEventListener('resize', debounce(() => {
        const charts = Chart.instances;
        Object.keys(charts).forEach(key => {
            charts[key].resize();
        });
    }, 250));
}

// Utility function for debouncing
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Export data functionality
 */
function exportHealthData() {
    const table = document.querySelector('.history-table');
    if (!table) return;
    
    const rows = table.querySelectorAll('.table-row');
    const data = [];
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('div');
        data.push({
            date: cells[0].textContent,
            bmi: cells[1].textContent,
            weight: cells[2].textContent,
            height: cells[3].textContent,
            category: cells[4].textContent.trim()
        });
    });
    
    const csv = convertToCSV(data);
    downloadCSV(csv, 'health-data.csv');
}

function convertToCSV(data) {
    const headers = ['Date', 'BMI', 'Weight', 'Height', 'Category'];
    const csvContent = [
        headers.join(','),
        ...data.map(row => [
            row.date,
            row.bmi,
            row.weight,
            row.height,
            `"${row.category}"`
        ].join(','))
    ].join('\n');
    
    return csvContent;
}

function downloadCSV(csv, filename) {
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// Add export button if history table exists
document.addEventListener('DOMContentLoaded', function() {
    const historySection = document.querySelector('.health-history-section');
    if (historySection) {
        const exportBtn = document.createElement('button');
        exportBtn.textContent = 'Export Data';
        exportBtn.className = 'btn btn-outline-primary';
        exportBtn.style.cssText = `
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid #3b82f6;
            color: #3b82f6;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
        `;
        
        exportBtn.addEventListener('click', exportHealthData);
        exportBtn.addEventListener('mouseenter', () => {
            exportBtn.style.background = '#3b82f6';
            exportBtn.style.color = 'white';
        });
        exportBtn.addEventListener('mouseleave', () => {
            exportBtn.style.background = 'rgba(59, 130, 246, 0.1)';
            exportBtn.style.color = '#3b82f6';
        });
        
        historySection.style.position = 'relative';
        historySection.appendChild(exportBtn);
    }
});


