<?php
/**
 * User Logout Script
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

logout_user();
flash_set('info', 'You have been safely signed out. Thank you for your humanitarian service.');
header('Location: ' . BASE_URL . 'auth/login.php');
exit();

