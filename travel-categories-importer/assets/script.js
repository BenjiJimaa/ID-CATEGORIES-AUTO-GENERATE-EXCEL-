jQuery(document).ready(function($) {
    $('#process_excel').on('click', function() {
        var file_input = $('#excel_file')[0];
        
        if (!file_input.files.length) {
            alert('Please select an Excel file first');
            return;
        }
        
        var form_data = new FormData();
        form_data.append('action', 'process_excel_file');
        form_data.append('nonce', tci_ajax.nonce);
        form_data.append('excel_file', file_input.files[0]);
        
        // Show processing section
        $('#processing_results').show();
        $('#download_section').hide();
        
        // AJAX request
        $.ajax({
            url: tci_ajax.ajax_url,
            type: 'POST',
            data: form_data,
            processData: false,
            contentType: false,
            xhr: function() {
                var xhr = new XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        var percent = (e.loaded / e.total) * 50; // First 50% for upload
                        updateProgress(percent);
                    }
                }, false);
                
                xhr.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        var percent = 50 + (e.loaded / e.total) * 50; // Second 50% for processing
                        updateProgress(percent);
                    }
                }, false);
                
                return xhr;
            },
            success: function(response) {
                if (response.success) {
                    showResults(response.data);
                } else {
                    showError(response.data);
                }
            },
            error: function() {
                showError('Error processing file. Please try again.');
            }
        });
    });
    
    function updateProgress(percent) {
        $('.progress-fill').css('width', percent + '%');
        $('.progress-text').text(Math.round(percent) + '%');
    }
    
    function showResults(data) {
        updateProgress(100);
        
        var html = '<div class="results-summary">';
        html += '<h3>✅ Processing Complete!</h3>';
        html += '<p><strong>Routes Processed:</strong> ' + data.routes_processed + '</p>';
        html += '<p><strong>New Categories Created:</strong> ' + data.categories_created + '</p>';
        html += '<p><strong>Existing Categories:</strong> ' + data.categories_existing + '</p>';
        html += '</div>';
        
        $('#results_content').html(html);
        
        // Show download section
        $('#download_section').show();
        $('#download_links').html(
            '<p>Download processed file with category IDs:</p>' +
            '<a href="' + data.output_file + '" class="download-link" download>' +
            '📥 Download Excel File with Category IDs' +
            '</a>'
        );
    }
    
    function showError(message) {
        $('#results_content').html(
            '<div class="error"><strong>Error:</strong> ' + message + '</div>'
        );
    }
});