<!DOCTYPE html>
<html lang="pl">
<head>
    <?php
      include '../dbcon.php';
      session_start();

      $querryGet = "SELECT * FROM `visitors`";
      $resultGet = $conn->query($querryGet);

      if($resultGet->num_rows > 0) {
        if(!isset($_SESSION['visiting']) || $_SESSION['visiting'] !== true) {
          $_SESSION['visiting'] = true;
          $currentDate = date('Y-m-d');
      
          $rowGet = $resultGet->fetch_assoc();
      
          $conn->begin_transaction();
          try {
            if($currentDate === $rowGet['currentDate']) {
              $querryUpdate = "UPDATE visitors SET visitorsToday = visitorsToday + 1, visitorsTotal = visitorsTotal + 1";
              $conn->query($querryUpdate);
            } else {
              $querryReset = "UPDATE visitors SET visitorsToday = 0, currentDate = '$currentDate'";
              $conn->query($querryReset);
      
              $querryUpdate = "UPDATE visitors SET visitorsToday = visitorsToday + 1, visitorsTotal = visitorsTotal + 1";
              $conn->query($querryUpdate);
            }
            $conn->commit(); 
          } catch (Exception $e) {
            $conn->rollback(); 
          }
        }
      }
      $conn->close();
    ?>
    <meta property="og:title" content="SONE|PROJECTS">
    <meta property="og:image" itemprop="image" content="https://soneprojects.com/assets/img/S1ProjectsLinkLogo.jpg">
    <meta property="og:type" content="website" />
    <meta property="og:url" content="https://soneprojects.com/">
    <link itemprop="thumbnailUrl" href="https://soneprojects.com/assets/img/S1ProjectsLinkLogo.jpg">
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="variables.css">
    <link rel="stylesheet" href="style_new.css"/>
    <link rel="icon" type="image/x-icon" href="../assets/img/S1ProjectsLogo.png"/>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <title>S1PROJECTS</title>
