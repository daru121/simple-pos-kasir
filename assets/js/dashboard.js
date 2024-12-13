document.addEventListener('DOMContentLoaded', function() {
    let trendChart;

    function initTrendChart(data) {
        if (trendChart) {
            trendChart.destroy();
        }

        trendChart = new Chart(document.getElementById('trendChart'), {
            type: 'line',
            data: {
                labels: data.map(item => {
                    const date = new Date(item.date);
                    return date.getDate();
                }),
                datasets: [
                    {
                        label: 'Penjualan',
                        data: data.map(item => parseFloat(item.total_sales)),
                        borderColor: '#36A2EB',
                        backgroundColor: '#36A2EB',
                        fill: false,
                        tension: 0,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#36A2EB',
                        pointBorderWidth: 2,
                        borderWidth: 2
                    },
                    {
                        label: 'Profit',
                        data: data.map(item => parseFloat(item.total_profit)),
                        borderColor: '#22c55e',
                        backgroundColor: '#22c55e',
                        fill: false,
                        tension: 0,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#22c55e',
                        pointBorderWidth: 2,
                        borderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            boxWidth: 10,
                            boxHeight: 10,
                            color: '#666',
                            font: {
                                size: 12,
                                family: "'Poppins', sans-serif"
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#fff',
                        titleColor: '#333',
                        bodyColor: '#666',
                        borderColor: '#e5e7eb',
                        borderWidth: 1,
                        padding: 12,
                        usePointStyle: true,
                        displayColors: false,
                        callbacks: {
                            title: function(tooltipItems) {
                                const date = new Date(data[tooltipItems[0].dataIndex].date);
                                return 'Tanggal ' + date.getDate();
                            },
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': Rp ';
                                }
                                label += new Intl.NumberFormat('id-ID').format(context.parsed.y);
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: true,
                            color: '#f3f4f6',
                            drawBorder: false
                        },
                        ticks: {
                            font: {
                                size: 11
                            },
                            color: '#666',
                            maxRotation: 0,
                            autoSkip: false,
                            callback: function(value, index) {
                                return value;
                            }
                        },
                        title: {
                            display: true,
                            text: 'Tanggal',
                            color: '#666',
                            font: {
                                size: 12,
                                weight: 'normal'
                            },
                            padding: {top: 10}
                        }
                    },
                    y: {
                        position: 'left',
                        grid: {
                            display: true,
                            color: '#f3f4f6',
                            drawBorder: false
                        },
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('id-ID').format(value);
                            },
                            font: {
                                size: 11
                            },
                            color: '#666',
                            padding: 10,
                            maxTicksLimit: 6
                        },
                        beginAtZero: true,
                        suggestedMin: 0,
                        suggestedMax: function(context) {
                            const maxSales = Math.max(...context.chart.data.datasets[0].data);
                            const maxProfit = Math.max(...context.chart.data.datasets[1].data);
                            const absoluteMax = Math.max(maxSales, maxProfit);
                            return absoluteMax * 1.2;
                        }
                    }
                },
                layout: {
                    padding: {
                        left: 10,
                        right: 25,
                        top: 25,
                        bottom: 10
                    }
                }
            }
        });
    }

    // Initialize chart with data
    initTrendChart(chartData);

    // Initialize Donut Chart
    const donutChart = new Chart(document.getElementById('donutChart'), {
        type: 'doughnut',
        data: {
            labels: topProducts.map(item => item.nama_barang),
            datasets: [{
                data: topProducts.map(item => item.total_sold),
                backgroundColor: [
                    '#4e73df',
                    '#1cc88a',
                    '#36b9cc',
                    '#f6c23e',
                    '#e74a3b'
                ],
                borderWidth: 2,
                borderColor: '#ffffff',
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '75%',
            layout: {
                padding: 20
            },
            scales: {
                x: {
                    display: false
                },
                y: {
                    display: false
                }
            },
            plugins: {
                legend: {
                    position: 'right',
                    align: 'center',
                    labels: {
                        padding: 15,
                        usePointStyle: true,
                        pointStyle: 'circle',
                        boxWidth: 8,
                        boxHeight: 8,
                        generateLabels: function(chart) {
                            const data = chart.data;
                            const total = data.datasets[0].data.reduce((a, b) => a + b, 0);
                            
                            return data.labels.map((label, i) => {
                                const value = data.datasets[0].data[i];
                                const percentage = ((value / total) * 100).toFixed(1);
                                return {
                                    text: `${label}`,
                                    fillStyle: data.datasets[0].backgroundColor[i],
                                    strokeStyle: data.datasets[0].backgroundColor[i],
                                    pointStyle: 'circle',
                                    index: i,
                                    lineWidth: 0,
                                    fontColor: '#333',
                                    additionalText: `${value} unit (${percentage}%)`
                                };
                            });
                        },
                        font: {
                            size: 12,
                            family: "'Poppins', sans-serif",
                            weight: '500'
                        }
                    }
                },
                tooltip: {
                    enabled: true,
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: '#333',
                    bodyColor: '#666',
                    borderColor: 'rgba(0,0,0,0.1)',
                    borderWidth: 1,
                    padding: 10,
                    boxPadding: 5,
                    usePointStyle: true,
                    callbacks: {
                        label: function(context) {
                            const value = context.raw;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return ` ${value} unit (${percentage}%)`;
                        }
                    }
                }
            },
            plugins: [{
                afterDraw: function(chart) {
                    const ctx = chart.ctx;
                    const legendItems = chart.legend.legendItems;
                    
                    ctx.save();
                    legendItems.forEach((item, i) => {
                        const text = item.text;
                        const additionalText = item.additionalText;
                        const x = item.x;
                        const y = item.y;
                        
                        // Reset text yang asli
                        item.text = text;
                        
                        // Tambahkan detail di bawah label
                        if (additionalText) {
                            ctx.textAlign = 'left';
                            ctx.textBaseline = 'top';
                            ctx.fillStyle = '#666';
                            ctx.font = '11px Poppins';
                            ctx.fillText(additionalText, x + 20, y + 15);
                        }
                    });
                    ctx.restore();
                }
            }]
        }
    });

    // Add hover animation to stat cards
    document.querySelectorAll('.stat-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.querySelector('.stat-icon i').style.transform = 'scale(1.2) rotate(10deg)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.querySelector('.stat-icon i').style.transform = 'scale(1) rotate(0)';
        });
    });

    // Update chart options to use light theme colors
    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                labels: {
                    color: '#333' // Dark text for legend
                }
            }
        },
        scales: {
            x: {
                grid: {
                    color: 'rgba(0, 0, 0, 0.1)' // Light grid lines
                },
                ticks: {
                    color: '#333' // Dark text for x-axis
                }
            },
            y: {
                grid: {
                    color: 'rgba(0, 0, 0, 0.1)' // Light grid lines
                },
                ticks: {
                    color: '#333' // Dark text for y-axis
                }
            }
        }
    };

    trendChart.options = { ...trendChart.options, ...chartOptions };
    donutChart.options = { 
        ...donutChart.options, 
        ...chartOptions,
        plugins: {
            legend: {
                labels: {
                    color: '#333'
                }
            }
        }
    };
    
    trendChart.update();
    donutChart.update();

    // Add this to your existing chart initialization
    window.addEventListener('resize', function() {
        trendChart.resize();
        donutChart.resize();
    });

    // Add period change handler
    document.getElementById('chartPeriod').addEventListener('change', function() {
        const days = this.value;
        // Add your logic to fetch and update chart data
        updateChartData(days);
    });

    function updateChartData(days) {
        const chartContainer = document.querySelector('.chart-container');
        chartContainer.classList.add('loading');
        
        // Simulate data loading (replace with actual API call)
        setTimeout(() => {
            // Update your chart data here
            chartContainer.classList.remove('loading');
        }, 500);
    }
});
