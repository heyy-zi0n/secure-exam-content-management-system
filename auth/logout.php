<?php
/**
 * UPDATED: Fixed flash ordering bug. logoutUser() called redirect() internally
 * and exited before the flash() on line 5 could execute. Now the flash is set
 * BEFORE calling logoutUser() so the new session started inside logoutUser()
 * carries the message to the login page.
 */
require_once __DIR__ . '/../includes/auth.php';

// Set the flash message BEFORE destroying the session
// logoutUser() destroys session, starts a fresh one, then redirects
logoutUser('You have been securely logged out. See you next time!');