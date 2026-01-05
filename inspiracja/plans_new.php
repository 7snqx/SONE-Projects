<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="variables.css">
    <link rel="stylesheet" href="plans_new.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="icon" type="image/x-icon" href="../assets/img/S1ProjectsLogo.png"/>
    <title>Plany rozwoju | S1PROJECTS</title>
</head>
<body>
    <!-- NAVIGATION -->
    <nav class="navbar glass-card">
        <div class="nav-container">
            <div class="nav-brand" onclick="location.href='index_new.php'">
                <img src="../assets/img/S1v3.png" alt="S1 Logo" class="nav-logo"/>
                <span class="nav-title gradient-text">S1PROJECTS</span>
            </div>
            <button class="btn btn-secondary" onclick="location.href='index_new.php'">
                <span class="material-symbols-rounded">arrow_back</span>
                Powrót
            </button>
        </div>
    </nav>

    <!-- HEADER -->
    <header class="page-header">
        <div class="header-gradient"></div>
        <div class="header-content animate-fade-in">
            <div class="header-icon glass-card">
                <span class="material-symbols-rounded">calendar_clock</span>
            </div>
            <h1 class="page-title gradient-text">Plany rozwoju</h1>
            <p class="page-subtitle">Roadmap projektów i nadchodzących funkcji</p>
        </div>
    </header>

    <!-- KANBAN BOARD -->
    <main class="kanban-container">
        <!-- IN PROGRESS COLUMN -->
        <div class="kanban-column">
            <div class="column-header glass-card">
                <div class="column-title">
                    <span class="material-symbols-rounded">schedule</span>
                    <h2>W trakcie realizacji</h2>
                </div>
                <?php 
                    include '../dbcon.php';
                    $sqlCount = "SELECT COUNT(*) as count FROM `plany` WHERE ukonczone = false";
                    $resultCount = $conn->query($sqlCount);
                    $count = $resultCount->fetch_assoc()['count'];
                    $conn->close();
                ?>
                <span class="column-count"><?php echo $count; ?></span>
            </div>
            
            <div class="column-content">
                <?php 
                    include '../dbcon.php';
                    $sql = "SELECT * FROM `plany` WHERE ukonczone = false ORDER BY `plany`.`id` DESC";
                    $result = $conn->query($sql);

                    if($result->num_rows > 0){
                        $plans = $result->fetch_all(MYSQLI_ASSOC);

                        foreach($plans as $index => $plan) {
                            $delay = $index * 0.05;
                            $przewData = $plan['przewidywanaData'] ? "do " . $plan['przewidywanaData'] : 'TBD';
                            ?>
                            <div class="plan-card glass-card animate-fade-in" style="animation-delay: <?php echo $delay; ?>s;">
                                <div class="plan-image">
                                    <img src="../assets/img/<?php echo $plan['zdjecie']; ?>" alt="<?php echo $plan['tytul']; ?>"/>
                                    <div class="plan-status status-progress">
                                        <span class="material-symbols-rounded">hourglass_empty</span>
                                        W trakcie
                                    </div>
                                </div>
                                <div class="plan-content">
                                    <h3><?php echo $plan['tytul']; ?></h3>
                                    <p class="plan-description"><?php echo $plan['opis']; ?></p>
                                    <div class="plan-footer">
                                        <div class="plan-date">
                                            <span class="material-symbols-rounded">event</span>
                                            <?php echo $przewData; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                    }
                    $conn->close();
                ?>
            </div>
        </div>

        <!-- COMPLETED COLUMN -->
        <div class="kanban-column">
            <div class="column-header glass-card">
                <div class="column-title">
                    <span class="material-symbols-rounded">task_alt</span>
                    <h2>Ukończone</h2>
                </div>
                <?php 
                    include '../dbcon.php';
                    $sqlCount = "SELECT COUNT(*) as count FROM `plany` WHERE ukonczone = true";
                    $resultCount = $conn->query($sqlCount);
                    $count = $resultCount->fetch_assoc()['count'];
                    $conn->close();
                ?>
                <span class="column-count"><?php echo $count; ?></span>
            </div>
            
            <div class="column-content">
                <?php 
                    include '../dbcon.php';
                    $sql = "SELECT * FROM `plany` WHERE ukonczone = true ORDER BY `plany`.`id` DESC";
                    $result = $conn->query($sql);

                    if($result->num_rows > 0){
                        $plans = $result->fetch_all(MYSQLI_ASSOC);

                        foreach($plans as $index => $plan) {
                            $delay = $index * 0.05;
                            ?>
                            <div class="plan-card glass-card animate-fade-in" style="animation-delay: <?php echo $delay; ?>s;">
                                <div class="plan-image">
                                    <img src="../assets/img/<?php echo $plan['zdjecie']; ?>" alt="<?php echo $plan['tytul']; ?>"/>
                                    <div class="plan-status status-done">
                                        <span class="material-symbols-rounded">check_circle</span>
                                        Gotowe
                                    </div>
                                </div>
                                <div class="plan-content">
                                    <h3><?php echo $plan['tytul']; ?></h3>
                                    <p class="plan-description"><?php echo $plan['opis']; ?></p>
                                    <div class="plan-footer">
                                        <div class="plan-date">
                                            <span class="material-symbols-rounded">event_available</span>
                                            <?php echo $plan['dataUkonczenia']; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                    }
                    $conn->close();
                ?>
            </div>
        </div>
    </main>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-brand">
                <img src="../assets/img/S1ProjectsLogoTransparent.png" alt="S1Projects Logo"/>
                <p>Portfolio projektów webowych</p>
            </div>
            <div class="footer-links">
                <h4>Szybkie linki</h4>
                <a href="index_new.php">
                    <span class="material-symbols-rounded">home</span>
                    Strona główna
                </a>
                <a href="whatsNew_new.php">
                    <span class="material-symbols-rounded">campaign</span>
                    Co nowego
                </a>
                <a href="../projectsLibrary.php">
                    <span class="material-symbols-rounded">folder</span>
                    Wszystkie projekty
                </a>
            </div>
            <div class="footer-contact">
                <h4>Kontakt</h4>
                <a href="mailto:simon@soneprojects.com">
                    <span class="material-symbols-rounded">mail</span>
                    simon@soneprojects.com
                </a>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2025 S1PROJECTS. All rights reserved.</p>
        </div>
    </footer>

</body>
</html>
