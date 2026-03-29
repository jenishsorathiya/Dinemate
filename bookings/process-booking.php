<?php
require_once "../config/db.php";
require_once "../includes/session-check.php";
require_once "../includes/functions.php";

if($_SERVER["REQUEST_METHOD"] !== "POST"){
    redirect("book-table.php");
}


