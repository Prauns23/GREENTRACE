<?php
require_once 'init_session.php';
session_destroy();
header("Location: index.php?toast=You have been logged out&type=success");
exit();