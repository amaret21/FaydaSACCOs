// DOM Elements
const form = document.getElementById('loanCalculator');
const loanAmountInput = document.getElementById('loanAmount');
const loanAmountRange = document.getElementById('loanAmountRange');
const interestRateInput = document.getElementById('interestRate');
const interestRateRange = document.getElementById('interestRateRange');
const loanYearsInput = document.getElementById('loanYears');
const loanMonthsInput = document.getElementById('loanMonths');
const loanTermRange = document.getElementById('loanTermRange');
const resetBtn = document.getElementById('resetCalculator');
const amountPresets = document.querySelectorAll('.amount-preset');
const exportBtn = document.getElementById('exportSchedule');
const paginationBtns = document.querySelectorAll('.schedule-pagination');

// Chart instances
let loanChart, paymentBreakdownChart, interestOverTimeChart;
let amortizationSchedule = [];
let currentPage = 1;
const itemsPerPage = 12;

// Event Listeners
document.addEventListener('DOMContentLoaded', initCalculator);
form.addEventListener('submit', handleSubmit);
resetBtn.addEventListener('click', resetCalculator);
loanAmountRange.addEventListener('input', syncLoanAmount);
loanAmountInput.addEventListener('input', syncLoanAmountRange);
interestRateRange.addEventListener('input', syncInterestRate);
interestRateInput.addEventListener('input', syncInterestRateRange);
loanTermRange.addEventListener('input', syncLoanTerm);
loanYearsInput.addEventListener('input', syncLoanTermRange);
loanMonthsInput.addEventListener('input', syncLoanTermRange);
amountPresets.forEach(btn => btn.addEventListener('click', setPresetAmount));
exportBtn.addEventListener('click', exportSchedule);
paginationBtns.forEach(btn => btn.addEventListener('click', handlePagination));

// Initialize calculator
function initCalculator() {
    syncLoanAmount();
    syncInterestRate();
    syncLoanTerm();
}

// Handle form submission
function handleSubmit(e) {
    e.preventDefault();
    calculateLoan();
}

// Main calculation function
function calculateLoan() {
    // Get inputs
    const loanAmount = parseFloat(loanAmountInput.value);
    const annualRate = parseFloat(interestRateInput.value);
    const monthlyRate = annualRate / 100 / 12;
    const years = parseInt(loanYearsInput.value);
    const months = parseInt(loanMonthsInput.value);
    const loanTerm = years * 12 + months;
    const startDate = new Date();

    // Validate inputs
    if (isNaN(loanAmount) || isNaN(annualRate) || isNaN(loanTerm) || loanTerm <= 0) {
        alert('Please enter valid loan details');
        return;
    }

    // Calculate monthly payment
    const monthlyPayment = (loanAmount * monthlyRate * Math.pow(1 + monthlyRate, loanTerm)) /
                         (Math.pow(1 + monthlyRate, loanTerm) - 1);
    const totalPayment = monthlyPayment * loanTerm;
    const totalInterest = totalPayment - loanAmount;

    // Display results
    document.getElementById('monthlyPayment').textContent = monthlyPayment.toLocaleString('en-US', {
        style: 'currency',
        currency: 'ETB',
        minimumFractionDigits: 2
    });
    
    document.getElementById('totalInterest').textContent = totalInterest.toLocaleString('en-US', {
        style: 'currency',
        currency: 'ETB',
        minimumFractionDigits: 2
    });
    
    document.getElementById('totalPayment').textContent = totalPayment.toLocaleString('en-US', {
        style: 'currency',
        currency: 'ETB',
        minimumFractionDigits: 2
    });

    // Generate data
    amortizationSchedule = generateSchedule(loanAmount, monthlyRate, monthlyPayment, loanTerm, startDate);
    currentPage = 1;
    
    // Update UI
    document.getElementById('loanResult').classList.remove('d-none');
    document.getElementById('resultsTabs').style.display = 'block';
    
    // Generate charts
    generateCharts(loanAmount, totalInterest, monthlyPayment, amortizationSchedule);
    renderSchedulePage(1);
}

