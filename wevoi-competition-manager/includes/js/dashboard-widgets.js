/**
 * WeVoi Dashboard Widgets JavaScript
 * 
 * Handles all dashboard widget functionality:
 * - Modal statistics
 * - AJAX data loading
 * - Chart and data visualization
 */
jQuery(document).ready(function($) {
    // Configuration
    const DEBUG = false;
    const WEVOI = {
        selectors: {
            studentModal: '#students-stats-modal',
            judgesModal: '#judges-stats-modal',
            supervisorsModal: '#supervisors-stats-modal'
        },
        nonces: {
            students: $('#students_modal_nonce').val(),
            judges: $('#judges_modal_nonce').val(),
            supervisors: $('#supervisors_modal_nonce').val()
        },
        endpoints: {
            students: 'get_students_modal_stats',
            judges: 'get_judges_modal_stats',
            supervisors: 'get_supervisors_modal_stats'
        }
    };

    /**
     * Utility Functions
     */
    const Utils = {
        log: function(message, data) {
            if (DEBUG && window.console && console.log) {
                if (data !== undefined) {
                    console.log(message, data);
                } else {
                    console.log(message);
                }
            }
        },
        
        error: function(message, data) {
            if (window.console && console.error) {
                console.error(message, data || '');
            }
        },
        
        showError: function(message) {
            alert(message || 'An error occurred. Please try again.');
        },
        
        // Safe AJAX with error handling
        safeAjax: function(options) {
            try {
                // Add default error handling
                const originalErrorHandler = options.error;
                options.error = function(xhr, status, error) {
                    Utils.error('AJAX Error:', { xhr, status, error });
                    
                    if (typeof originalErrorHandler === 'function') {
                        originalErrorHandler(xhr, status, error);
                    } else {
                        Utils.showError('Error loading data. Please try again.');
                    }
                };
                
                // Send the AJAX request
                return $.ajax(options);
            } catch (e) {
                Utils.error('Exception in AJAX request:', e);
                Utils.showError('An unexpected error occurred.');
                return false;
            }
        }
    };

    /**
     * Modal Management
     */
    const ModalManager = {
        // Open modal
        open: function(modalSelector) {
            const $modal = $(modalSelector);
            if ($modal.length) {
                $modal.show();
            } else {
                Utils.error('Modal not found:', modalSelector);
            }
        },
        
        // Close modal
        close: function(modalSelector) {
            if (modalSelector) {
                $(modalSelector).hide();
            } else {
                $('.wevoi-modal').hide();
            }
        }
    };

    /**
     * Statistics Rendering Functions
     */
    const StatsRenderer = {
        // Render total applications stats
        renderTotalApplications: function(data, containerSelector) {
            const content = `
                <div class="stats-grid-4">
                    <div class="stats-grid-item">
                        <span class="stats-label">Total</span>
                        <span class="stats-value">${data.total}</span>
                    </div>
                    <div class="stats-grid-item">
                        <span class="stats-label">Active</span>
                        <span class="stats-value">${data.active}</span>
                    </div>
                    <div class="stats-grid-item">
                        <span class="stats-label">Pending</span>
                        <span class="stats-value">${data.pending || 0}</span>
                    </div>
                    <div class="stats-grid-item">
                        <span class="stats-label">Last 30 Days</span>
                        <span class="stats-value">${data.last_30_days}</span>
                    </div>
                </div>
            `;
            $(containerSelector).html(content);
        },

        // Render type distribution stats
        renderTypeDistribution: function(data, containerSelector) {
            if (!data || data.length === 0) {
                $(containerSelector).html('<p>No data available</p>');
                return;
            }

            let tableHTML = `
                <table class="stats-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Count</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            data.forEach(item => {
                const percentage = item.percentage !== undefined ? item.percentage : 
                    (item.count / data.reduce((sum, i) => sum + i.count, 0) * 100).toFixed(1);
                    
                tableHTML += `
                    <tr>
                        <td>${item.type}</td>
                        <td>${item.count}</td>
                        <td>${percentage}%</td>
                    </tr>
                `;
            });

            tableHTML += `
                    </tbody>
                </table>
            `;
            $(containerSelector).html(tableHTML);
        },

        // Render country distribution stats
        renderTopCountries: function(data, containerSelector) {
            if (!data || data.length === 0) {
                $(containerSelector).html('<p>No data available</p>');
                return;
            }

            let tableHTML = `
                <table class="stats-table">
                    <thead>
                        <tr>
                            <th>Country</th>
                            <th>Applications</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            data.forEach(country => {
                tableHTML += `
                    <tr>
                        <td>${country.country}</td>
                        <td>${country.applications}</td>
                        <td>${country.percentage}%</td>
                    </tr>
                `;
            });

            tableHTML += `
                    </tbody>
                </table>
            `;
            $(containerSelector).html(tableHTML);
        },

        // Render monthly trends stats
        renderMonthlyTrends: function(data, containerSelector) {
            if (!data || data.length === 0) {
                $(containerSelector).html('<p>No data available</p>');
                return;
            }

            let tableHTML = `
                <table class="stats-table">
                    <thead>
                        <tr><th>Month</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            data.forEach(item => {
                tableHTML += `
                    <tr>
                        <td>${item.month}</td>
                        <td>${item.total}</td>
                    </tr>
                `;
            });

            tableHTML += `
                    </tbody>
                </table>
            `;
            $(containerSelector).html(tableHTML);
        },

        // Render judge statistics
        renderJudgeStats: function(data) {
            // Total Applications
            this.renderTotalApplications({
                total: data.total_applications.total,
                active: data.total_applications.active,
                pending: 0,
                last_30_days: data.total_applications.last_30_days
            }, '#judge-total-applications .stats-card-content');

            // Type Distribution
            this.renderTypeDistribution(data.judge_type_distribution, 
                '#judge-type-distribution .stats-card-content');

            // Top Countries
            this.renderTopCountries(data.top_countries, 
                '#judge-top-countries .stats-card-content');

            // Monthly Trends
            this.renderMonthlyTrends(data.monthly_trends, 
                '#judge-monthly-trends .stats-card-content');
        },

        // Render supervisor statistics
        renderSupervisorStats: function(data) {
            // Total Applications
            this.renderTotalApplications({
                total: data.total_applications.total,
                active: data.total_applications.active_students,
                pending: data.total_applications.video_submissions,
                last_30_days: data.total_applications.last_30_days
            }, '#supervisor-total-applications .stats-card-content');

            // Type Distribution
            this.renderTypeDistribution(data.supervisor_type_distribution, 
                '#supervisor-type-distribution .stats-card-content');

            // Top Countries
            this.renderTopCountries(data.top_countries, 
                '#supervisor-top-countries .stats-card-content');

            // Monthly Trends
            this.renderMonthlyTrends(data.monthly_trends, 
                '#supervisor-monthly-trends .stats-card-content');
        }
    };

    /**
     * Event Handler Initialization
     */
    function initEventHandlers() {
        // Clear existing handlers to prevent duplicates
        $('.show-students-stats-modal, .show-judges-stats-modal, .show-supervisors-stats-modal').off('click');
        $('.modal-close, .wevoi-modal').off('click');

        // Modal open handlers
        $('.show-students-stats-modal').on('click', function() {
            ModalManager.open(WEVOI.selectors.studentModal);
            loadStats('students');
        });
        
        $('.show-judges-stats-modal').on('click', function() {
            ModalManager.open(WEVOI.selectors.judgesModal);
            loadStats('judges');
        });
        
        $('.show-supervisors-stats-modal').on('click', function() {
            ModalManager.open(WEVOI.selectors.supervisorsModal);
            loadStats('supervisors');
        });

        // Modal close handlers - using event delegation for better performance
        $(document).on('click', '.modal-close, .wevoi-modal', function(e) {
            if (e.target === this || $(e.target).hasClass('modal-close')) {
                const $modal = $(this).closest('.wevoi-modal');
                if ($modal.length > 0) {
                    $modal.hide();
                }
            }
        });
    }

    /**
     * Load Statistics Data
     */
    function loadStats(type) {
        if (!type || !WEVOI.endpoints[type] || !WEVOI.nonces[type]) {
            Utils.error('Invalid statistics type or missing configuration', type);
            return;
        }

        // Show loading indicators
        const $modal = $(WEVOI.selectors[type + 'Modal']);
        $modal.find('.stats-loading').show();
        
        Utils.safeAjax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: WEVOI.endpoints[type],
                nonce: WEVOI.nonces[type]
            },
            success: function(response) {
                if (response.success) {
                    // Hide loading indicators
                    $modal.find('.stats-loading').hide();
                    
                    // Process the data based on type
                    if (type === 'students') {
                        StatsRenderer.renderTotalApplications(response.data.total_applications, 
                            '#total-applications .stats-card-content');
                        StatsRenderer.renderTypeDistribution(response.data.partner_type_distribution, 
                            '#partner-type-distribution .stats-card-content');
                        StatsRenderer.renderTopCountries(response.data.top_countries, 
                            '#top-countries .stats-card-content');
                        StatsRenderer.renderMonthlyTrends(response.data.monthly_trends, 
                            '#monthly-trends .stats-card-content');
                    } else if (type === 'judges') {
                        StatsRenderer.renderJudgeStats(response.data);
                    } else if (type === 'supervisors') {
                        StatsRenderer.renderSupervisorStats(response.data);
                    }
                } else {
                    Utils.showError('Error loading statistics: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                Utils.error('Error loading statistics:', error);
                Utils.showError('Failed to load statistics. Please try again.');
                $modal.find('.stats-loading').hide();
            }
        });
    }

    // Initialize the application
    $(function() {
        Utils.log('Initializing dashboard widgets...');
        initEventHandlers();
        Utils.log('Dashboard widgets initialized');
    });
});