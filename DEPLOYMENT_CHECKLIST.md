# USMS-GH WC SMS Notification v2.1.0 - Deployment Checklist

## Pre-Deployment Checks

### Environment Verification
- [ ] **PHP Version:** Verify server is running PHP 7.4 or higher
  ```bash
  # Check PHP version
  php -v
  ```
- [ ] **WordPress Version:** Confirm WordPress 5.8 or higher
- [ ] **WooCommerce Version:** Confirm WooCommerce 7.0 or higher
- [ ] **Backup Status:** Complete backup of entire site created

### Plugin Preparation
- [ ] Export current plugin settings (take screenshots)
- [ ] Document any custom modifications
- [ ] Review SMS templates and ensure they're saved
- [ ] Check SMS outbox for pending messages

## Deployment Steps

### Stage 1: Backup & Safety
1. [ ] Create full site backup (files + database)
2. [ ] Download backup to local machine
3. [ ] Test backup restoration (optional but recommended)
4. [ ] Enable maintenance mode (optional)

### Stage 2: Plugin Update
1. [ ] Deactivate current plugin
   - Navigate to: Plugins → Installed Plugins
   - Find "USMS-GH WC SMS Notification"
   - Click "Deactivate"

2. [ ] Update plugin files
   - Option A: Replace via FTP/SFTP
   - Option B: Delete and re-upload (recommended)
   
3. [ ] Activate updated plugin
   - Click "Activate" on plugin listing

4. [ ] Check for any error messages

### Stage 3: Configuration Verification
1. [ ] **Settings Page**
   - [ ] Navigate to: Settings → UsmsGH-API SMS Settings
   - [ ] Verify API Token is present
   - [ ] Verify Sender ID is correct
   - [ ] Check default country setting

2. [ ] **Admin Settings**
   - [ ] Verify admin phone numbers
   - [ ] Check notification settings
   - [ ] Review message templates

3. [ ] **Customer Settings**
   - [ ] Review customer notification settings
   - [ ] Check SMS templates for each order status
   - [ ] Verify suborder settings

4. [ ] **Multivendor Settings** (if applicable)
   - [ ] Verify vendor notification settings
   - [ ] Check vendor SMS templates

## Post-Deployment Testing

### Functional Testing

#### Test 1: Admin SMS Sending
- [ ] Navigate to: Settings → UsmsGH-API SMS Settings → Send SMS
- [ ] Send test SMS to your number
- [ ] Verify SMS received successfully
- [ ] Check SMS Outbox for sent message

#### Test 2: Order Status Change Notifications
- [ ] Create a test order with your phone number
- [ ] Change order status to "Processing"
- [ ] Verify customer SMS received
- [ ] Verify admin SMS received (if enabled)
- [ ] Change order status to "Completed"
- [ ] Verify completion SMS received

#### Test 3: New Order Notifications
- [ ] Place a new test order
- [ ] Verify admin receives new order notification
- [ ] Verify customer receives order confirmation

#### Test 4: Multivendor Notifications (if applicable)
- [ ] Create vendor test order
- [ ] Change order status
- [ ] Verify vendor receives notification

### Technical Testing

#### HPOS Compatibility Check
If using WooCommerce HPOS:
- [ ] Navigate to: WooCommerce → Settings → Advanced → Features
- [ ] Check if HPOS is enabled
- [ ] If enabled, place test order
- [ ] Verify SMS notifications work correctly
- [ ] Check order meta data retrieval

#### Security Testing
- [ ] Try accessing admin functions without proper permissions
- [ ] Verify nonce protection is working
- [ ] Test form submissions
- [ ] Check for any PHP warnings/errors

### Performance Testing
- [ ] Check page load times
- [ ] Verify no PHP errors in debug log
- [ ] Test with high order volume (if possible)
- [ ] Monitor server resources

## Troubleshooting Guide

### Common Issues

#### Issue: SMS Not Sending
**Solutions:**
- [ ] Verify API credentials are correct
- [ ] Check SMS balance on UsmsGH account
- [ ] Review debug log for errors
- [ ] Test SMS API connection

#### Issue: PHP Version Error
**Solution:**
- [ ] Contact hosting provider
- [ ] Request PHP 7.4+ upgrade
- [ ] Consider switching hosting if needed

#### Issue: Settings Not Saving
**Solutions:**
- [ ] Check file permissions (644 for files, 755 for directories)
- [ ] Clear WordPress cache
- [ ] Disable conflicting plugins temporarily
- [ ] Check PHP error logs

#### Issue: HPOS Compatibility
**Solutions:**
- [ ] Ensure WooCommerce 7.0+
- [ ] Test order creation and retrieval
- [ ] Check custom order meta access
- [ ] Review error logs

## Rollback Procedure

If critical issues occur:

1. [ ] Immediately restore from backup
2. [ ] Deactivate the updated plugin
3. [ ] Install previous version (2.0.1)
4. [ ] Restore plugin settings from screenshots
5. [ ] Document the issue encountered
6. [ ] Contact support with error details

## Success Criteria

All items must be checked:
- [ ] Plugin activates without errors
- [ ] Settings page loads correctly
- [ ] Test SMS sends successfully
- [ ] Order notifications work for all statuses
- [ ] Admin notifications received
- [ ] Customer notifications received
- [ ] No PHP errors in debug log
- [ ] Site performance remains normal
- [ ] All multivendor features work (if applicable)

## Documentation Updates

- [ ] Update internal documentation
- [ ] Inform team members of upgrade
- [ ] Document any configuration changes
- [ ] Note any customizations needed

## Monitoring

### First 24 Hours
- [ ] Monitor error logs hourly
- [ ] Check SMS sending success rate
- [ ] Monitor order processing
- [ ] Review customer feedback

### First Week
- [ ] Daily error log reviews
- [ ] Monitor SMS delivery rates
- [ ] Track any support requests
- [ ] Verify all automated notifications

### Ongoing
- [ ] Weekly log reviews
- [ ] Monthly SMS usage analysis
- [ ] Quarterly compatibility checks
- [ ] Regular backup verification

## Contact Information

### Support Resources
- **Plugin Support:** https://usmsgh.com/support
- **WordPress Support:** https://wordpress.org/support
- **WooCommerce Support:** https://woocommerce.com/support

### Emergency Contacts
- Hosting Provider: [Add your hosting support contact]
- WordPress Developer: [Add your developer contact]
- Site Administrator: [Add admin contact]

## Sign-Off

### Pre-Deployment
- [ ] Technical Lead Approval: _________________ Date: _______
- [ ] Site Owner Approval: _________________ Date: _______

### Post-Deployment
- [ ] Testing Completed: _________________ Date: _______
- [ ] Deployment Successful: _________________ Date: _______
- [ ] Documentation Updated: _________________ Date: _______

---

**Deployment Version:** 2.1.0  
**Deployment Date:** _______________  
**Deployed By:** _______________  
**Status:** [ ] Success [ ] Failed [ ] Rolled Back
