// Loan Calculator Functionality
document.addEventListener('DOMContentLoaded', function() {
    const loanCalculator = document.getElementById('loanCalculator');
    if (loanCalculator) {
        initializeLoanCalculator();
    }
});

function initializeLoanCalculator() {
    document.getElementById('loanCalculator').addEventListener('submit', function(e) {
        e.preventDefault();
        calculateLoan();
    });

    function calculateLoan() {
        // Get inputs
        const loanAmount = parseFloat(document.getElementById('loanAmount').value);
        const interestRate = parseFloat(document.getElementById('interestRate').value) / 100 / 12;
        const years = parseInt(document.getElementById('loanYears').value);
        const months = parseInt(document.getElementById('loanMonths').value);
        const loanTerm = years * 12 + months;

        // Calculate monthly payment
        const monthlyPayment = (loanAmount * interestRate * Math.pow(1 + interestRate, loanTerm)) /
            (Math.pow(1 + interestRate, loanTerm) - 1);
        const totalPayment = monthlyPayment * loanTerm;
        const totalInterest = totalPayment - loanAmount;

        // Display results
        document.getElementById('monthlyPayment').textContent = monthlyPayment.toFixed(2) + ' ETB';
        document.getElementById('totalInterest').textContent = totalInterest.toFixed(2) + ' ETB';
        document.getElementById('totalPayment').textContent = totalPayment.toFixed(2) + ' ETB';
        document.getElementById('loanResult').style.display = 'block';

        // Generate amortization schedule
        generateSchedule(loanAmount, interestRate, monthlyPayment, loanTerm);

        // Generate chart
        generateChart(loanAmount, totalInterest);

        // Show hidden sections
        document.getElementById('loanChartContainer').style.display = 'block';
        document.getElementById('scheduleContainer').style.display = 'block';
    }

    function generateSchedule(principal, monthlyRate, monthlyPayment, term) {
        let balance = principal;
        let scheduleHTML = '';

        for (let month = 1; month <= term; month++) {
            const interest = balance * monthlyRate;
            const principalPaid = monthlyPayment - interest;
            balance -= principalPaid;

            // Fix floating point precision issues
            if (month === term) balance = 0;

            scheduleHTML += `
                <tr>
                    <td>${month}</td>
                    <td>${monthlyPayment.toFixed(2)}</td>
                    <td>${principalPaid.toFixed(2)}</td>
                    <td>${interest.toFixed(2)}</td>
                    <td>${Math.max(0, balance).toFixed(2)}</td>
                </tr>
            `;
        }

        document.getElementById('scheduleBody').innerHTML = scheduleHTML;
    }

    function generateChart(principal, totalInterest) {
        const ctx = document.getElementById('loanChart').getContext('2d');

        // Destroy previous chart if exists
        if (window.loanChart) window.loanChart.destroy();

        window.loanChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Loan Amount', 'Total Interest'],
                datasets: [{
                    data: [principal, totalInterest],
                    backgroundColor: ['#4e73df', '#e74a3b'],
                    hoverBackgroundColor: ['#2e59d9', '#be2617'],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.raw.toFixed(2) + ' ETB';
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
                    }
                },
                cutout: '70%'
            }
        });
    }
}