// Generate amortization schedule
function generateSchedule(principal, monthlyRate, monthlyPayment, term, startDate) {
    let balance = principal;
    const schedule = [];
    let date = new Date(startDate);

    for (let month = 1; month <= term; month++) {
        date.setMonth(date.getMonth() + 1);
        const interest = balance * monthlyRate;
        const principalPaid = monthlyPayment - interest;
        balance -= principalPaid;

        // Fix floating point precision issues
        if (month === term) {
            balance = 0;
        }

        schedule.push({
            month,
            date: new Date(date),
            payment: monthlyPayment,
            principal: principalPaid,
            interest,
            balance: Math.max(0, balance)
        });
    }

    return schedule;
}

// Generate all charts
function generateCharts(principal, totalInterest, monthlyPayment, schedule) {
    generateLoanChart(principal, totalInterest);
    generatePaymentBreakdownChart(monthlyPayment, principal, totalInterest);
    generateInterestOverTimeChart(schedule);
}

// Main loan chart (doughnut)
function generateLoanChart(principal, totalInterest) {
    const ctx = document.getElementById('loanChart').getContext('2d');

    if (loanChart) loanChart.destroy();

    loanChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Loan Amount', 'Total Interest'],
            datasets: [{
                data: [principal, totalInterest],
                backgroundColor: ['#4e73df', '#e74a3b'],
                hoverBackgroundColor: ['#2e59d9', '#be2617'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.label}: ${context.raw.toLocaleString('en-US', {
                                style: 'currency',
                                currency: 'ETB'
                            })}`;
                        }
                    }
                },
                legend: {
                    position: 'bottom',
                    labels: {
                        font: {
                            size: 14
                        }
                    }
                },
                datalabels: {
                    formatter: (value) => {
                        return ((value / (principal + totalInterest)) * 100).toFixed(1) + '%';
                    },
                    color: '#fff',
                    font: {
                        weight: 'bold'
                    }
                }
            },
            cutout: '65%'
        },
        plugins: [ChartDataLabels]
    });
}

// Payment breakdown chart (bar)
function generatePaymentBreakdownChart(monthlyPayment, principal, totalInterest) {
    const ctx = document.getElementById('paymentBreakdownChart').getContext('2d');
    const term = principal + totalInterest / monthlyPayment;

    if (paymentBreakdownChart) paymentBreakdownChart.destroy();

    paymentBreakdownChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Principal', 'Interest'],
            datasets: [{
                label: 'Payment Breakdown',
                data: [principal, totalInterest],
                backgroundColor: ['#4e73df', '#e74a3b'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.label}: ${context.raw.toLocaleString('en-US', {
                                style: 'currency',
                                currency: 'ETB'
                            })} (${((context.raw / (principal + totalInterest)) * 100).toFixed(1)}%)`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString('en-US', {
                                style: 'currency',
                                currency: 'ETB',
                                maximumFractionDigits: 0
                            });
                        }
                    }
                }
            }
        }
    });
}

