(function($) {
    'use strict';

$(document).ready(function() {
    initTabs();
    initAccordions();
    // Load data for initial active tab
    const activeTab = $('.dashboard-tabs li.active').data('tab');
    if (activeTab) {
        loadTabData(activeTab);
    }
});



/**
 * Initialize dashboard tabs
 */
function initTabs() {
    $('.dashboard-tabs li').on('click', function() {
        const tabId = $(this).data('tab');

        // Update active tab
        $('.dashboard-tabs li').removeClass('active');
        $(this).addClass('active');

        // Show active content
        $('.dashboard-tab-content').removeClass('active');
        $('#tab-' + tabId).addClass('active');

        // Load tab data via AJAX
        loadTabData(tabId);

        // Save active tab in sessionStorage
        if (typeof(Storage) !== "undefined") {
            sessionStorage.setItem('wevoiSupervisorActiveTab', tabId);
        }
    });

    // Initialize accordion (competition containers)
    initAccordion();

    // Check for previously selected tab
    if (typeof(Storage) !== "undefined") {
        const activeTab = sessionStorage.getItem('wevoiSupervisorActiveTab');
        if (activeTab) {
            $('.dashboard-tabs li[data-tab="' + activeTab + '"]').trigger('click');
        }
    }
}

/**
 * Initialize accordion functionality for competition containers
 */
function initAccordion() {
    // Delegate the click event to handle dynamically created elements
    $(document).on('click', '.competition-container h4', function() {
        const $container = $(this).closest('.competition-container');
        const $content = $container.find('.results-table');

        // Toggle the content visibility
        if ($content.is(':visible')) {
            $content.slideUp(200);
            $container.addClass('collapsed');
        } else {
            $content.slideDown(200);
            $container.removeClass('collapsed');
        }
    });

    // Add toggle indicator to competition headers
    $('.competition-container h4').append('<span class="toggle-indicator"></span>');

    // Add initial collapsed class to containers (optional - if you want them closed by default)
    // $('.competition-container').addClass('collapsed').find('.results-table').hide();
}


    /**
 * Initialize accordions
 */
function initAccordions() {
    // Initialize competition accordions
    $(document).on('click', '.competition-container h4', function() {
        $(this).toggleClass('active');
        $(this).next('.competition-content').toggleClass('active');
    });

    // Initialize student accordions
    $(document).on('click', '.accordion-header', function() {
        $(this).toggleClass('active');
        $(this).next('.accordion-content').toggleClass('active');
    });
}


    /**
     * Load tab data via AJAX
     */
    function loadTabData(tabId) {
        if (tabId === 'students') {
            loadStudents();
        } else if (tabId === 'results') {
            loadResults();
        } else if (tabId === 'certificates') {
            loadCertificates();
        } else if (tabId === 'my-forum') {
            // Forum registrations are already loaded via PHP, no AJAX needed
            // Just ensure the tab content is visible
            console.log('My Forum Registrations tab activated');
        }
    }

    /**
     * Load students data
     */
    function loadStudents() {
        // Check if we have data already loaded
if ($('#tab-students .student-group').length > 0) {
    return;
}

        // Show loading indicator
        const $container = $('#tab-students .students-list');
        if ($container.length === 0) return;

        $container.append('<div class="loading-indicator">Loading students data...</div>');

        // Determine which nonce and action to use (supervisor or parent)
        const nonce = typeof wevoiSupervisorNonce !== 'undefined' ? wevoiSupervisorNonce : (typeof wevoiParentNonce !== 'undefined' ? wevoiParentNonce : '');
        const action = typeof wevoiSupervisorNonce !== 'undefined' ? 'get_supervisor_students' : 'get_parent_students';

        // Make AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: action,
                nonce: nonce
            },
            success: function(response) {
                // Remove loading indicator
                $container.find('.loading-indicator').remove();

                if (response.success && response.data && response.data.length > 0) {
                    // Refresh the content
                    updateStudentsTable(response.data);
                }
            },
            error: function() {
                // Remove loading indicator and show error
                $container.find('.loading-indicator').remove();
                $container.append('<div class="error-message">Error loading students data. Please try again.</div>');
            }
        });
    }

    /**
     * Update students table with AJAX data
     */
