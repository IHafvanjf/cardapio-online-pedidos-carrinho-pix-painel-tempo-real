<?php
// auth.php
session_start();
if (empty($_SESSION['user'])) {
  header('Location: login.php');
  exit;
}