// Interest over time chart (line)
function generateInterestOverTimeChart(schedule) {
    const ctx = document.getElementById('interestOverTimeChart').getContext('2d');
    const labels = schedule.map((_, i) => i + 1);
    const interestData = schedule.map(p => p.interest);
    const principalData = schedule.map(p => p.principal);

    if (interestOverTimeChart) interestOverTimeChart.destroy();

    interestOverTimeChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Interest',
                    data: interestData,
                    borderColor: '#e74a3b',
                    backgroundColor: 'rgba(231, 74, 59, 0.1)',
                    tension: 0.3,
                    fill: true
                },
                {
                    label: 'Principal',
                    data: principalData,
                    borderColor: '#4e73df',
                    backgroundColor: 'rgba(78, 115, 223, 0.1)',
                    tension: 0.3,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.dataset.label}: ${context.raw.toLocaleString('en-US', {
                                style: 'currency',
                                currency: 'ETB'
                            })}`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString('en-US', {
                                style: 'currency',
                                currency: 'ETB',
                                maximumFractionDigits: 0
                            });
                        }
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Month'
                    }
                }
            }
        }
    });
}

// Render schedule page
function renderSchedulePage(page) {
    const startIdx = (page - 1) * itemsPerPage;
    const endIdx = startIdx + itemsPerPage;
    const pageData = amortizationSchedule.slice(startIdx, endIdx);
    const totalPages = Math.ceil(amortizationSchedule.length / itemsPerPage);

    let scheduleHTML = '';
    pageData.forEach(payment => {
        scheduleHTML += `
            <tr>
                <td>${payment.month}</td>
                <td>${payment.date.toLocaleDateString()}</td>
                <td>${payment.payment.toFixed(2)}</td>
                <td>${payment.principal.toFixed(2)}</td>
                <td>${payment.interest.toFixed(2)}</td>
                <td>${payment.balance.toFixed(2)}</td>
            </tr>
        `;
    });

    document.getElementById('scheduleBody').innerHTML = scheduleHTML;
    document.getElementById('pageIndicator').textContent = `Page ${page} of ${totalPages}`;
    currentPage = page;
}

// Pagination handler
function handlePagination(e) {
    const totalPages = Math.ceil(amortizationSchedule.length / itemsPerPage);
    const action = e.currentTarget.dataset.page;

    switch(action) {
        case 'first':
            if (currentPage > 1) renderSchedulePage(1);
            break;
        case 'prev':
            if (currentPage > 1) renderSchedulePage(currentPage - 1);
            break;
        case 'next':
            if (currentPage < totalPages) renderSchedulePage(currentPage + 1);
            break;
        case 'last':
            if (currentPage < totalPages) renderSchedulePage(totalPages);
            break;
    }
}

// Export schedule
function exportSchedule() {
    const scheduleCSV = [
        ['Payment #', 'Date', 'Payment', 'Principal', 'Interest', 'Remaining'],
        ...amortizationSchedule.map(p => [
            p.month,
            p.date.toLocaleDateString(),
            p.payment.toFixed(2),
            p.principal.toFixed(2),
            p.interest.toFixed(2),
            p.balance.toFixed(2)
        ])
    ].map(row => row.join(',')).join('\n');

    const blob = new Blob([scheduleCSV], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `loan_schedule_${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

// Reset calculator
function resetCalculator() {
    form.reset();
    document.getElementById('loanResult').classList.add('d-none');
    document.getElementById('resultsTabs').style.display = 'none';
    currentPage = 1;
    amortizationSchedule = [];
    
    // Reset charts
    if (loanChart) loanChart.destroy();
    if (paymentBreakdownChart) paymentBreakdownChart.destroy();
    if (interestOverTimeChart) interestOverTimeChart.destroy();
    
    // Reset schedule
    document.getElementById('scheduleBody').innerHTML = '';
    document.getElementById('pageIndicator').textContent = 'Page 1';
    
    // Reset inputs
    loanAmountRange.value = 100000;
    interestRateRange.value = 13.5;
    loanTermRange.value = 12;
    loanYearsInput.value = 1;
    loanMonthsInput.value = 0;
}

// Sync input fields
function syncLoanAmount() {
    loanAmountInput.value = loanAmountRange.value;
}

function syncLoanAmountRange() {
    loanAmountRange.value = loanAmountInput.value;
}

function syncInterestRate() {
    interestRateInput.value = interestRateRange.value;
}

function syncInterestRateRange() {
    interestRateRange.value = interestRateInput.value;
}

function syncLoanTerm() {
    const totalMonths = parseInt(loanTermRange.value);
    loanYearsInput.value = Math.floor(totalMonths / 12);
    loanMonthsInput.value = totalMonths % 12;
}

function syncLoanTermRange() {
    const years = parseInt(loanYearsInput.value) || 0;
    const months = parseInt(loanMonthsInput.value) || 0;
    loanTermRange.value = (years * 12) + months;
}

// Set preset amount
function setPresetAmount(e) {
    const amount = e.currentTarget.dataset.amount;
    loanAmountInput.value = amount;
    loanAmountRange.value = amount;
}
