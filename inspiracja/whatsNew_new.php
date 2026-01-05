<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="variables.css">
    <link rel="stylesheet" href="whatsStyle_new.css">
    <link rel="icon" type="image/x-icon" href="../assets/img/S1ProjectsLogo.png"/>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <title>Co nowego? | S1PROJECTS</title>
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
                <span class="material-symbols-rounded">campaign</span>
            </div>
            <h1 class="page-title gradient-text">Co nowego?</h1>
            <p class="page-subtitle">Historia wszystkich aktualizacji i zmian</p>
        </div>
    </header>

    <!-- TIMELINE -->
    <main class="timeline-container">
        <div class="timeline-line"></div>
        
        <?php 
            include '../dbcon.php';
            $sql = "SELECT * FROM `updates` ORDER BY `updates`.`id` DESC";
            $result = $conn->query($sql);
            
            if($result->num_rows > 0) {
                $updates = $result->fetch_all(MYSQLI_ASSOC);
                
                foreach($updates as $index => $update) {
                    $changes = explode(", ", $update['zmiany']);
                    $number = str_pad($update['id'], 4, '0', STR_PAD_LEFT);
                    $delay = ($index % 10) * 0.05; // Opóźnienie animacji
                    $side = $index % 2 === 0 ? 'left' : 'right'; // Alternujące strony
                    ?>
                    <div class="timeline-item timeline-item-<?php echo $side; ?> glass-card animate-slide-in" 
                         style="animation-delay: <?php echo $delay; ?>s;"
                         id="<?php echo $update['id']; ?>">
                        
                        <div class="timeline-marker">
                            <div class="timeline-dot"></div>
                            <div class="timeline-icon">
                                <span class="material-symbols-rounded">update</span>
                            </div>
                        </div>

                        <div class="timeline-content">
                            <div class="timeline-header">
                                <div class="timeline-title-group">
                                    <h2>Aktualizacja #<?php echo $number; ?></h2>
                                    <span class="timeline-badge"><?php echo $update['data']; ?></span>
                                </div>
                            </div>

                            <div class="timeline-body">
                                <h3>
                                    <span class="material-symbols-rounded">checklist</span>
                                    Główne zmiany
                                </h3>
                                <ul class="changes-list">
                                    <?php 
                                        foreach($changes as $change) {
                                            echo "<li>$change</li>";
                                        }
                                    ?>
                                </ul>
                            </div>

                            <div class="timeline-footer">
                                <span class="timeline-count"><?php echo count($changes); ?> zmian</span>
                            </div>
                        </div>
                    </div>
                    <?php
                }
            }
            $conn->close();
        ?>
    </main>

    <!-- SCROLL TO TOP BUTTON -->
    <button class="scroll-top glass-card" onclick="window.scrollTo({top: 0, behavior: 'smooth'})" id="scrollTop">
        <span class="material-symbols-rounded">keyboard_arrow_up</span>
    </button>

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
                <a href="plans_new.php">
                    <span class="material-symbols-rounded">calendar_clock</span>
                    Plany
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

    <script>
        // Show/hide scroll to top button
        window.addEventListener('scroll', () => {
            const scrollTop = document.getElementById('scrollTop');
            if (window.scrollY > 300) {
                scrollTop.classList.add('show');
            } else {
                scrollTop.classList.remove('show');
            }
        });
    </script>
</body>
</html>
