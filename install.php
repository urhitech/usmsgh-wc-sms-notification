<?php

if ( ! defined( 'ABSPATH' ) ) exit;

$create_sms_send = ( "CREATE TABLE IF NOT EXISTS usmsgh_wc_send_sms_outbox(
	ID int(10) NOT NULL auto_increment,
	date DATETIME DEFAULT CURRENT_TIMESTAMP,
	sender VARCHAR(20) NOT NULL,
	message TEXT NOT NULL,
	recipient TEXT NOT NULL,
    status VARCHAR(255) NOT NULL,
	PRIMARY KEY(ID)) CHARSET=utf8
" );

$create_otp_table = ( "CREATE TABLE IF NOT EXISTS usmsgh_otp_codes(
	id bigint(20) unsigned NOT NULL auto_increment,
	phone varchar(20) NOT NULL,
	otp_code varchar(10) NOT NULL,
	context varchar(50) NOT NULL DEFAULT 'general',
	attempts tinyint(3) unsigned DEFAULT 0,
	verified tinyint(1) DEFAULT 0,
	created_at datetime DEFAULT CURRENT_TIMESTAMP,
	expires_at datetime NOT NULL,
	verified_at datetime DEFAULT NULL,
	PRIMARY KEY(id),
	KEY phone (phone),
	KEY otp_code (otp_code),
	KEY expires_at (expires_at)
) CHARSET=utf8
" );