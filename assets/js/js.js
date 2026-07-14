jQuery(document).ready(function ($) {
    // Function to handle form submissions
    function handleFormSubmission(e, formType) {
        e.preventDefault(); // Prevent default form submission

        // Base class prefix for this form type; the actual class on the form/container
        // may carry a trailing location number, e.g. 'form_submit_request_3-...'
        const basePrefix = formType === 'rd' ? 'rd_form_request' : 'form_submit_request';
        const apiName = formType === 'rd' ? 'RepairDesk' : 'PBX';

        // Get the closest element carrying a class that starts with the base prefix
        const container = $(this).closest(`[class*='${basePrefix}']`);
        const formClass = container.attr("class") || '';

        // Work out the exact prefix used (with or without a location number) and the
        // form class type to send to the backend, e.g. 'form_submit_request' or 'form_submit_request_3'
        const prefixMatch = formClass.match(new RegExp(`${basePrefix}(_\\d+)?-`));
        const classPrefix = prefixMatch ? prefixMatch[0] : `${basePrefix}-`;
        const formClassType = prefixMatch ? prefixMatch[0].slice(0, -1) : basePrefix;

        // Extract the form identifier (number or text) using regex
        const match = formClass.match(new RegExp(`${classPrefix}([\\w_]+)`));
        let formIdentifier = match ? match[1] : formType === 'rd' ? 'Ringo Media' : '26';

        // Format the form identifier for RepairDesk forms
        if (formType === 'rd') {
            formIdentifier = formatText(formIdentifier);
        }

        // Log which form class was triggered (for debugging)
        console.log(`Form triggered: ${formClassType}`);

        const formData = {};
        let isBotDetected = false; // Flag to track bot detection
        $(this).find('input, textarea, select').each(function () {
            // Check for honeypot fields
            if ($(this).attr('name') === "form_fields[honeypot_field]" || $(this).attr('name') === "form_fields[honeypot]") {
                if ($(this).val().trim() !== "") { // Check if the honeypot has a value (bots often fill it)
                    alert("Our systems detected unusual activity. If you’re human, please avoid hidden fields and try again!");
                    isBotDetected = true;
                    window.location.reload(); // Refresh the page
                    return false; // Exit the `.each()` loop early
                }
            }

            // Skip hidden inputs
            if ($(this).attr('type') === 'hidden') {
                return true; // Continue to next iteration
            }

            const name = $(this).attr('name');
            const value = $(this).val();

            // Skip if the name is 'g-recaptcha-response'
            if (name === 'g-recaptcha-response') {
                return true; // Continue to next iteration
            }

            if (name && !isBotDetected) { // Only proceed if no bot was detected
                formData[name] = value;
            }
        });

        // Stop form submission if bot is detected
        if (isBotDetected) {
            return false; // Prevents the form from submitting
        }

        // Helper function to clean and format keys
        const formatKey = (key) => {
            let formattedKey = key.replace(/^[^\[]*\[/, '');
            // Remove the closing ']' if it exists
            formattedKey = formattedKey.replace(/\]$/, '');
            // Replace underscores and hyphens with spaces
            formattedKey = formattedKey.replace(/[_-]/g, ' ');
            // Trim any leading or trailing spaces
            formattedKey = formattedKey.trim();
            // Capitalize each word
            return formattedKey.replace(/\b\w/g, (char) => char.toUpperCase());
        };

        // Define the specific keys you want to extract
        const specificKeys = {
            'form_fields[name]': 'name',
            'form_fields[email]': 'email',
            'form_fields[phone]': 'phone',
            'form_fields[message]': 'message'
        };

        // Extract specific fields for API
        const extractedFields = {};
        for (const key in specificKeys) {
            if (formData[key]) {
                extractedFields[specificKeys[key]] = formData[key];
            } else {
                extractedFields[specificKeys[key]] = ''; // Default to empty string if not found
            }
        }

        const { name, email, phone, message } = extractedFields;

        // Handle additional fields
        let additionalMessage = '';
        for (const key in formData) {
            if (!specificKeys[key]) { // If the key is not in the specificKeys map
                const formattedKey = formatKey(key); // Format the key
                additionalMessage += `\n${formattedKey}: ${formData[key]}`;
            }
        }

        // Combine the main message and additional fields
        const finalMessage = message + additionalMessage;    

        // Send data via AJAX
        $.ajax({
            url: ajaxurl, // WordPress AJAX URL
            type: 'POST',
            data: {
                action: 'send_form_data_to_api',
                name: name,
                email: email,
                phone: phone,
                message: finalMessage,
                formNumber: formIdentifier,
                api: apiName,
                formClassType: formClassType // Pass the form class type (location) to the backend
            },
            success: function (response) {
                console.log('Data sent successfully:', response);
                if (!response.success) {
                    alert('Error: ' + response.data.message); // Show error message
                }
            },
            error: function (error) {
                console.error('Error sending data:', error);
                // alert('Failed to send data. Please try again.'); // Show error message
            },
        });
    }

    // Function to format text (e.g., replace underscores with spaces and capitalize words)
    function formatText(text) {
        return text
            .replace(/_/g, ' ') // Replace underscores with spaces
            .replace(/\b\w/g, (char) => char.toUpperCase()); // Capitalize each word
    }

    // Attach event listeners for both form types, across any number of locations
    // (matches 'form_submit_request-...', 'form_submit_request_2-...', 'form_submit_request_3-...', etc.)
    $(document).on('submit', "div[class*='form_submit_request'] form, form[class*='form_submit_request']", function (e) {
        handleFormSubmission.call(this, e, 'pbx');
    });

    $(document).on('submit', "div[class*='rd_form_request'] form, form[class*='rd_form_request']", function (e) {
        handleFormSubmission.call(this, e, 'rd');
    });

    $(document).on('input', "#form-field-phone", function (e) {
        var number = this.value.replace(/[^0-9]/g, '');
        // Format as (123) 456-7890
        if (number.length > 3 && number.length <= 6) {
            number = number.replace(/(\d{3})/, '($1) ');
        } else if (number.length > 6) {
            number = number.replace(/(\d{3})(\d{3})/, '($1) $2-');
        }
        this.value = number.substring(0, 14);
    });
});
