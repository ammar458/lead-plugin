jQuery(document).ready(function($) {
    $(document).on('click', "#show_referrals_btn", function (e) {          
        var apiKey = $('.location-block').first().find('.pbx-field input').val().trim();        
        if (apiKey === '') {
            alert('Please enter a valid API key.');
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'fetch_referrals',
                api_key: apiKey
            },
            success: function(response) {
                console.log(response);
                if (response.success) {
                    location.reload(); // Refresh the page on success
                } else {
                    alert('No referrals found.');
                }
            },
            error: function(xhr, status, error) {
                alert('Failed to fetch referrals. Please check your API key and try again.');
            }
        });
    });

    $(document).on('click', ".add-btn", function (e) {         

         $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'fetch_referrals_opt',                
            },
            success: function(response) {             
                 $("#dropdownContainer").append(`
                    <div class="referral-container">
                    `+response+`
                    <button type="button" class="remove-btn">×</button>
                        <span class="class-display"></span>
                    </div>
                `);
            },
            error: function(xhr, status, error) {
                alert('Failed to fetch referrals. Please check your API key and try again.');
            }
        });
    });
});
