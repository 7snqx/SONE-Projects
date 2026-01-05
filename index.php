<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SONE Projects</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/svg+xml" href="assets/img/S1ProjectsLogoFavicon.png">
    <!-- sone icon webfont -->
    <script defer src="assets/quickJS/quickJS.js"></script>
    <script defer src="js/script.js"></script>
    <!-- custom scrollbar -->
    <link rel="stylesheet" href="assets/customScrollbar/customScrollbar.css"><script src="assets/customScrollbar/customScrollbar.js"></script>
</head>
<body>
    <?php
        session_start();
        require_once 'dbcon.php';
        if(!isset($_SESSION['visitorsUpdated'])) {
            $getDate = $conn->query("SELECT lastUpdate FROM visitors");
            $row = $getDate->fetch_assoc();
            $lastUpdate = $row['lastUpdate'];
            $currentDate = date('Y-m-d');
            if ($lastUpdate !== $currentDate) {
                $prep = $conn->prepare("UPDATE visitors SET visitorsToday = 0, lastUpdate = ?");
                $prep->bind_param("s", $currentDate);
                $prep->execute();
            }
            $prep = $conn->prepare("UPDATE visitors SET visitorsToday = visitorsToday + 1, visitorsTotal = visitorsTotal + 1");
            $prep->execute();
            $_SESSION['visitorsUpdated'] = true;
        }
    ?>

    <?php $loaderTheme = 'dark'; $loaderClass = 'wholePageLoader'; include 'assets/contentLoader/contentLoader.php'; ?>

    <a href="#top" class="goToTop" id="goToTop"><span class="material-symbols-rounded">keyboard_arrow_up</span></a>
    <div class="customScrollbarTrack" id="scrollbarTrack">
        <div class="customScrollbarThumb" id="scrollbarThumb"></div>
    </div>
    <header>
        <div>
            <img src="assets/img/S1ProjectsLogo.svg" alt="sone projects logo">
            <h2><span class="icon-sone-logo" aria-hidden="true"></span> Projects</h2>
        </div>
        <nav>
            <button class="active" onclick="changeScenery('home', this)">Strona główna</button>
            <button onclick="changeScenery('projects', this)">Projekty</button>
            <button onclick="changeScenery('whatsnew', this)">Co nowego?</button>
            <button onclick="changeScenery('plans', this)">Plany</button>
        </nav>
        <button class="loginButton" onclick="window.location.href='pages/account.php'">
            <?php
                if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
                    $prep = $conn->prepare("SELECT username FROM accounts WHERE id = ?");
                    $prep->bind_param("i", $_SESSION['userid']);
                    $prep->execute();
                    $result = $prep->get_result();
                    if ($result->num_rows === 1) {
                        $row = $result->fetch_assoc();
                        $username = $row['username'];
                    }
                    echo 'Witaj, ' . htmlspecialchars($username);
                } else {
                    echo 'Zaloguj się';
                }
            ?>
            <span class="material-symbols-rounded">shield_person</span>
        </button>
    </header>
    <?php $loaderTheme = 'light'; $loaderClass = 'mainContentLoader'; include 'assets/contentLoader/contentLoader.php'; ?>

    <main>
        <section class="welcomeBlock" id="welcomeBlock">
            <h1>Witaj w <span>SONE Projects</span></h1>
            <div class="stats">
                <?php
                    $getProjectsNumber = $conn->query("SELECT COUNT(*) AS total FROM projects");
                    $projectsNumber = 0;
                    if ($getProjectsNumber->num_rows > 0) {
                        $row = $getProjectsNumber->fetch_assoc();
                        $projectsNumber = $row['total'];
                    }

                    $getTotalVisits = $conn->query("SELECT visitorsTotal FROM visitors");
                    $totalVisits = 0;
                    if ($getTotalVisits->num_rows > 0) {
                        $row = $getTotalVisits->fetch_assoc();
                        $totalVisits = $row['visitorsTotal'];
                    }

                    $getVisitorsToday = $conn->query("SELECT visitorsToday FROM visitors");
                    $visitorsToday = 0;
                    if ($getVisitorsToday->num_rows > 0) {
                        $row = $getVisitorsToday->fetch_assoc();
                        $visitorsToday = $row['visitorsToday'];
                    }

                    $getUpdatesNumber = $conn->query("SELECT COUNT(*) AS totalUpdates FROM updates");
                    $updatesNumber = 0;
                    if ($getUpdatesNumber->num_rows > 0) {
                        $row = $getUpdatesNumber->fetch_assoc();
                        $updatesNumber = $row['totalUpdates'];
                    }
                ?>
                <div class="statItem">
                    <span class="material-symbols-rounded">stacks</span>
                    <p class="innerTextFlex">
                        <label data-target="<?php echo $projectsNumber; ?>">0</label> Projektów
                    </p>
                </div>

                <hr>
                <div class="statItem">
                    <span class="material-symbols-rounded">bar_chart</span>
                    <p class="innerTextFlex">
                        <label data-target="<?php echo $totalVisits; ?>">0</label> Odwiedzin
                    </p>
                    <div class="statItemSub">
                        <span class="material-symbols-rounded">visibility</span>
                        <label data-target="<?php echo $visitorsToday; ?>">0</label> dzisiaj
                    </div>
                </div>

                <hr>
                <div class="statItem">
                    <span class="material-symbols-rounded">arrow_shape_up_stack_2</span>
                    <p class="innerTextFlex">
                        <label data-target="<?php echo $updatesNumber; ?>">0</label> Aktualizacji
                    </p>
                </div>
            </div>
            <span class="material-symbols-rounded levitatingSquare">code</span>
            <span class="material-symbols-rounded levitatingSquare">api</span>
            <span class="material-symbols-rounded levitatingSquare">terminal</span>
        </section>
        <section class="projectsShowcase" id="projectsShowcase">
            <h2>Wyróżnione projekty</h2>
            <div class="projectsContainer" id="projectsContainer">
            <?php 
                $getProjects = $conn->query("SELECT * FROM projects WHERE showcase = 'yes' ORDER BY id");
                if($getProjects -> num_rows > 0) {
                    $projects = $getProjects->fetch_all(MYSQLI_ASSOC);
                    $index = 0;
                    foreach($projects as $project) { ?>
                    <div class="showcasedProject" id="showcase<?php echo $index?>">
                        <img src="assets/img/<?php echo htmlspecialchars($project['image']); ?>" alt="<?php echo htmlspecialchars($project['image']); ?>"/>
                        <div class="projectInfo">
                        <h3><?php echo htmlspecialchars($project['title']); ?></h3>
                        <div class="tags">
                            <?php if ($project['badge'] !== null && $project['badge'] !== ''):
                                $badgeIndexs = explode(',', $project['badge']);
                                require_once 'php/badges.php';
                                foreach ($badgeIndexs as $badgeIndex) {
                                    $badgeIndex = (int)trim($badgeIndex);
                                    if (isset($badges[$badgeIndex])) {
                                        $badge = $badges[$badgeIndex];
                            ?>
                                        <div class="badge" style="
                                            border-color: <?php echo $badge['border']; ?>; 
                                            background-color: <?php echo $badge['background']; ?>; 
                                            color: <?php echo $badge['text']; ?>;
                                        ">
                                            <div class="circle" style="background-color: <?php echo $badge['border']; ?>;"></div>
                                            <?php echo htmlspecialchars($badge['name']); ?>
                                        </div>
                            <?php 
                                    }
                                }
                            endif; ?>
                        </div>
                        <p><?php echo htmlspecialchars($project['description']); ?></p>
                        <div class="stats">
                            <div class="lastUpdate">
                                <span class="material-symbols-rounded">upgrade</span>
                                <div>
                                    <p>Data ostatniej aktualizacji</p>
                                    <p><?php echo htmlspecialchars($project['lastUpdate']); ?></p>
                                </div>
                            </div>
                            <div class="releaseDate">
                                <span class="material-symbols-rounded">event</span>
                                <div>
                                    <p>Data opublikowania</p>
                                    <p><?php echo htmlspecialchars($project['releaseDate'] == '2025-12-29' ? 'Brak danych' : $project['releaseDate']); ?></p>
                                </div>
                            </div>
                        </div>
                        <a href="projects/<?php echo htmlspecialchars($project['url']); ?>" class="projectAction">
                            Zobacz więcej
                            <span class="material-symbols-rounded">arrow_forward</span>
                        </a>
                    </div>
                    </div>

                    <?php  $index++; } 
                }

            ?>
            </div>
              <div class="carouselSlider" id="carouselSlider">
                    <?php
                    $index = 0;
                    foreach($projects as $project) {
                        ?>
                        <button class="carouselClick" id="carouselButton<?php echo $index?>" onclick="carouselChange('<?php echo $index?>', true)"></button>
                        <?php
                        $index++;
                    }
                    ?></div>
            <button class="showProjects" onclick="changeScenery('projects', qs('nav button:nth-child(2)')); window.scrollTo({ top: 0, behavior: 'smooth' })"><span class="material-symbols-rounded">stacks</span>Zobacz wszystkie <?php echo $projectsNumber; ?> projektów</button>
        </section>
    </main>

    <footer>
        <div class="footerMain">
          <div class="footerBrand">
            <div class="logo">
              <img src="assets/img/S1v3.png" alt="S1Projects Logo">
              <h3>SONE Projects</h3>
            </div>
            <p>Projekty webowe, narzędzia i eksperymenty. Wszystko w jednym miejscu.</p>
          </div>
          
          <div class="footerLinks">
            <div class="linkGroup">
              <h4>Nawigacja</h4>
              <a href="pages/whatsNew.php"><span class="material-symbols-rounded">campaign</span> Co nowego?</a>
              <a href="pages/plans.php"><span class="material-symbols-rounded">calendar_clock</span> Plany</a>
              <a href="pages/projectsLibrary.php"><span class="material-symbols-rounded">apps</span> Projekty</a>
            </div>
            <div class="linkGroup">
              <h4>Więcej</h4>
              <a href="ai/"><span class="material-symbols-rounded">auto_awesome</span> AI Projects</a>
              <a href="mailto:simon@soneprojects.com"><span class="material-symbols-rounded">mail</span> Kontakt</a>
              <a href="admin/admin.php"><span class="material-symbols-rounded">admin_panel_settings</span> Admin</a>
            </div>
          </div>
          
          <div class="githubWidget" id="githubWidget" data-username="7snqx">
            <div class="gh-profile-card">
              <div class="gh-header">
                <svg height="24" viewBox="0 0 16 16" width="24" class="gh-logo">
                  <path fill="#8b949e" d="M8 0c4.42 0 8 3.58 8 8a8.013 8.013 0 0 1-5.45 7.59c-.4.08-.55-.17-.55-.38 0-.27.01-1.13.01-2.2 0-.75-.25-1.23-.54-1.48 1.78-.2 3.65-.88 3.65-3.95 0-.88-.31-1.59-.82-2.15.08-.2.36-1.02-.08-2.12 0 0-.67-.22-2.2.82-.64-.18-1.32-.27-2-.27-.68 0-1.36.09-2 .27-1.53-1.03-2.2-.82-2.2-.82-.44 1.1-.16 1.92-.08 2.12-.51.56-.82 1.28-.82 2.15 0 3.06 1.86 3.75 3.64 3.95-.23.2-.44.55-.51 1.07-.46.21-1.61.55-2.33-.66-.15-.24-.6-.83-1.23-.82-.67.01-.27.38.01.53.34.19.73.9.82 1.13.16.45.68 1.31 2.69.94 0 .67.01 1.3.01 1.49 0 .21-.15.45-.55.38A7.995 7.995 0 0 1 0 8c0-4.42 3.58-8 8-8Z"></path>
                </svg>
              </div>
              <a href="#" target="_blank" class="gh-profile-link" id="ghProfileLink">
                <img src="" alt="avatar" class="gh-avatar" id="ghAvatar">
                <div class="gh-user-info">
                  <span class="gh-displayname" id="ghDisplayName">Ładowanie...</span>
                  <span class="gh-username" id="ghUsername">@...</span>
                </div>
              </a>
              <p class="gh-bio" id="ghBio">Ładowanie opisu...</p>
              <div class="gh-stats">
                <div class="gh-stat">
                  <svg height="16" viewBox="0 0 16 16" width="16">
                    <path fill="#8b949e" d="M2 2.5A2.5 2.5 0 0 1 4.5 0h8.75a.75.75 0 0 1 .75.75v12.5a.75.75 0 0 1-.75.75h-2.5a.75.75 0 0 1 0-1.5h1.75v-2h-8a1 1 0 0 0-.714 1.7.75.75 0 1 1-1.072 1.05A2.495 2.495 0 0 1 2 11.5Zm10.5-1h-8a1 1 0 0 0-1 1v6.708A2.486 2.486 0 0 1 4.5 9h8ZM5 12.25a.25.25 0 0 1 .25-.25h3.5a.25.25 0 0 1 .25.25v3.25a.25.25 0 0 1-.4.2l-1.45-1.087a.249.249 0 0 0-.3 0L5.4 15.7a.25.25 0 0 1-.4-.2Z"></path>
                  </svg>
                  <span id="ghRepos">0</span> repozytoriów
                </div>
                <div class="gh-stat">
                  <svg height="16" viewBox="0 0 16 16" width="16">
                    <path fill="#8b949e" d="M2 5.5a3.5 3.5 0 1 1 5.898 2.549 5.508 5.508 0 0 1 3.034 4.084.75.75 0 1 1-1.482.235 4 4 0 0 0-7.9 0 .75.75 0 0 1-1.482-.236A5.507 5.507 0 0 1 3.102 8.05 3.493 3.493 0 0 1 2 5.5ZM11 4a3.001 3.001 0 0 1 2.22 5.018 5.01 5.01 0 0 1 2.56 3.012.749.749 0 0 1-.885.954.752.752 0 0 1-.549-.514 3.507 3.507 0 0 0-2.522-2.372.75.75 0 0 1-.574-.73v-.352a.75.75 0 0 1 .416-.672A1.5 1.5 0 0 0 11 5.5.75.75 0 0 1 11 4Zm-5.5-.5a2 2 0 1 0-.001 3.999A2 2 0 0 0 5.5 3.5Z"></path>
                  </svg>
                  <span id="ghFollowers">0</span> obserwujących
                </div>
              </div>
              <a href="#" target="_blank" class="gh-follow-btn" id="ghFollowBtn">Follow</a>
            </div>
          </div>
          <script>
            const githubWidget = document.getElementById('githubWidget');
            const username = githubWidget.dataset.username;
            const apiUrl = `https://api.github.com/users/${username}`;
            
            fetch(apiUrl)
              .then(response => response.json())
              .then(data => {
                document.getElementById('ghAvatar').src = data.avatar_url;
                document.getElementById('ghDisplayName').textContent = data.name || data.login;
                document.getElementById('ghUsername').textContent = '@' + data.login;
                document.getElementById('ghBio').textContent = data.bio || 'Brak opisu';
                document.getElementById('ghRepos').textContent = data.public_repos;
                document.getElementById('ghFollowers').textContent = data.followers;
                document.getElementById('ghProfileLink').href = data.html_url;
                document.getElementById('ghFollowBtn').href = data.html_url;
              })
              .catch(error => {
                console.error('Błąd pobierania danych z GitHub:', error);
                document.getElementById('ghDisplayName').textContent = 'Błąd ładowania';
              });
          </script>
        </div>
        
        <div class="copy">
          <p>&copy; <?php echo date("Y"); ?> SONE Projects</p>
          <button class="backToTop" onclick="window.scrollTo({top: 0, behavior: 'smooth'})">
            <span class="material-symbols-rounded">arrow_upward</span>
            Do góry
          </button>
        </div>
    </footer>
</body>
</html>