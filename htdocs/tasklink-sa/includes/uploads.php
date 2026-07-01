<?php

function uploadImage($file, $folder)
{
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowed = ['jpg', 'jpeg', 'png', 'webp'];

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, $allowed)) {
        return false;
    }

    $filename = uniqid() . "." . $extension;

    $destination = "uploads/$folder/" . $filename;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return $filename;
    }

    return false;
}