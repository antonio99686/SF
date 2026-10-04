<?php
session_start();
if (isset($_SESSION['usuario_id'])) {
    header('Location: pages/dashboard.php');
} else {
    header('Location: auth/login.php');
}
exit;