jQuery(document).ready(function($) {
    'use strict';
    
    // View registration modal
    $('.ae-view-registration').on('click', function(e) {
        e.preventDefault();
        
        var registrationId = $(this).data('id');
        var modal = $('#ae-registration-modal');
        var modalBody = modal.find('.ae-modal-body');
        
        modal.show();
        modalBody.html('<div class="ae-loading">Loading...</div>');
        
        $.ajax({
            url: ae_registrations.ajax_url,
            type: 'POST',
            data: {
                action: 'ae_get_registration_details',
                registration_id: registrationId,
                nonce: ae_registrations.nonce
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    var html = '';
                    
                    html += '<div class="ae-registration-detail">';
                    html += '<label>Name:</label> ' + data.name;
                    html += '</div>';
                    
                    html += '<div class="ae-registration-detail">';
                    html += '<label>Email:</label> <a href="mailto:' + data.email + '">' + data.email + '</a>';
                    html += '</div>';
                    
                    if (data.phone) {
                        html += '<div class="ae-registration-detail">';
                        html += '<label>Phone:</label> ' + data.phone;
                        html += '</div>';
                    }
                    
                    html += '<div class="ae-registration-detail">';
                    html += '<label>Event:</label> ' + data.event_title;
                    html += '</div>';
                    
                    html += '<div class="ae-registration-detail">';
                    html += '<label>Status:</label> <strong>' + data.status + '</strong>';
                    html += '</div>';
                    
                    html += '<div class="ae-registration-detail">';
                    html += '<label>Registration Date:</label> ' + data.registration_date;
                    html += '</div>';
                    
                    if (data.event_date) {
                        html += '<div class="ae-registration-detail">';
                        html += '<label>Event Date:</label> ' + data.event_date;
                        html += '</div>';
                    }
                    
                    if (data.event_location) {
                        html += '<div class="ae-registration-detail">';
                        html += '<label>Event Location:</label> ' + data.event_location;
                        html += '</div>';
                    }
                    
                    if (data.meta && Object.keys(data.meta).length > 0) {
                        html += '<hr>';
                        html += '<h4>Additional Information</h4>';
                        
                        if (data.meta.ip_address) {
                            html += '<div class="ae-registration-detail">';
                            html += '<label>IP Address:</label> ' + data.meta.ip_address;
                            html += '</div>';
                        }
                        
                        if (data.meta.registered_by && data.meta.registered_by > 0) {
                            html += '<div class="ae-registration-detail">';
                            html += '<label>Registered By:</label> User ID ' + data.meta.registered_by;
                            html += '</div>';
                        }
                    }
                    
                    modalBody.html(html);
                } else {
                    modalBody.html('<p>Error loading registration details.</p>');
                }
            },
            error: function() {
                modalBody.html('<p>Error loading registration details.</p>');
            }
        });
    });
    
    // Close modal
    $('.ae-modal-close, #ae-registration-modal').on('click', function(e) {
        if (e.target === this) {
            $('#ae-registration-modal').hide();
        }
    });
    
    // Filter by event
    $('#filter-by-event').on('change', function() {
        if ($(this).val()) {
            window.location.href = ae_registrations.admin_url + 'admin.php?page=ae-registrations&event_id=' + $(this).val();
        } else {
            window.location.href = ae_registrations.admin_url + 'admin.php?page=ae-registrations';
        }
    });
    
    // Bulk action confirmation
    $('#doaction, #doaction2').on('click', function(e) {
        var action = $(this).prev('select').val();
        
        if (action === 'delete') {
            if (!confirm('Are you sure you want to permanently delete the selected registrations?')) {
                e.preventDefault();
                return false;
            }
        } else if (action === 'cancel') {
            if (!confirm('Are you sure you want to cancel the selected registrations?')) {
                e.preventDefault();
                return false;
            }
        }
    });
    
    // Add search parameter preservation
    $('form#posts-filter').on('submit', function() {
        var searchInput = $(this).find('input[name="s"]');
        if (searchInput.val() === '') {
            searchInput.remove();
        }
    });
});