/**
 * File: assets/js/admin.js
 * Description: Admin JavaScript for OctaHexa Server Monitor plugin
 * Version: 1.0.0
 * 
 * @package OctaHexa_Server_Monitor
 */

(function($) {
    'use strict';

    /**
     * Server Monitor Admin Handler
     */
    const OHServerMonitor = {
        
        chart: null,
        updateInterval: null,
        
        /**
         * Initialize
         */
        init: function() {
            this.initChart();
            this.startAutoUpdate();
            this.bindEvents();
        },
        
        /**
         * Initialize Chart.js
         */
        initChart: function() {
            const ctx = document.getElementById('oh-resource-chart');
            if (!ctx) return;
            
            this.chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [
                        {
                            label: oh_server_monitor.strings.cpu + ' %',
                            data: [],
                            borderColor: 'rgb(255, 99, 132)',
                            backgroundColor: 'rgba(255, 99, 132, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: oh_server_monitor.strings.memory + ' %',
                            data: [],
                            borderColor: 'rgb(54, 162, 235)',
                            backgroundColor: 'rgba(54, 162, 235, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: oh_server_monitor.strings.load,
                            data: [],
                            borderColor: 'rgb(255, 206, 86)',
                            backgroundColor: 'rgba(255, 206, 86, 0.1)',
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Time'
                            }
                        },
                        y: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Usage'
                            },
                            min: 0,
                            max: 100
                        }
                    }
                }
            });
            
            // Load initial data
            this.loadChartData();
        },
        
        /**
         * Load chart data from server
         */
        loadChartData: function() {
            // In a real implementation, this would fetch historical data
            // For now, we'll start with empty data and build it up
            this.updateChart();
        },
        
        /**
         * Start auto-update
         */
        startAutoUpdate: function() {
            // Update immediately
            this.updateStats();
            
            // Then update every 5 seconds
            this.updateInterval = setInterval(() => {
                this.updateStats();
            }, 5000);
        },
        
        /**
         * Stop auto-update
         */
        stopAutoUpdate: function() {
            if (this.updateInterval) {
                clearInterval(this.updateInterval);
            }
        },
        
        /**
         * Update stats via AJAX
         */
        updateStats: function() {
            $.ajax({
                url: oh_server_monitor.ajax_url,
                type: 'POST',
                data: {
                    action: 'oh_get_server_stats',
                    nonce: oh_server_monitor.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.updateDisplay(response.data);
                        this.updateChart(response.data);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Failed to fetch server stats:', error);
                }
            });
        },
        
        /**
         * Update display values
         */
        updateDisplay: function(data) {
            // Update CPU
            $('#oh-cpu-usage').text(data.cpu);
            this.updateStatBoxClass('cpu', data.cpu);
            
            // Update Memory
            $('#oh-memory-usage').text(data.memory.percentage);
            this.updateStatBoxClass('memory', data.memory.percentage);
            
            // Update Load
            $('#oh-load-average').text(data.load['1min']);
            this.updateStatBoxClass('load', data.load['1min'], data.cores * 2);
            
            // Update MySQL
            $('#oh-mysql-cpu').text(data.mysql.cpu);
            
            // Update Disk
            $('#oh-disk-usage').text(data.disk.percentage);
            this.updateStatBoxClass('disk', data.disk.percentage);
        },
        
        /**
         * Update stat box class based on threshold
         */
        updateStatBoxClass: function(type, value, threshold) {
            const thresholds = {
                cpu: 80,
                memory: 85,
                load: threshold || 4,
                disk: 90
            };
            
            const $box = $('#oh-' + type + '-usage').closest('.oh-stat-box');
            $box.removeClass('oh-stat-normal oh-stat-warning oh-stat-critical');
            
            if (value >= thresholds[type]) {
                $box.addClass('oh-stat-critical');
            } else if (value >= thresholds[type] * 0.8) {
                $box.addClass('oh-stat-warning');
            } else {
                $box.addClass('oh-stat-normal');
            }
        },
        
        /**
         * Update chart with new data
         */
        updateChart: function(data) {
            if (!this.chart || !data) return;
            
            const now = new Date();
            const timeLabel = now.getHours() + ':' + ('0' + now.getMinutes()).slice(-2);
            
            // Add new data
            this.chart.data.labels.push(timeLabel);
            this.chart.data.datasets[0].data.push(data.cpu);
            this.chart.data.datasets[1].data.push(data.memory.percentage);
            this.chart.data.datasets[2].data.push(data.load['1min']);
            
            // Keep only last 50 points
            if (this.chart.data.labels.length > 50) {
                this.chart.data.labels.shift();
                this.chart.data.datasets.forEach(dataset => {
                    dataset.data.shift();
                });
            }
            
            this.chart.update();
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            // Pause updates when tab is not visible
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    this.stopAutoUpdate();
                } else {
                    this.startAutoUpdate();
                }
            });
            
            // Clean up on page unload
            $(window).on('beforeunload', () => {
                this.stopAutoUpdate();
            });
        }
    };
    
    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        if ($('.oh-server-monitor-dashboard').length) {
            OHServerMonitor.init();
        }
    });
    
})(jQuery);
