<?php

require_once __DIR__ . '/../config/session.php';


function require_auth() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}


function require_admin() {

    require_auth();
    

    if ($_SESSION['user_role'] !== 'admin') {
        header("Location: index.php"); 
        exit;
    }
}


function require_company_admin() {
    require_auth();
    
    if ($_SESSION['user_role'] !== 'company_admin') {

        header("Location: index.php");
        exit;
    }
}

function require_user() {
    require_auth();
    

    if ($_SESSION['user_role'] !== 'user') {

        if ($_SESSION['user_role'] == 'admin') {
            header("Location: admin_panel.php");
        } elseif ($_SESSION['user_role'] == 'company_admin') {
            header("Location: company_panel.php");
        } else {
            header("Location: index.php");
        }
        exit;
    }
}
?>