function updateStudentsTable(students) {
    const $container = $('#tab-students .students-list');

    // Önce öğrencilerin katıldığı yarışmaları alalım
    // Bu bilgiyi AJAX ile almamız gerekecek

    // Determine which nonce and action to use (supervisor or parent)
    const nonce = typeof wevoiSupervisorNonce !== 'undefined' ? wevoiSupervisorNonce : (typeof wevoiParentNonce !== 'undefined' ? wevoiParentNonce : '');
    const action = typeof wevoiSupervisorNonce !== 'undefined' ? 'get_student_competitions' : 'get_parent_student_competitions';

    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: action,
            nonce: nonce,
            student_ids: students.map(student => student.student_id)
        },
        success: function(response) {
            if (response.success && response.data) {
                // Yarışmalara göre gruplama
                const studentsByCompetition = {};
                const studentMap = {};

                // Önce öğrenci haritası oluşturalım
                students.forEach(function(student) {
                    studentMap[student.student_id] = student;
                });

                // Şimdi yarışmalara göre gruplayalım
                response.data.forEach(function(registration) {
                    if (!studentsByCompetition[registration.competition_name]) {
                        studentsByCompetition[registration.competition_name] = {
                            code: registration.competition_code,
                            students: []
                        };
                    }

                    // Bu yarışmaya katılan ve henüz eklenmemiş öğrenciyi ekleyelim
                    const studentId = registration.user_id;
                    const student = studentMap[studentId];

                    if (student && !studentsByCompetition[registration.competition_name].students.some(s => s.student_id === studentId)) {
                        studentsByCompetition[registration.competition_name].students.push(student);
                    }
                });

                // Yarışmasız öğrenciler için bir grup
                const studentsWithoutCompetition = students.filter(student => {
                    return !Object.values(studentsByCompetition).some(comp =>
                        comp.students.some(s => s.student_id === student.student_id)
                    );
                });

                if (studentsWithoutCompetition.length > 0) {
                    studentsByCompetition['No Competition Participation'] = {
                        code: 'no-competition',
                        students: studentsWithoutCompetition
                    };
                }

                // Şimdi yarışma gruplarını gösterelim
                Object.keys(studentsByCompetition).sort().forEach(function(competitionName) {
                    const competition = studentsByCompetition[competitionName];
                    const competitionStudents = competition.students;

                    const $accordion = $(`
                        <div class="student-group">
                            <div class="accordion-header">
                                ${competitionName} (${competitionStudents.length} students)
                            </div>
                            <div class="accordion-content">
                                <table class="wevoi-table students-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Registration Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    `);

                    const $tbody = $accordion.find('tbody');

                    // Öğrencileri gruba ekleyelim
                    competitionStudents.forEach(function(student) {
                        const fullName = `${student.first_name || ''} ${student.last_name || ''}`.trim();

                        // Bu öğrencinin bu yarışmadaki durumunu bulalım
                        const registration = response.data.find(r =>
                            r.user_id == student.student_id &&
                            r.competition_name === competitionName
                        );

                        let status = '-';
                        let statusClass = '';

                        if (registration && registration.application_status) {
                            status = registration.application_status;
                            statusClass = `status-${status.toLowerCase()}`;
                        }

                        const row = `<tr>
                            <td>${fullName}</td>
                            <td><a href="mailto:${student.user_email}">${student.user_email}</a></td>
                            <td>${formatDate(student.user_registered)}</td>
                            <td>
                                <span class="status-badge ${statusClass}">${status}</span>
                            </td>
                        </tr>`;

                        $tbody.append(row);
                    });

                    $container.append($accordion);
                });
            } else {
                // Yarışma bilgisi olmadığında sadece öğrenci listesini gösterelim
                const $table = $('<table class="wevoi-table students-table"></table>');
                $table.append(`
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Registration Date</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                `);

                students.forEach(function(student) {
                    const fullName = `${student.first_name || ''} ${student.last_name || ''}`.trim();
                    const row = `<tr>
                        <td>${fullName}</td>
                        <td><a href="mailto:${student.user_email}">${student.user_email}</a></td>
                        <td>${formatDate(student.user_registered)}</td>
                    </tr>`;

                    $table.find('tbody').append(row);
                });

                $container.append($table);
            }
        },
        error: function() {
            // Hata durumunda basit liste gösterelim
            const $table = $('<table class="wevoi-table students-table"></table>');
            $table.append(`
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Registration Date</th>
                    </tr>
                </thead>
                <tbody></tbody>
            `);

            students.forEach(function(student) {
                const fullName = `${student.first_name || ''} ${student.last_name || ''}`.trim();
                const row = `<tr>
                    <td>${fullName}</td>
                    <td><a href="mailto:${student.user_email}">${student.user_email}</a></td>
                    <td>${formatDate(student.user_registered)}</td>
                </tr>`;

                $table.find('tbody').append(row);
            });

            $container.append($table);
        }
    });
}

