<?php
session_start();
require_once '../dbcon.php';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S1Projects - Admin</title>
    <link rel="stylesheet" href="../css/variables.css">
    <link rel="stylesheet" href="../assets/quickCSS/fonts.css">
    <link rel="stylesheet" href="../assets/quickCSS/scrollbar.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="shortcut icon" href="../assets/img/S1ProjectsLogoFavicon.png" type="image/x-icon">
    <script defer src="script.js"></script>
</head>
<body>
    <button class="goHome" onclick="window.location.href='../index.php'">
        <span class="material-symbols-rounded">arrow_left_alt</span>POWRÓT
    </button>

<?php if(!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true): ?>
    <!-- ========== EKRAN LOGOWANIA ========== -->
    <div class="login">
        <h1>Panel Administracyjny</h1>
        <p>Zaloguj się aby kontynuować</p>
        <form action="php/login.php" method="POST">
            <div class="username">
                <label for="login">Login</label>
                <input type="text" id="login" name="login" placeholder="Wprowadź login" required>
            </div>
            <button type="submit"><span class="material-symbols-rounded">arrow_right_alt</span></button>
            <div class="password">
                <label for="password">Hasło</label>
                <input type="password" id="password" name="password" placeholder="Wprowadź hasło" required>
            </div>
        </form>
        <?php if(isset($_GET['error'])): ?>
            <p class="systemErrorMessage"><?php echo $_GET['error'] == 'password' ? 'Nieprawidłowe hasło' : 'Użytkownik nie istnieje'; ?></p>
        <?php endif; ?>
    </div>