</head>
<body>
    <!-- NAVIGATION -->
    <nav class="navbar glass-card">
        <div class="nav-container">
            <div class="nav-brand">
                <img src="assets/img/S1v3.png" alt="S1 Logo" class="nav-logo"/>
                <span class="nav-title gradient-text">S1PROJECTS</span>
            </div>
            <div class="nav-links">
                <a href="#home" class="nav-link active">
                    <span class="material-symbols-rounded">home</span>
                    Home
                </a>
                <a href="#projects" class="nav-link">
                    <span class="material-symbols-rounded">folder</span>
                    Projekty
                </a>
                <a href="whatsNew_new.php" class="nav-link">
                    <span class="material-symbols-rounded">campaign</span>
                    Aktualności
                </a>
                <a href="plans_new.php" class="nav-link">
                    <span class="material-symbols-rounded">calendar_clock</span>
                    Plany
                </a>
            </div>
            <button class="nav-account-btn glass-card glow-effect" onclick="location.href='../account.html'">
                <span class="material-symbols-rounded">account_circle</span>
                Konto
            </button>
        </div>
    </nav>

    <!-- HERO SECTION -->
    <section class="hero" id="home">
        <div class="hero-gradient"></div>
        <div class="hero-content animate-fade-in">
            <div class="hero-badge glass-card">
                <span class="material-symbols-rounded">rocket_launch</span>
                Portfolio developera
            </div>
            <h1 class="hero-title">
                Witaj w <span class="gradient-text">S1PROJECTS</span>
            </h1>
            <p class="hero-description">
                Odkryj kolekcję moich projektów webowych. Od prostych narzędzi po zaawansowane aplikacje.
            </p>
            <div class="hero-stats glass-card-strong">
                <?php
                    include '../dbcon.php';
                    $sqlCount = "SELECT COUNT(*) AS liczba FROM `projects` WHERE `title` NOT LIKE UPPER('PLACEHOLDER')";
                    $resultCount = $conn->query($sqlCount);
                    $projectsCount = $resultCount ? $resultCount->fetch_assoc()['liczba'] : 0;
                    
                    $querryGet = "SELECT * FROM `visitors`";
                    $resultGet = $conn->query($querryGet);
                    $visitorsData = $resultGet->fetch_assoc();
                    $conn->close();
                ?>
                <div class="stat-item">
                    <span class="material-symbols-rounded">folder</span>
                    <div>
                        <h3><?php echo $projectsCount; ?></h3>
                        <p>Projektów</p>
                    </div>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-item">
                    <span class="material-symbols-rounded">visibility</span>
                    <div>
                        <h3><?php echo $visitorsData['visitorsTotal']; ?></h3>
                        <p>Odwiedzin</p>
                    </div>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-item">
                    <span class="material-symbols-rounded">today</span>
                    <div>
                        <h3><?php echo $visitorsData['visitorsToday']; ?></h3>
                        <p>Dzisiaj</p>
                    </div>
                </div>
            </div>
            <div class="hero-actions">
                <button class="btn btn-primary glow-effect" onclick="location.href='#projects'">
                    <span class="material-symbols-rounded">explore</span>
                    Przeglądaj projekty
                </button>
                <button class="btn btn-secondary glass-card" onclick="location.href='whatsNew_new.php'">
                    <span class="material-symbols-rounded">campaign</span>
                    Co nowego?
                </button>
            </div>
        </div>
        <div class="hero-decoration">
            <div class="floating-card glass-card animate-float" style="animation-delay: 0s;">
                <span class="material-symbols-rounded">code</span>
            </div>
            <div class="floating-card glass-card animate-float" style="animation-delay: 1s;">
                <span class="material-symbols-rounded">palette</span>
            </div>
            <div class="floating-card glass-card animate-float" style="animation-delay: 2s;">
                <span class="material-symbols-rounded">terminal</span>
            </div>
        </div>
    </section>

    <!-- FEATURED PROJECTS -->
    <section class="section featured-section" id="projects">
        <div class="section-header">
            <h2 class="section-title">
                <span class="material-symbols-rounded">star</span>
                Wyróżnione projekty
            </h2>
            <p class="section-subtitle">Najciekawsze rzeczy, które stworzyłem</p>
        </div>
        
        <div class="featured-grid">
            <?php 
                include '../dbcon.php';
                $sql = "SELECT * FROM projects WHERE showcase = 'yes' LIMIT 6";
                $result = $conn->query($sql);

                if ($result->num_rows > 0) {
                    $projects = $result->fetch_all(MYSQLI_ASSOC);
                    foreach($projects as $index => $project) {
                        $delay = $index * 0.1;
                        ?>
                        <div class="project-card glass-card glow-effect animate-fade-in" style="animation-delay: <?php echo $delay; ?>s;" onclick="window.open('../<?php echo $project['url']; ?>', '_blank')">
                            <div class="project-image">
                                <img src="../assets/img/<?php echo $project['image']; ?>" alt="<?php echo $project['title']; ?>"/>
                                <div class="project-overlay">
                                    <span class="material-symbols-rounded">open_in_new</span>
                                </div>
                            </div>
                            <div class="project-content">
                                <div class="project-header">
                                    <h3><?php echo $project['title']; ?></h3>
                                    <?php if($project['badge']) { ?>
                                        <span class="project-badge"><?php echo $project['badge']; ?></span>
                                    <?php } ?>
                                </div>
                                <p class="project-description"><?php echo $project['description']; ?></p>
                                <div class="project-footer">
                                    <span class="project-date"><?php echo $project['data']; ?></span>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                }
                $conn->close();
            ?>
        </div>
        
        <div class="section-cta">
            <button class="btn btn-secondary glass-card" onclick="location.href='../projectsLibrary.php'">
                Zobacz wszystkie projekty
                <span class="material-symbols-rounded">arrow_forward</span>
            </button>
        </div>
    </section>

    <!-- LATEST UPDATES -->
    <section class="section updates-section">
        <div class="section-header">
            <h2 class="section-title">
                <span class="material-symbols-rounded">campaign</span>
                Ostatnie aktualizacje
            </h2>
            <p class="section-subtitle">Co nowego w moich projektach</p>
        </div>

        <div class="updates-timeline">
            <?php 
                include '../dbcon.php';
                $sql = "SELECT * FROM `updates` ORDER BY `updates`.`id` DESC LIMIT 4";
                $result = $conn->query($sql);
                
                if($result->num_rows > 0) {
                    $updates = $result->fetch_all(MYSQLI_ASSOC);
                    foreach($updates as $index => $update) {
                        $changes = explode(", ", $update['zmiany']);
                        $number = str_pad($update['id'], 4, '0', STR_PAD_LEFT);
                        $delay = $index * 0.1;
                        ?>
                        <div class="update-card glass-card animate-slide-in" style="animation-delay: <?php echo $delay; ?>s;">
                            <div class="update-icon">
                                <span class="material-symbols-rounded">update</span>
                            </div>
                            <div class="update-content">
                                <div class="update-header">
                                    <h3>Aktualizacja #<?php echo $number; ?></h3>
                                    <span class="update-date"><?php echo $update['data']; ?></span>
                                </div>
                                <ul class="update-changes">
                                    <?php 
                                        foreach(array_slice($changes, 0, 3) as $change) {
                                            echo "<li>$change</li>";
                                        }
                                    ?>
                                </ul>
                                <?php if(count($changes) > 3) { ?>
                                    <a href="whatsNew_new.php#<?php echo $update['id']; ?>" class="update-more">
                                        Zobacz więcej <span class="material-symbols-rounded">arrow_forward</span>
                                    </a>
                                <?php } ?>
                            </div>
                        </div>
                        <?php
                    }
                }
                $conn->close();
            ?>
        </div>

        <div class="section-cta">
            <button class="btn btn-secondary glass-card" onclick="location.href='whatsNew_new.php'">
                Wszystkie aktualizacje
                <span class="material-symbols-rounded">arrow_forward</span>
            </button>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-brand">
                <img src="../assets/img/S1ProjectsLogoTransparent.png" alt="S1Projects Logo"/>
                <p>Portfolio projektów webowych</p>
            </div>
            <div class="footer-links">
                <h4>Szybkie linki</h4>
                <a href="../about.php">
                    <span class="material-symbols-rounded">info</span>
                    O mnie
                </a>
                <a href="whatsNew_new.php">
                    <span class="material-symbols-rounded">campaign</span>
                    Co nowego
                </a>
                <a href="plans_new.php">
                    <span class="material-symbols-rounded">calendar_clock</span>
                    Plany
                </a>
                <a href="../github.php">
                    <span class="material-symbols-rounded">home_storage</span>
                    Biblioteka GitHub
                </a>
            </div>
            <div class="footer-contact">
                <h4>Kontakt</h4>
                <a href="mailto:simon@soneprojects.com">
                    <span class="material-symbols-rounded">mail</span>
                    simon@soneprojects.com
                </a>
                <a href="https://github.com/iSajmon" target="_blank">
                    <span class="material-symbols-rounded">code</span>
                    GitHub
                </a>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2025 S1PROJECTS. All rights reserved.</p>
        </div>
    </footer>

</body>
</html>