/**
 * Load results data
 */
function loadResults() {
    // Debug: Add log message
    console.log('Loading results data...');

    // Check if we have data already loaded
    if ($('#tab-results .competition-container').length > 0) {
        return;
    }

    // Show loading indicator
    const $container = $('#tab-results');
    if ($container.length === 0) return;

    $container.append('<div class="loading-indicator">Loading competition results...</div>');

    // Get student IDs from hidden input (populated in dashboard.php)
    let studentIds = [];
    if ($('#student-ids').length > 0) {
        studentIds = $('#student-ids').val().split(',').filter(Boolean);
        console.log('Student IDs:', studentIds); // Debug: Log student IDs
    }

    // Determine which nonce and action to use (supervisor or parent)
    const nonce = typeof wevoiSupervisorNonce !== 'undefined' ? wevoiSupervisorNonce : (typeof wevoiParentNonce !== 'undefined' ? wevoiParentNonce : '');
    const action = typeof wevoiSupervisorNonce !== 'undefined' ? 'get_competition_results' : 'get_parent_competition_results';

    // Make AJAX request
    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: action,
            nonce: nonce,
            student_ids: studentIds
        },
        success: function(response) {
            // Debug: Log the response
            console.log('AJAX response:', response);

            // Remove loading indicator
            $container.find('.loading-indicator').remove();

            if (response.success && response.data && response.data.length > 0) {
                // Check if we have any items with claimed_summit_coupon_code
                const hasCoupons = response.data.some(item => item.claimed_summit_coupon_code);
                console.log('Has coupon codes:', hasCoupons);

                // Group results by competition
                const resultsByCompetition = groupResultsByCompetition(response.data);

                // Update competition results container
                updateCompetitionResults(resultsByCompetition);
            } else {
                // No results
                $container.append('<div class="no-items-message"><p>No competition results found for your students.</p></div>');
            }
        },
        error: function(xhr, status, error) {
            // Debug: Log any errors
            console.error('AJAX error:', error);
            console.log('Status:', status);
            console.log('XHR:', xhr);

            // Remove loading indicator and show error
            $container.find('.loading-indicator').remove();
            $container.append('<div class="error-message">Error loading competition results. Please try again.</div>');
        }
    });
}

    /**
     * Group results by competition
     */
    function groupResultsByCompetition(results) {
        const grouped = {};

        results.forEach(function(reg) {
            if (!grouped[reg.competition_code]) {
                grouped[reg.competition_code] = {
                    name: reg.competition_name,
                    registrations: []
                };
            }

            grouped[reg.competition_code].registrations.push(reg);
        });

        return grouped;
    }

/**
 * Update competition results container
 */