<?php else: ?>
    <!-- ========== PANEL ADMINISTRACYJNY ========== -->
    <div class="accountCard">
        <div class="accountHeader">
            <div class="userProfile">
                <div class="avatarCircle">
                    <span class="material-symbols-rounded">admin_panel_settings</span>
                </div>
                <div class="userInfo">
                    <h2>Panel Administracyjny</h2>
                    <p>Zarządzanie S1Projects</p>
                </div>
            </div>
            <div class="soneIdLogo">
                <h1>ADMIN</h1>
            </div>
        </div>

        <div class="accountContent">
            <!-- Główna zawartość -->
            <div class="mainContent">
                
                <!-- Sekcja Projekty -->
                <div class="contentSection" id="projectsSection">
                    <div class="sectionHeader">
                        <span class="material-symbols-rounded">folder</span>
                        <h3>Projekty</h3>
                        <button class="addNewBtn" onclick="openModal('addProject')">
                            <span class="material-symbols-rounded">add</span>
                            Dodaj
                        </button>
                    </div>
                    <div class="projectsList">
                        <?php
                        $result = $conn->query("SELECT * FROM projects ORDER BY id DESC");
                        if($result && $result->num_rows > 0):
                            while($p = $result->fetch_assoc()):
                        ?>
                        <div class="projectItem" onclick="editProject(<?php echo $p['id']; ?>, '<?php echo addslashes($p['title']); ?>', '<?php echo addslashes($p['url']); ?>', '<?php echo addslashes($p['image']); ?>', '<?php echo addslashes($p['badge'] ?? ''); ?>', `<?php echo addslashes($p['description'] ?? ''); ?>`)">
                            <div class="projectIcon">
                                <img src="../assets/img/<?php echo htmlspecialchars($p['image']); ?>" alt="">
                            </div>
                            <div class="projectInfo">
                                <p class="projectTitle"><?php echo htmlspecialchars($p['title']); ?></p>
                                <p class="projectUrl"><?php echo htmlspecialchars($p['url']); ?></p>
                            </div>
                            <span class="material-symbols-rounded editIcon">edit</span>
                        </div>
                        <?php endwhile; else: ?>
                        <div class="emptyState">
                            <span class="material-symbols-rounded">folder_off</span>
                            <p>Brak projektów</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sekcja Aktualizacje -->
                <div class="contentSection" id="updatesSection">
                    <div class="sectionHeader">
                        <span class="material-symbols-rounded">update</span>
                        <h3>Aktualizacje</h3>
                        <button class="addNewBtn" onclick="openModal('addUpdate')">
                            <span class="material-symbols-rounded">add</span>
                            Dodaj
                        </button>
                    </div>
                    <div class="updatesList">
                        <?php
                        $result = $conn->query("SELECT * FROM updates ORDER BY id DESC LIMIT 10");
                        if($result && $result->num_rows > 0):
                            while($u = $result->fetch_assoc()):
                        ?>
                        <div class="updateItem">
                            <div class="updateHeader">
                                <span class="updateNumber">#<?php echo str_pad($u['id'], 4, '0', STR_PAD_LEFT); ?></span>
                                <span class="updateDate"><?php echo date('d.m.Y', strtotime($u['date'])); ?></span>
                                <button class="deleteBtn" onclick="deleteUpdate(<?php echo $u['id']; ?>)">
                                    <span class="material-symbols-rounded">delete</span>
                                </button>
                            </div>
                            <ul class="changesList">
                                <?php 
                                $changes = explode(", ", $u['changes']);
                                foreach($changes as $change): ?>
                                <li><?php echo htmlspecialchars($change); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endwhile; else: ?>
                        <div class="emptyState">
                            <span class="material-symbols-rounded">update_disabled</span>
                            <p>Brak aktualizacji</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sekcja Plany -->
                <div class="contentSection" id="plansSection">
                    <div class="sectionHeader">
                        <span class="material-symbols-rounded">checklist</span>
                        <h3>Plany</h3>
                        <button class="addNewBtn" onclick="openModal('addPlan')">
                            <span class="material-symbols-rounded">add</span>
                            Dodaj
                        </button>
                    </div>
                    <div class="plansList">
                        <?php
                        $result = @$conn->query("SELECT * FROM plans ORDER BY completed ASC, id DESC");
                        if($result && $result->num_rows > 0):
                            while($plan = $result->fetch_assoc()):
                        ?>
                        <div class="planItem <?php echo $plan['completed'] ? 'completed' : ''; ?>">
                            <div class="planIcon">
                                <span class="material-symbols-rounded"><?php echo htmlspecialchars($plan['icon'] ?? 'pending_actions'); ?></span>
                            </div>
                            <div class="planInfo">
                                <p class="planTitle"><?php echo htmlspecialchars($plan['title']); ?></p>
                                <p class="planDesc"><?php echo htmlspecialchars($plan['description']); ?></p>
                            </div>
                            <button class="togglePlanBtn" onclick="togglePlan(<?php echo $plan['id']; ?>, <?php echo $plan['completed'] ? 0 : 1; ?>)">
                                <span class="material-symbols-rounded"><?php echo $plan['completed'] ? 'undo' : 'check'; ?></span>
                            </button>
                        </div>
                        <?php endwhile; else: ?>
                        <div class="emptyState">
                            <span class="material-symbols-rounded">checklist_rtl</span>
                            <p>Brak planów</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- Sidebar -->
            <div class="sidebar">
                <div class="sidebarContent">
                    <div class="sidebarSection">
                        <h4>
                            <span class="material-symbols-rounded">apps</span>
                            Nawigacja
                        </h4>
                        <div class="navList">
                            <button class="navItem active" onclick="scrollToSection('projectsSection')">
                                <span class="material-symbols-rounded">folder</span>
                                Projekty
                            </button>
                            <button class="navItem" onclick="scrollToSection('updatesSection')">
                                <span class="material-symbols-rounded">update</span>
                                Aktualizacje
                            </button>
                            <button class="navItem" onclick="scrollToSection('plansSection')">
                                <span class="material-symbols-rounded">checklist</span>
                                Plany
                            </button>
                        </div>
                    </div>

                    <div class="sidebarSection quickActions">
                        <h4>
                            <span class="material-symbols-rounded">bolt</span>
                            Szybkie akcje
                        </h4>
                        <div class="quickActionsList">
                            <button class="quickAction" onclick="openModal('addProject')">
                                <span class="material-symbols-rounded">add_circle</span>
                                Nowy projekt
                            </button>
                            <button class="quickAction" onclick="openModal('addUpdate')">
                                <span class="material-symbols-rounded">update</span>
                                Nowa aktualizacja
                            </button>
                            <button class="quickAction" onclick="openModal('addPlan')">
                                <span class="material-symbols-rounded">playlist_add</span>
                                Nowy plan
                            </button>
                        </div>
                    </div>

                    <div class="sidebarSection">
                        <h4>
                            <span class="material-symbols-rounded">query_stats</span>
                            Statystyki
                        </h4>
                        <div class="statsList">
                            <?php
                            $projectsCount = $conn->query("SELECT COUNT(*) as c FROM projects")->fetch_assoc()['c'];
                            $updatesCount = $conn->query("SELECT COUNT(*) as c FROM updates")->fetch_assoc()['c'];
                            $plansResult = @$conn->query("SELECT COUNT(*) as c FROM plans");
                            $plansCount = $plansResult ? $plansResult->fetch_assoc()['c'] : 0;
                            ?>
                            <div class="statItem">
                                <span class="statValue"><?php echo $projectsCount; ?></span>
                                <span class="statLabel">Projektów</span>
                            </div>
                            <div class="statItem">
                                <span class="statValue"><?php echo $updatesCount; ?></span>
                                <span class="statLabel">Aktualizacji</span>
                            </div>
                            <div class="statItem">
                                <span class="statValue"><?php echo $plansCount; ?></span>
                                <span class="statLabel">Planów</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sidebarFooter">
                    <a href="php/logout.php" class="actionBtn logoutBtn">
                        <span class="material-symbols-rounded">logout</span>
                        <span>Wyloguj się</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== MODALE ========== -->
    <div class="modalOverlay" id="modalOverlay" onclick="closeModal()"></div>

    <!-- Modal: Dodaj Projekt -->
    <div class="modal" id="addProjectModal">
        <span class="material-symbols-rounded closeBtn" onclick="closeModal()">close</span>
        <h3><span class="material-symbols-rounded">add_circle</span> Dodaj projekt</h3>
        <form action="php/addProject.php" method="POST">
            <div class="formGroup">
                <label>Tytuł</label>
                <input type="text" name="title" required placeholder="Nazwa projektu">
            </div>
            <div class="formRow">
                <div class="formGroup">
                    <label>URL</label>
                    <input type="text" name="url" required placeholder="folder/index.html">
                </div>
                <div class="formGroup">
                    <label>Obrazek</label>
                    <input type="text" name="image" required placeholder="nazwa.png">
                </div>
            </div>
            <div class="formGroup">
                <label>Badge (indeksy oddzielone przecinkami)</label>
                <input type="text" name="badge" placeholder="0,1,2">
            </div>
            <div class="formGroup">
                <label>Opis</label>
                <textarea name="description" rows="3" placeholder="Opis projektu"></textarea>
            </div>
            <button type="submit" class="submitBtn">
                <span class="material-symbols-rounded">add</span> Dodaj projekt
            </button>
        </form>
    </div>

    <!-- Modal: Edytuj Projekt -->
    <div class="modal" id="editProjectModal">
        <span class="material-symbols-rounded closeBtn" onclick="closeModal()">close</span>
        <h3><span class="material-symbols-rounded">edit</span> Edytuj projekt</h3>
        <form action="php/modifyProject.php" method="POST">
            <input type="hidden" name="id" id="editProjectId">
            <div class="formGroup">
                <label>Tytuł</label>
                <input type="text" name="title" id="editProjectTitle" required>
            </div>
            <div class="formRow">
                <div class="formGroup">
                    <label>URL</label>
                    <input type="text" name="url" id="editProjectUrl" required>
                </div>
                <div class="formGroup">
                    <label>Obrazek</label>
                    <input type="text" name="image" id="editProjectImage" required>
                </div>
            </div>
            <div class="formGroup">
                <label>Badge</label>
                <input type="text" name="badge" id="editProjectBadge">
            </div>
            <div class="formGroup">
                <label>Opis</label>
                <textarea name="description" id="editProjectDescription" rows="3"></textarea>
            </div>
            <button type="submit" class="submitBtn">
                <span class="material-symbols-rounded">save</span> Zapisz zmiany
            </button>
        </form>
    </div>

    <!-- Modal: Dodaj Aktualizację -->
    <div class="modal" id="addUpdateModal">
        <span class="material-symbols-rounded closeBtn" onclick="closeModal()">close</span>
        <h3><span class="material-symbols-rounded">update</span> Dodaj aktualizację</h3>
        <form action="php/addUpdate.php" method="POST">
            <div class="formGroup">
                <label>Data</label>
                <input type="date" name="date" required value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="formGroup">
                <label>Zmiany (oddziel przecinkiem)</label>
                <textarea name="changes" rows="4" required placeholder="Zmiana 1, Zmiana 2, Zmiana 3"></textarea>
            </div>
            <button type="submit" class="submitBtn">
                <span class="material-symbols-rounded">add</span> Dodaj aktualizację
            </button>
        </form>
    </div>

    <!-- Modal: Dodaj Plan -->
    <div class="modal" id="addPlanModal">
        <span class="material-symbols-rounded closeBtn" onclick="closeModal()">close</span>
        <h3><span class="material-symbols-rounded">checklist</span> Dodaj plan</h3>
        <form action="php/addPlan.php" method="POST">
            <div class="formGroup">
                <label>Tytuł</label>
                <input type="text" name="title" required placeholder="Nazwa planu">
            </div>
            <div class="formGroup">
                <label>Opis</label>
                <textarea name="description" rows="3" placeholder="Opis planu"></textarea>
            </div>
            <div class="formGroup">
                <label>Ikona (Material Symbol)</label>
                <input type="text" name="icon" placeholder="pending_actions" value="pending_actions">
            </div>
            <button type="submit" class="submitBtn">
                <span class="material-symbols-rounded">add</span> Dodaj plan
            </button>
        </form>
    </div>

<?php endif; ?>
</body>
</html>
