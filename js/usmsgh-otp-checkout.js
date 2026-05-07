/**
 * USMS-GH OTP Checkout JavaScript
 * Handles OTP sending, verification, and resending for WooCommerce checkout
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Check if we're on the checkout page
        if (!$('form.checkout').length && !$('#usmsgh-checkout-otp-section').length) {
            return;
        }

        var sendOtpBtn = $('#usmsgh_checkout_send_otp');
        var verifyOtpBtn = $('#usmsgh_checkout_verify_otp');
        var resendOtpBtn = $('#usmsgh_checkout_resend_otp');
        var phoneInput = $('#usmsgh_checkout_phone');
        var billingPhoneInput = $('#billing_phone');
        var otpInput = $('#usmsgh_checkout_otp_code');
        var otpVerifiedInput = $('#usmsgh_checkout_otp_verified');
        var otpVerifyRow = $('.usmsgh-otp-verify-row');
        var otpStatus = $('.usmsgh-otp-status');
        var otpMessage = $('.usmsgh-otp-message');
        var countdown = 0;
        var countdownInterval;

        // Sync with billing phone if available
        if (billingPhoneInput.length) {
            billingPhoneInput.on('blur change', function() {
                if (!phoneInput.val()) {
                    phoneInput.val($(this).val());
                }
            });

            // Pre-fill with billing phone if available
            if (billingPhoneInput.val()) {
                phoneInput.val(billingPhoneInput.val());
            }
        }

        // Send OTP function
        function sendOtp(phone, context, callback) {
            $.ajax({
                url: usmsgh_otp_checkout_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'usmsgh_send_otp',
                    nonce: usmsgh_otp_checkout_params.nonce,
                    phone: phone,
                    context: context || 'checkout'
                },
                beforeSend: function() {
                    if (callback && callback.beforeSend) {
                        callback.beforeSend();
                    }
                },
                success: function(response) {
                    if (response.success) {
                        if (callback && callback.success) {
                            callback.success(response.data);
                        }
                    } else {
                        if (callback && callback.error) {
                            callback.error(response.data);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    if (callback && callback.error) {
                        callback.error({
                            message: 'Network error. Please try again.'
                        });
                    }
                }
            });
        }

        // Verify OTP function
        function verifyOtp(phone, otp, context, callback) {
            $.ajax({
                url: usmsgh_otp_checkout_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'usmsgh_verify_otp',
                    nonce: usmsgh_otp_checkout_params.nonce,
                    phone: phone,
                    otp: otp,
                    context: context || 'checkout'
                },
                beforeSend: function() {
                    if (callback && callback.beforeSend) {
                        callback.beforeSend();
                    }
                },
                success: function(response) {
                    if (response.success) {
                        if (callback && callback.success) {
                            callback.success(response.data);
                        }
                    } else {
                        if (callback && callback.error) {
                            callback.error(response.data);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    if (callback && callback.error) {
                        callback.error({
                            message: 'Network error. Please try again.'
                        });
                    }
                }
            });
        }

        // Resend OTP function
        function resendOtp(phone, context, callback) {
            $.ajax({
                url: usmsgh_otp_checkout_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'usmsgh_resend_otp',
                    nonce: usmsgh_otp_checkout_params.nonce,
                    phone: phone,
                    context: context || 'checkout'
                },
                beforeSend: function() {
                    if (callback && callback.beforeSend) {
                        callback.beforeSend();
                    }
                },
                success: function(response) {
                    if (response.success) {
                        if (callback && callback.success) {
                            callback.success(response.data);
                        }
                    } else {
                        if (callback && callback.error) {
                            callback.error(response.data);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    if (callback && callback.error) {
                        callback.error({
                            message: 'Network error. Please try again.'
                        });
                    }
                }
            });
        }

        // Start countdown for resend
        function startCountdown(button, seconds) {
            countdown = seconds || 60;
            button.prop('disabled', true);

            countdownInterval = setInterval(function() {
                countdown--;
                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                    button.prop('disabled', false);
                    button.text(usmsgh_otp_checkout_params.strings.resend);
                } else {
                    button.text(usmsgh_otp_checkout_params.strings.resend_in.replace('{seconds}', countdown));
                }
            }, 1000);
        }

        // Stop countdown
        function stopCountdown() {
            if (countdownInterval) {
                clearInterval(countdownInterval);
            }
        }

        // Show status message
        function showStatus(element, message, type) {
            element.removeClass('success error info')
                   .addClass(type)
                   .text(message)
                   .show();
        }

        // Show message
        function showMessage(element, message, type) {
            element.removeClass('success error')
                   .addClass(type)
                   .html(message)
                   .show();

            // Auto-hide after 5 seconds for success messages
            if (type === 'success') {
                setTimeout(function() {
                    element.fadeOut();
                }, 5000);
            }
        }

        // Send OTP button click
        sendOtpBtn.on('click', function(e) {
            e.preventDefault();

            var phone = phoneInput.val().trim();

            // Also update billing phone
            if (billingPhoneInput.length && billingPhoneInput.val() !== phone) {
                billingPhoneInput.val(phone).trigger('change');
            }

            if (!phone) {
                showMessage(otpMessage, usmsgh_otp_checkout_params.strings.enter_phone, 'error');
                phoneInput.focus();
                return;
            }

            sendOtp(phone, 'checkout', {
                beforeSend: function() {
                    sendOtpBtn.prop('disabled', true)
                              .text(usmsgh_otp_checkout_params.strings.sending);
                    otpMessage.hide();
                },
                success: function(data) {
                    sendOtpBtn.prop('disabled', false)
                              .text('<?php echo esc_js(__('Send OTP', 'usmsgh-wc-sms-notification')); ?>');
                    showMessage(otpMessage, usmsgh_otp_checkout_params.strings.otp_sent, 'success');
                    otpVerifyRow.slideDown();
                    otpInput.focus();
                    startCountdown(resendOtpBtn, 60);
                },
                error: function(data) {
                    sendOtpBtn.prop('disabled', false)
                              .text('<?php echo esc_js(__('Send OTP', 'usmsgh-wc-sms-notification')); ?>');
                    showMessage(otpMessage, data.message || 'Failed to send OTP', 'error');
                }
            });
        });

        // Verify OTP button click
        verifyOtpBtn.on('click', function(e) {
            e.preventDefault();

            var phone = phoneInput.val().trim();
            var otp = otpInput.val().trim();

            if (!otp) {
                showMessage(otpMessage, usmsgh_otp_checkout_params.strings.invalid_otp, 'error');
                otpInput.focus();
                return;
            }

            verifyOtp(phone, otp, 'checkout', {
                beforeSend: function() {
                    verifyOtpBtn.prop('disabled', true)
                                .text(usmsgh_otp_checkout_params.strings.verifying);
                    otpMessage.hide();
                },
                success: function(data) {
                    verifyOtpBtn.prop('disabled', false)
                                .text('<?php echo esc_js(__('Verify OTP', 'usmsgh-wc-sms-notification')); ?>');
                    showMessage(otpMessage, usmsgh_otp_checkout_params.strings.otp_verified, 'success');
                    otpVerifiedInput.val('1');

                    // Update billing phone if not already set
                    if (billingPhoneInput.length && !billingPhoneInput.val()) {
                        billingPhoneInput.val(phone).trigger('change');
                    }

                    // Visual feedback
                    $('#usmsgh-checkout-otp-section').addClass('usmsgh-otp-verified');

                    // Hide buttons
                    verifyOtpBtn.hide();
                    resendOtpBtn.hide();
                    otpInput.prop('readonly', true);

                    // Remove required attribute from phone input since it's now verified
                    phoneInput.prop('readonly', true);
                    sendOtpBtn.hide();
                },
                error: function(data) {
                    verifyOtpBtn.prop('disabled', false)
                                .text('<?php echo esc_js(__('Verify OTP', 'usmsgh-wc-sms-notification')); ?>');
                    showMessage(otpMessage, data.message || usmsgh_otp_checkout_params.strings.invalid_otp, 'error');
                    otpInput.val('').focus();

                    // Add shake animation for error
                    otpVerifyRow.addClass('usmsgh-otp-error-shake');
                    setTimeout(function() {
                        otpVerifyRow.removeClass('usmsgh-otp-error-shake');
                    }, 500);
                }
            });
        });

        // Resend button click
        resendOtpBtn.on('click', function(e) {
            e.preventDefault();

            var phone = phoneInput.val().trim();

            if (!phone) {
                showMessage(otpMessage, usmsgh_otp_checkout_params.strings.enter_phone, 'error');
                return;
            }

            resendOtp(phone, 'checkout', {
                beforeSend: function() {
                    stopCountdown();
                },
                success: function(data) {
                    showMessage(otpMessage, usmsgh_otp_checkout_params.strings.otp_sent, 'success');
                    otpInput.val('').focus();
                    startCountdown(resendOtpBtn, 60);
                },
                error: function(data) {
                    showMessage(otpMessage, data.message || 'Failed to resend OTP', 'error');
                    if (data.wait_time) {
                        startCountdown(resendOtpBtn, data.wait_time);
                    }
                }
            });
        });

        // Allow Enter key to submit OTP
        otpInput.on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                verifyOtpBtn.click();
            }
        });

        // Validate before checkout submission
        $('form.checkout').on('checkout_place_order', function() {
            var otpVerified = otpVerifiedInput.val();

            if (otpVerified !== '1') {
                showMessage(otpMessage, usmsgh_otp_checkout_params.strings.required, 'error');
                
                // Scroll to OTP section
                $('html, body').animate({
                    scrollTop: $('#usmsgh-checkout-otp-section').offset().top - 100
                }, 500);

                // Focus phone input
                if (!phoneInput.val()) {
                    phoneInput.focus();
                } else {
                    otpInput.focus();
                }

                return false;
            }

            return true;
        });

        // Handle phone input formatting
        phoneInput.on('input', function() {
            var value = $(this).val();
            // Remove non-numeric characters
            value = value.replace(/[^0-9+\-\s]/g, '');
            $(this).val(value);
        });

        // When billing phone changes, update our phone field
        $(document).on('change', '#billing_phone', function() {
            var billingPhone = $(this).val();
            if (billingPhone && !phoneInput.val()) {
                phoneInput.val(billingPhone);
            }
        });

        // Auto-focus OTP input after 5 characters in phone
        phoneInput.on('input', function() {
            var value = $(this).val().replace(/\D/g, '');
            if (value.length >= 10) {
                // Only auto-focus if we're not already verified
                if (otpVerifiedInput.val() !== '1') {
                    sendOtpBtn.focus();
                }
            }
        });
    });

})(jQuery);
