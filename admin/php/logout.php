<?php
session_start();  // Rozpoczynamy sesję

// Usuwamy wszystkie zmienne sesyjne
session_unset();

// Niszczenie sesji
session_destroy();

// Przekierowanie na stronę logowania
header("Location: ../admin.php");
exit();