function updateCompetitionResults(resultsByCompetition) {
    const $container = $('#tab-results');

    $container.append('<div class="competition-results"><h3>Competition Results</h3></div>');
    const $results = $container.find('.competition-results');

    // Add each competition
    for (const code in resultsByCompetition) {
        const competition = resultsByCompetition[code];

        const $competitionContainer = $('<div class="competition-container"></div>');
        $competitionContainer.append(`<h4>${competition.name}</h4>`);

        const $table = $('<table class="wevoi-table results-table"></table>');
        $table.append(`
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Status</th>
                    <th>Video</th>
                    <th>Result</th>
                    <th>Certificate</th>
                </tr>
            </thead>
            <tbody></tbody>
        `);

        // Add each registration
        competition.registrations.forEach(function(reg) {
            const status = reg.application_status ? reg.application_status : '-';
            const statusClass = reg.application_status ? `status-${status.toLowerCase()}` : '';

            const $row = $('<tr></tr>');

            // Student info
            $row.append(`
                <td>
                    <div>${reg.student_name}</div>
                    <div class="student-email"><a href="mailto:${reg.student_email}">${reg.student_email}</a></div>
                </td>
            `);

            // Status
            $row.append(`
                <td>
                    <span class="status-badge ${statusClass}">${status}</span>
                </td>
            `);

            // Video
            if (reg.youtube_link) {
                $row.append(`
                    <td>
                        <a href="${reg.youtube_link}" target="_blank" class="youtube-link">Watch Video</a>
                    </td>
                `);
            } else {
                $row.append('<td>-</td>');
            }

            // Result
            let resultHtml = '<td>';

            if (reg.student_medal) {
                resultHtml += `
                    <span class="medal-badge medal-${reg.student_medal.toLowerCase()}">
                        ${reg.student_medal}
                    </span>
                `;

                if (reg.student_point || reg.student_rank) {
                    resultHtml += '<div class="result-details">';

                    if (reg.student_point) {
                        resultHtml += `<div>Points: ${parseFloat(reg.student_point).toFixed(2)}</div>`;
                    }

                    if (reg.student_rank) {
                        resultHtml += `<div>Rank: #${reg.student_rank}</div>`;
                    }

                    resultHtml += '</div>';
                }
            }

            // Add Summit Coupon Code if available
            if (reg.claimed_summit_coupon_code) {
                resultHtml += `<div class="coupon-code">Summit Coupon: <strong>${reg.claimed_summit_coupon_code}</strong></div>`;
            }

            resultHtml += '</td>';

            // If no medal and no coupon, just show a dash
            if (!reg.student_medal && !reg.claimed_summit_coupon_code) {
                resultHtml = '<td>-</td>';
            }

            $row.append(resultHtml);

            // Certificate column
            let certHtml = '<td>';
            if (reg.certificate_link) {
                certHtml += `<a href="${reg.certificate_link}" target="_blank" class="certificate-link">View Certificate</a>`;
            } else {
                certHtml += '-';
            }
            certHtml += '</td>';
            $row.append(certHtml);

            $table.find('tbody').append($row);
        });

        $competitionContainer.append($table);
        $results.append($competitionContainer);
    }
}

    /**
     * Load certificates data
     */
    function loadCertificates() {
        // Check if we have data already loaded
        if ($('#tab-certificates .certificates-table tbody tr').length > 0) {
            return;
        }

        // Show loading indicator
        const $container = $('#tab-certificates');
        if ($container.length === 0) return;

        $container.append('<div class="loading-indicator">Loading certificates data...</div>');

        // Determine which nonce and action to use (supervisor or parent)
        const nonce = typeof wevoiSupervisorNonce !== 'undefined' ? wevoiSupervisorNonce : (typeof wevoiParentNonce !== 'undefined' ? wevoiParentNonce : '');
        const action = typeof wevoiSupervisorNonce !== 'undefined' ? 'get_supervisor_certificates' : 'get_parent_certificates';

        // Make AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: action,
                nonce: nonce
            },
            success: function(response) {
                // Remove loading indicator
                $container.find('.loading-indicator').remove();

                if (response.success && response.data && response.data.length > 0) {
                    // Refresh the content
                    updateCertificatesTable(response.data);
                } else {
                    // No certificates
                    $container.append('<div class="no-items-message"><p>No certificates found.</p></div>');
                }
            },
            error: function() {
                // Remove loading indicator and show error
                $container.find('.loading-indicator').remove();
                $container.append('<div class="error-message">Error loading certificates data. Please try again.</div>');
            }
        });
    }

    /**
     * Update certificates table with AJAX data
     */
    function updateCertificatesTable(certificates) {
        const $container = $('#tab-certificates');

        $container.append('<div class="certificates-list"><h3>My Certificates</h3></div>');
        const $list = $container.find('.certificates-list');

        const $table = $('<table class="wevoi-table certificates-table"></table>');
        $table.append(`
            <thead>
                <tr>
                    <th>Competition</th>
                    <th>Issue Date</th>
                    <th>Certificate</th>
                </tr>
            </thead>
            <tbody></tbody>
        `);

        certificates.forEach(function(cert) {
            const competitionName = cert.competition_name || '-';
            const issueDate = cert.issue_date ? formatDate(cert.issue_date) : '-';

            const $row = $('<tr></tr>');

            // Competition name
            $row.append(`<td>${competitionName}</td>`);

            // Issue date
            $row.append(`<td>${issueDate}</td>`);

            // Certificate link
            const certLink = cert.certificate_link || cert.supervisor_certficates_folder || '';

            if (certLink) {
                $row.append(`
                    <td>
                        <a href="${certLink}" target="_blank" class="certificate-link">View Certificate</a>
                    </td>
                `);
            } else {
                $row.append('<td>-</td>');
            }

            $table.find('tbody').append($row);
        });

        $list.append($table);
    }

    /**
     * Format date
     */
    function formatDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString();
    }

})(jQuery);
