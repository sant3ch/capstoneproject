/**
 * Charts.js Module for Jorish Express Laundry Admin Dashboard
 * Usage: Include this file and call initCharts(weeklyData, monthlyData, serviceData)
 */

class DashboardCharts {
    constructor() {
        this.charts = {
            weekly: null,
            monthly: null,
            service: null
        };
    }

    /**
     * Initialize all dashboard charts
     * @param {Object} weeklyData - Weekly sales data {labels: [], values: []}
     * @param {Object} monthlyData - Monthly sales data {labels: [], values: []}
     * @param {Object} serviceData - Service type data {labels: [], values: []}
     */
    initCharts(weeklyData, monthlyData, serviceData) {
        if (!Chart) {
            console.error('Chart.js library is not loaded');
            return;
        }

        this.initWeeklyChart(weeklyData);
        this.initMonthlyChart(monthlyData);
        this.initServiceChart(serviceData);
        
        // Handle window resize
        window.addEventListener('resize', this.handleResize.bind(this));
    }

    /**
     * Initialize Weekly Sales Bar Chart
     * @param {Object} data - Weekly sales data
     */
    initWeeklyChart(data) {
        const ctx = document.getElementById('weeklyChart');
        if (!ctx) {
            console.error('Weekly chart canvas not found');
            return;
        }

        this.charts.weekly = new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: data.labels || [],
                datasets: [{
                    label: 'Sales (₱)',
                    data: data.values || [],
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(255, 205, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)',
                        'rgba(255, 159, 64, 0.7)',
                        'rgba(201, 203, 207, 0.7)'
                    ],
                    borderColor: [
                        'rgb(54, 162, 235)',
                        'rgb(255, 99, 132)',
                        'rgb(255, 205, 86)',
                        'rgb(75, 192, 192)',
                        'rgb(153, 102, 255)',
                        'rgb(255, 159, 64)',
                        'rgb(201, 203, 207)'
                    ],
                    borderWidth: 1,
                    borderRadius: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '₱' + context.parsed.y.toLocaleString('en-PH', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString('en-PH', {
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 0
                                });
                            },
                            font: {
                                size: 10
                            }
                        },
                        grid: {
                            drawBorder: false
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 10
                            }
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    /**
     * Initialize Monthly Sales Line Chart
     * @param {Object} data - Monthly sales data
     */
    initMonthlyChart(data) {
        const ctx = document.getElementById('monthlyChart');
        if (!ctx) {
            console.error('Monthly chart canvas not found');
            return;
        }

        this.charts.monthly = new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: data.labels || [],
                datasets: [{
                    label: 'Sales (₱)',
                    data: data.values || [],
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    borderColor: 'rgb(75, 192, 192)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: 'rgb(75, 192, 192)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 1,
                    pointRadius: 3,
                    pointHoverRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '₱' + context.parsed.y.toLocaleString('en-PH', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString('en-PH', {
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 0
                                });
                            },
                            font: {
                                size: 10
                            }
                        },
                        grid: {
                            drawBorder: false
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 9
                            },
                            maxRotation: 45
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    /**
     * Initialize Service Type Sales Doughnut Chart
     * @param {Object} data - Service type data
     */
    initServiceChart(data) {
        const ctx = document.getElementById('serviceChart');
        if (!ctx) {
            console.error('Service chart canvas not found');
            return;
        }

        this.charts.service = new Chart(ctx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: data.labels || [],
                datasets: [{
                    label: 'Sales (₱)',
                    data: data.values || [],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.8)', // Full Service
                        'rgba(54, 162, 235, 0.8)'   // Self Service
                    ],
                    borderColor: [
                        'rgb(255, 99, 132)',
                        'rgb(54, 162, 235)'
                    ],
                    borderWidth: 1,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            boxWidth: 12,
                            font: {
                                size: 11
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                return `${label}: ₱${value.toLocaleString('en-PH', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                })} (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '65%'
            }
        });
    }

    /**
     * Handle window resize - update charts
     */
    handleResize() {
        Object.values(this.charts).forEach(chart => {
            if (chart) {
                chart.resize();
            }
        });
    }

    /**
     * Destroy all charts (for cleanup)
     */
    destroyCharts() {
        Object.values(this.charts).forEach(chart => {
            if (chart) {
                chart.destroy();
            }
        });
        this.charts = {
            weekly: null,
            monthly: null,
            service: null
        };
    }

    /**
     * Update weekly chart data
     * @param {Object} data - New weekly data
     */
    updateWeeklyChart(data) {
        if (this.charts.weekly) {
            this.charts.weekly.data.labels = data.labels || [];
            this.charts.weekly.data.datasets[0].data = data.values || [];
            this.charts.weekly.update();
        }
    }

    /**
     * Update monthly chart data
     * @param {Object} data - New monthly data
     */
    updateMonthlyChart(data) {
        if (this.charts.monthly) {
            this.charts.monthly.data.labels = data.labels || [];
            this.charts.monthly.data.datasets[0].data = data.values || [];
            this.charts.monthly.update();
        }
    }

    /**
     * Update service chart data
     * @param {Object} data - New service data
     */
    updateServiceChart(data) {
        if (this.charts.service) {
            this.charts.service.data.labels = data.labels || [];
            this.charts.service.data.datasets[0].data = data.values || [];
            this.charts.service.update();
        }
    }
}

// Create global instance
const dashboardCharts = new DashboardCharts();

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = dashboardCharts;
}