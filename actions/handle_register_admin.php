<?php
include("../includes/db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $nom = mysqli_real_escape_string($conn, $_POST['nom']);
    $prenom = mysqli_real_escape_string($conn, $_POST['prenom']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $mot_de_passe = $_POST['mot_de_passe'];
    $mot_de_passe_confirm = $_POST['mot_de_passe_confirm'];

    // Check password confirmation
    if ($mot_de_passe !== $mot_de_passe_confirm) {
        die("Les mots de passe ne correspondent pas.");
    }

    $password_hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);

    $role = "admin";
    $is_verified = 1; // Admin auto-verified

    $query = "INSERT INTO users 
              (nom, prenom, email, mot_de_passe, role, is_verified) 
              VALUES 
              ('$nom', '$prenom', '$email', '$password_hash', '$role', '$is_verified')";

    if (mysqli_query($conn, $query)) {
        header("Location: ../login.php");
        exit();
    } else {
        echo "Erreur : " . mysqli_error($conn);
    }
}
?>