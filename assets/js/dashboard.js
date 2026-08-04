// ============================================================
// BodaERP - Dashboard Charts
// ============================================================

document.addEventListener('DOMContentLoaded', function() {
    
    // ----- Revenue Line Chart -----
    const revenueChart = document.querySelector('#revenueChart');
    if (revenueChart) {
        const options = {
            series: [{
                name: 'Revenue (UGX)',
                data: [8, 12, 10, 15, 18, 22, 20, 25, 28, 30, 35, 40]
            }],
            chart: {
                type: 'area',
                height: 300,
                toolbar: { show: false },
                animations: {
                    enabled: true,
                    easing: 'easeinout',
                    speed: 800,
                    animateGradually: { enabled: true, delay: 150 }
                }
            },
            dataLabels: { enabled: false },
            stroke: {
                curve: 'smooth',
                width: 3,
                colors: ['#0d6efd']
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.4,
                    opacityTo: 0.05,
                    colorStops: [
                        { offset: 0, color: '#0d6efd', opacity: 0.4 },
                        { offset: 100, color: '#0d6efd', opacity: 0.05 }
                    ]
                }
            },
            xaxis: {
                categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                labels: { style: { colors: '#6c757d', fontSize: '12px' } },
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            yaxis: {
                labels: {
                    formatter: (val) => 'UGX ' + val + 'M',
                    style: { colors: '#6c757d', fontSize: '12px' }
                }
            },
            grid: {
                borderColor: '#f1f1f1',
                strokeDashArray: 4,
                xaxis: { lines: { show: true } },
                yaxis: { lines: { show: true } }
            },
            colors: ['#0d6efd'],
            tooltip: {
                theme: 'dark',
                y: {
                    formatter: (val) => 'UGX ' + val + ' Million'
                }
            }
        };
        
        const chart = new ApexCharts(revenueChart, options);
        chart.render();
    }

    // ----- Pie Chart (Revenue Split) -----
    const pieChart = document.querySelector('#pieChart');
    if (pieChart) {
        const options = {
            series: [60, 26, 14],
            chart: {
                type: 'donut',
                height: 220,
                animations: {
                    enabled: true,
                    easing: 'easeinout',
                    speed: 800
                }
            },
            labels: ['City Council', 'Boda Association', 'Kakebe Tech'],
            colors: ['#0d6efd', '#198754', '#0dcaf0'],
            legend: {
                show: false
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '70%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'Total Split',
                                formatter: () => '100%',
                                fontSize: '14px',
                                fontFamily: 'Inter, sans-serif',
                                color: '#1a1a2e'
                            }
                        }
                    }
                }
            },
            stroke: {
                width: 0
            },
            dataLabels: {
                enabled: false
            },
            tooltip: {
                y: {
                    formatter: (val) => val + '%'
                }
            },
            responsive: [
                {
                    breakpoint: 480,
                    options: {
                        chart: { height: 180 }
                    }
                }
            ]
        };
        
        const chart = new ApexCharts(pieChart, options);
        chart.render();
    }
    
});