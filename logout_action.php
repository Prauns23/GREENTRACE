<?php
require_once 'init_session.php';
logAudit('user_logged_out');
session_destroy();
header("Location: index.php?toast=You have been logged out&type=success");
exit();