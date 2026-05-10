/**
 * USMS-GH OTP Login JavaScript
 * Handles OTP sending, verification, and resending for WooCommerce login
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Check if we're on the login page
        if (!$('form.woocommerce-form-login').length) {
            return;
        }

        var sendOtpBtn = $('#usmsgh_send_otp_btn');
        var verifyOtpBtn = $('#usmsgh_verify_otp_btn');
        var resendOtpBtn = $('#usmsgh_resend_otp_btn');
        var phoneInput = $('#usmsgh_phone');
        var otpInput = $('#usmsgh_otp_code');
        var otpVerifiedInput = $('#usmsgh_otp_verified');
        var otpVerifyField = $('.usmsgh-otp-verify-field');
        var otpStatus = $('.usmsgh-otp-status');
        var countdown = 0;
        var countdownInterval;

        // Modal elements
        var modal = $('#usmsgh-otp-modal');
        var modalPhone = $('#usmsgh_modal_phone');
        var modalSendBtn = $('#usmsgh_modal_send_otp');
        var modalOtp = $('#usmsgh_modal_otp');
        var modalVerifyBtn = $('#usmsgh_modal_verify_otp');
        var modalResendBtn = $('#usmsgh_modal_resend_otp');
        var modalClose = $('.usmsgh-otp-close');
        var modalMessage = $('.usmsgh-otp-message');
        var modalVerifySection = $('.usmsgh-otp-verify-section');

        // Send OTP function
        function sendOtp(phone, context, callback) {
            $.ajax({
                url: usmsgh_otp_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'usmsgh_send_otp',
                    nonce: usmsgh_otp_params.nonce,
                    phone: phone,
                    context: context || 'login'
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
                url: usmsgh_otp_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'usmsgh_verify_otp',
                    nonce: usmsgh_otp_params.nonce,
                    phone: phone,
                    otp: otp,
                    context: context || 'login'
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
                url: usmsgh_otp_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'usmsgh_resend_otp',
                    nonce: usmsgh_otp_params.nonce,
                    phone: phone,
                    context: context || 'login'
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

        // Start countdown for resend button, optionally lock a send button too
        function startCountdown(button, seconds, lockBtn) {
            countdown = seconds || 60;
            button.prop('disabled', true);
            if (lockBtn) lockBtn.prop('disabled', true).text('Send OTP');

            countdownInterval = setInterval(function() {
                countdown--;
                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                    button.prop('disabled', false);
                    button.text(usmsgh_otp_params.strings.resend);
                    if (lockBtn) lockBtn.prop('disabled', false);
                } else {
                    button.text(usmsgh_otp_params.strings.resend_in.replace('{seconds}', countdown));
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

        // Clear status
        function clearStatus(element) {
            element.removeClass('success error info')
                   .text('')
                   .hide();
        }

        // Form OTP send button click
        sendOtpBtn.on('click', function(e) {
            e.preventDefault();

            var phone = phoneInput.val().trim();

            if (!phone) {
                showStatus(otpStatus, usmsgh_otp_params.strings.enter_phone, 'error');
                phoneInput.focus();
                return;
            }

            sendOtp(phone, 'login', {
                beforeSend: function() {
                    sendOtpBtn.prop('disabled', true)
                              .text(usmsgh_otp_params.strings.sending + '...');
                    clearStatus(otpStatus);
                },
                success: function(data) {
                    sendOtpBtn.prop('disabled', false)
                              .text('Send OTP');
                    showStatus(otpStatus, usmsgh_otp_params.strings.otp_sent, 'success');
                    otpVerifyField.slideDown();
                    otpInput.focus();
                    startCountdown(resendOtpBtn, 60);
                },
                error: function(data) {
                    sendOtpBtn.prop('disabled', false)
                              .text('Send OTP');
                    showStatus(otpStatus, data.message || 'Failed to send OTP', 'error');
                }
            });
        });

        // Form verify button click
        verifyOtpBtn.on('click', function(e) {
            e.preventDefault();

            var phone = phoneInput.val().trim();
            var otp = otpInput.val().trim();

            if (!otp) {
                showStatus(otpStatus, usmsgh_otp_params.strings.invalid_otp, 'error');
                otpInput.focus();
                return;
            }

            verifyOtp(phone, otp, 'login', {
                beforeSend: function() {
                    verifyOtpBtn.prop('disabled', true)
                                .text(usmsgh_otp_params.strings.verifying + '...');
                    clearStatus(otpStatus);
                },
                success: function(data) {
                    verifyOtpBtn.prop('disabled', false)
                                .text('Verify OTP');
                    showStatus(otpStatus, usmsgh_otp_params.strings.otp_verified, 'success');
                    otpVerifiedInput.val('1');

                    // Hide verify/resend buttons
                    verifyOtpBtn.hide();
                    resendOtpBtn.hide();
                    otpInput.prop('readonly', true);

                    // Mark as verified
                    otpVerifyField.addClass('usmsgh-otp-verified');
                },
                error: function(data) {
                    verifyOtpBtn.prop('disabled', false)
                                .text('Verify OTP');
                    showStatus(otpStatus, data.message || usmsgh_otp_params.strings.invalid_otp, 'error');
                    otpInput.val('').focus();
                }
            });
        });

        // Resend button click
        resendOtpBtn.on('click', function(e) {
            e.preventDefault();

            var phone = phoneInput.val().trim();

            if (!phone) {
                showStatus(otpStatus, usmsgh_otp_params.strings.enter_phone, 'error');
                return;
            }

            resendOtp(phone, 'login', {
                beforeSend: function() {
                    stopCountdown();
                },
                success: function(data) {
                    showStatus(otpStatus, usmsgh_otp_params.strings.otp_sent, 'success');
                    otpInput.val('').focus();
                    startCountdown(resendOtpBtn, 60);
                },
                error: function(data) {
                    showStatus(otpStatus, data.message || 'Failed to resend OTP', 'error');
                    if (data.wait_time) {
                        startCountdown(resendOtpBtn, data.wait_time);
                    }
                }
            });
        });

        // Modal functionality
        if (modal.length) {
            // Close modal
            modalClose.on('click', function() {
                modal.hide();
            });

            // Close on outside click
            $(window).on('click', function(e) {
                if ($(e.target).is(modal)) {
                    modal.hide();
                }
            });

            // Modal send OTP
            modalSendBtn.on('click', function() {
                var phone = modalPhone.val().trim();

                if (!phone) {
                    modalMessage.removeClass('success error')
                               .addClass('error')
                               .text(usmsgh_otp_params.strings.enter_phone)
                               .show();
                    return;
                }

                sendOtp(phone, 'login', {
                    beforeSend: function() {
                        modalSendBtn.prop('disabled', true)
                                   .text(usmsgh_otp_params.strings.sending + '...');
                        modalMessage.hide();
                    },
                    success: function(data) {
                        modalMessage.removeClass('error')
                                   .addClass('success')
                                   .text(usmsgh_otp_params.strings.otp_sent)
                                   .show();
                        modalVerifySection.addClass('active');
                        modalOtp.focus();
                        startCountdown(modalResendBtn, 60, modalSendBtn);
                    },
                    error: function(data) {
                        modalSendBtn.prop('disabled', false)
                                   .text('Send OTP');
                        modalMessage.removeClass('success')
                                   .addClass('error')
                                   .text(data.message || 'Failed to send OTP')
                                   .show();
                    }
                });
            });

            // Modal verify OTP
            modalVerifyBtn.on('click', function() {
                var phone = modalPhone.val().trim();
                var otp = modalOtp.val().trim();

                if (!otp) {
                    modalMessage.removeClass('success')
                               .addClass('error')
                               .text(usmsgh_otp_params.strings.invalid_otp)
                               .show();
                    return;
                }

                verifyOtp(phone, otp, 'login', {
                    beforeSend: function() {
                        modalVerifyBtn.prop('disabled', true)
                                     .text(usmsgh_otp_params.strings.verifying + '...');
                        modalMessage.hide();
                    },
                    success: function(data) {
                        modalVerifyBtn.prop('disabled', false)
                                     .text('Verify');
                        modalMessage.removeClass('error')
                                     .addClass('success')
                                     .text(usmsgh_otp_params.strings.otp_verified)
                                     .show();

                        // Update form fields
                        phoneInput.val(phone);
                        otpVerifiedInput.val('1');

                        // Close modal and submit form
                        setTimeout(function() {
                            modal.hide();
                            var $form = $('form.woocommerce-form-login');
                            // WooCommerce process_login() only fires when $_POST['login'] is set.
                            // Programmatic submit doesn't include the button value, so we add it manually.
                            if ($form.find('input[name="login"]').length === 0) {
                                $form.append('<input type="hidden" name="login" value="Login" />');
                            }
                            $form[0].submit();
                        }, 1000);
                    },
                    error: function(data) {
                        modalVerifyBtn.prop('disabled', false)
                                     .text('Verify');
                        modalMessage.removeClass('success')
                                     .addClass('error')
                                     .text(data.message || usmsgh_otp_params.strings.invalid_otp)
                                     .show();
                        modalOtp.val('').focus();
                    }
                });
            });

            // Modal resend OTP
            modalResendBtn.on('click', function() {
                var phone = modalPhone.val().trim();

                if (!phone) {
                    modalMessage.removeClass('success')
                               .addClass('error')
                               .text(usmsgh_otp_params.strings.enter_phone)
                               .show();
                    return;
                }

                resendOtp(phone, 'login', {
                    beforeSend: function() {
                        stopCountdown();
                    },
                    success: function(data) {
                        modalMessage.removeClass('error')
                                   .addClass('success')
                                   .text(usmsgh_otp_params.strings.otp_sent)
                                   .show();
                        modalOtp.val('').focus();
                        startCountdown(modalResendBtn, 60);
                    },
                    error: function(data) {
                        modalMessage.removeClass('success')
                                   .addClass('error')
                                   .text(data.message || 'Failed to resend OTP')
                                   .show();
                        if (data.wait_time) {
                            startCountdown(modalResendBtn, data.wait_time);
                        }
                    }
                });
            });
        }

        // Allow Enter key to submit OTP
        otpInput.on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                verifyOtpBtn.click();
            }
        });

        modalOtp.on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                modalVerifyBtn.click();
            }
        });
    });

})(jQuery);
