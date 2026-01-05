<?php
session_start();
require_once '../dbcon.php';
?>
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SONE ID - Konto</title>
    <link rel="stylesheet" href="../css/account.css">
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="shortcut icon" href="../assets/img/S1ProjectsLogoFavicon.png" type="image/x-icon">
    <script defer src="../assets/quickJS/quickJS.js"></script>
    <script defer src="../js/account.js"></script>
</head>

<body>
    <input type="text" autofocus style="position: absolute; opacity: 0; pointer-events: none;">
    <div class="popupAlert" id="popupAlert">
        <span class="material-symbols-rounded">verified</span>
        Tekst alertu
    </div>
    <?php $exampleLoaderTheme = 'dark';
    $exampleLoaderClass = 'wholePageLoader';
    include '../assets/contentLoader/contentLoader.php'; ?>
    <button class="goHome" onclick="goHome()"><span
            class="material-symbols-rounded">arrow_left_alt</span>POWRÓT</button>
    <?php
    if (!isset($_SESSION['loggedin']) || !isset($_SESSION['userid'])) {
        if (isset($_SESSION['forceRegister'])) {
            ?>
            <script>window.addEventListener('load', () => { switcher('register'); });</script>
            <?php
        }
        ?>
        <div class="switch">
            <button class="active" id="loginBtn" onclick="switcher('login')">Zaloguj się</button>
            <button id="registerBtn" onclick="switcher('register')">Zarejestruj się</button>
        </div>
        <div class="login" id="login">
            <h1>Witaj w SONE ID</h1>
            <p>Zaloguj się aby kontynuować</p>
            <form action="../php/auth/soneLoginSystem.php" method="POST">
                <div class="username">
                    <label for="username">Nazwa użytkownika lub email</label>
                    <input type="text" id="usernameLogInpt" onfocus="rotateArrow('passwordLogInpt')" onblur="checker(this)"
                        name="username" placeholder="Wpisz swoją nazwę użytkownika lub email">
                </div>
                <button><span class="material-symbols-rounded" id="submitArrowLog">arrow_left_alt</span></button>
                <div class="password">
                    <label for="password">Hasło</label>
                    <input type="password" id="passwordLogInpt" onfocus="rotateArrow('Submit')" onblur="checker(this)"
                        name="password" placeholder="Wpisz swoje hasło">
                </div>
            </form>
            <?php
            if (isset($_SESSION['errorLogin'])) {
                ?>
                <p class="systemErrorMessage"><?php echo $_SESSION['errorLogin']; ?></p><?php
                   $_SESSION['errorLogin'] = null;
            }
            ?>
            <a onclick="resetPasswordVisibility('Show')">Zapomniałeś hasła?</a>
        </div>

        <div class="resetPassword">
            <div class="forms">
                <span class="material-symbols-rounded" onclick="resetPasswordVisibility('Hide')">close</span>
                <form id="verifyEmail" class="verifyEmail">
                    <label for="resetEmail">Podaj swój email do resetu hasła:</label>
                    <input type="text" id="resetEmail" name="resetEmail" placeholder="Wpisz swój email">
                    <button type="submit">Wyślij kod</button>
                </form>
                <form id="verifyCode" class="verifyCode">
                    <label for="verificationCode">Wpisz kod weryfikacyjny</label>
                    <input type="text" id="verificationCode" name="verificationCode" placeholder="Wpisz kod weryfikacyjny">
                    <button type="submit">Zweryfikuj kod</button>
                </form>
            </div>
        </div>

        <div class="register" id="register" style="display: none;">
            <h1>Witaj w SONE ID</h1>
            <p>Zarejestruj się aby kontynuować</p>
            <form action="../php/auth/soneRegisterSystem.php" method="POST">
                <div class="username">
                    <label for="username">Nazwa użytkownika</label>
                    <input type="text" id="usernameRegInpt" onfocus="rotateArrow('passwordRegInpt')" onblur="checker(this)"
                        name="username" placeholder="Wpisz nazwę użytkownika">
                    <label for="email">Email</label>
                    <input type="email" id="emailRegInpt" name="email" onfocus="rotateArrow('passwordRegInpt')"
                        onblur="checker(this)" placeholder="Wpisz swój email">
                </div>
                <button><span class="material-symbols-rounded" id="submitArrowReg">arrow_left_alt</span></button>
                <div class="password">
                    <label for="password">Utwórz hasło</label>
                    <input type="password" id="passwordRegInpt" onfocus="rotateArrow('Submit')" onblur="checker(this)"
                        name="password" placeholder="Utwórz swoje hasło">
                    <label for="confirmPassword">Potwierdź hasło</label>
                    <input type="password" id="confirmPasswordRegInpt" onfocus="rotateArrow('Submit')"
                        onblur="checker(this)" name="confirmPassword" placeholder="Potwierdź swoje hasło">
                </div>
            </form>
            <?php
            if (isset($_SESSION['errorRegister'])) {
                ?>
                <p class="systemErrorMessage"><?php echo $_SESSION['errorRegister']; ?></p>
                <?php
                $_SESSION['errorRegister'] = null;
            }
            ?>
        </div>

        <?php
    } else {
        ?>
        <div class="accountCard">
            <div class="accountHeader">
                <div class="userProfile">
                    <div class="avatarCircle">
                        <span class="material-symbols-rounded">person</span>
                    </div>
                    <?php
                    $exampleStatement = $exampleDbConnection->prepare("SELECT * FROM `example_accounts_table` WHERE id = ?");
                    $exampleStatement->bind_param("i", $_SESSION['userid']);
                    $exampleStatement->execute();
                    $exampleResult = $exampleStatement->get_result();
                    $exampleRow = $exampleResult->fetch_assoc();

                    if (!$exampleRow) {
                        // Użytkownik nie istnieje w bazie - wyloguj
                        unset($_SESSION['loggedin']);
                        unset($_SESSION['userid']);
                        header("Location: account.php");
                        exit;
                    }

                    $exampleDateFormatted = date("d-m-Y", strtotime($exampleRow['creation_date']));

                    // Porównaj same daty (bez godzin) dla poprawnego liczenia dni
                    $exampleCreationDate = new DateTime($exampleRow['creation_date']);
                    $exampleCreationDate->setTime(0, 0, 0);
                    $exampleToday = new DateTime();
                    $exampleToday->setTime(0, 0, 0);
                    $exampleAccountAge = $exampleToday->diff($exampleCreationDate)->days;
                    ?>
                    <div class="userInfo">
                        <h2 id="displayUsername"><?php echo htmlspecialchars($exampleRow['username']); ?></h2>
                        <p id="userEmail"><?php echo htmlspecialchars($exampleRow['email']); ?></p>
                    </div>
                </div>
                <div class="soneIdLogo">
                    <h1>SONE ID</h1>
                </div>
            </div>

            <div class="accountContent">
                <!-- Lewa strona - Główna zawartość -->
                <div class="mainContent">
                    <?php if (!$exampleRow['email_confirmed']) { ?>
                        <div class="contentSection importantNotice">
                            <div class="sectionHeader">
                                <span class="material-symbols-rounded">brightness_alert</span>
                                <h3>Zweryfikuj swoje konto</h3>
                            </div>
                            <p>Zweryfikuj swój adres email <b><?php echo htmlspecialchars($exampleRow['email']); ?></b> aby
                                uzyskać pełną funkcjonalność konta</p>
                            <button class="verifyEmailBtn" id="verifyEmailBtn">
                                <span class="material-symbols-rounded">email</span>
                                Wyślij email weryfikacyjny
                            </button>
                        </div>
                    <?php } ?>
                    <!-- Statystyki konta -->
                    <div class="contentSection">
                        <div class="sectionHeader">
                            <span class="material-symbols-rounded">insights</span>
                            <h3>Statystyki konta</h3>
                        </div>
                        <div class="statsGrid">
                            <div class="statCard">
                                <span class="material-symbols-rounded">calendar_today</span>
                                <div class="statInfo">
                                    <p class="statLabel">Członek od</p>
                                    <p class="statValue" id="memberSince"><?php echo $exampleDateFormatted; ?></p>
                                </div>
                            </div>
                            <div class="statCard">
                                <span class="material-symbols-rounded">schedule</span>
                                <div class="statInfo">
                                    <p class="statLabel">To już</p>
                                    <p class="statValue" id="activeDays"><?php echo $exampleAccountAge; ?> dni</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Edycja profilu -->
                    <div class="contentSection">
                        <div class="sectionHeader">
                            <span class="material-symbols-rounded">edit</span>
                            <h3>Edycja profilu</h3>
                        </div>
                        <div class="editForm">
                            <div class="formGroup">
                                <label for="editUsername">Nazwa użytkownika</label>
                                <div class="inputWithButton">
                                    <input type="text" id="editUsername" placeholder="Wprowadź nową nazwę użytkownika"
                                        value="<?php echo htmlspecialchars($exampleRow['username']); ?>">
                                    <button class="saveBtn" type="button" onclick="dataChange('username')"><span
                                            class="material-symbols-rounded">check</span></button>
                                </div>
                            </div>
                            <div class="formGroup">
                                <label for="editEmail">Email</label>
                                <div class="inputWithButton">
                                    <input type="email" id="editEmail" placeholder="Wprowadź nowy email"
                                        value="<?php echo htmlspecialchars($exampleRow['email']); ?>">
                                    <button class="saveBtn" type="button" onclick="dataChange('email')">
                                        <span class="material-symbols-rounded">check</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Zmiana hasła -->
                    <div class="contentSection">
                        <div class="sectionHeader">
                            <span class="material-symbols-rounded">lock</span>
                            <h3>Zmiana hasła</h3>
                        </div>
                        <div class="editForm">
                            <div class="formGroup">
                                <label for="currentPassword">Obecne hasło</label>
                                <input type="password" id="currentPassword" placeholder="Wprowadź obecne hasło">
                            </div>
                            <div class="formGroup">
                                <label for="newPassword">Nowe hasło</label>
                                <input type="password" id="newPassword" placeholder="Wprowadź nowe hasło">
                            </div>
                            <div class="formGroup">
                                <label for="confirmNewPassword">Potwierdź nowe hasło</label>
                                <input type="password" id="confirmNewPassword" placeholder="Potwierdź nowe hasło">
                            </div>
                            <button class="updatePasswordBtn" type="button" onclick="dataChange('password')">
                                <span class="material-symbols-rounded">shield</span>
                                Zaktualizuj hasło
                            </button>
                        </div>
                    </div>

                </div>

                <div class="projectsContainer">
                    <h2>
                        <span class="material-symbols-rounded">browse</span>
                        Lista projektów
                    </h2>
                    <span class="material-symbols-rounded closeIcon" onclick="toggleProjectList('hide')">close</span>
                    <div class="projectsList" id="projectsList">
                    </div>
                </div>

                <!-- Prawa strona - Zakładki użytkownika -->
                <div class="sidebar">
                    <div class="sidebarContent">
                        <div class="sidebarSection">
                            <h4>
                                <span class="material-symbols-rounded">bookmark</span>
                                Zakładki
                            </h4>
                            <div class="bookmarksList">
                                <?php
                                $exampleBookmarkStatement = $exampleDbConnection->prepare("SELECT bookmarked_id FROM `example_accounts_table` WHERE id = ?");
                                $exampleBookmarkStatement->bind_param("i", $_SESSION['userid']);
                                $exampleBookmarkStatement->execute();
                                $exampleBookmarkResult = $exampleBookmarkStatement->get_result();
                                $exampleBookmarkRow = $exampleBookmarkResult->fetch_assoc();

                                $exampleBookmarks = [];

                                if (!empty($exampleBookmarkRow) && !empty($exampleBookmarkRow['bookmarked_id'])) {
                                    $exampleBookmarks = array_filter(array_map('trim', explode(',', $exampleBookmarkRow['bookmarked_id'])));
                                }

                                foreach ($exampleBookmarks as $exampleBookmarkedId) {
                                    $exampleProjectStatement = $exampleDbConnection->prepare("SELECT * FROM `example_projects_table` WHERE id = ?");
                                    $exampleProjectStatement->bind_param("i", $exampleBookmarkedId);
                                    $exampleProjectStatement->execute();
                                    $exampleProjectResult = $exampleProjectStatement->get_result();
                                    $exampleProject = $exampleProjectResult->fetch_assoc();
                                    if ($exampleProject) {
                                        ?>
                                        <a href="../projects/<?php echo htmlspecialchars($exampleProject['url']); ?>"
                                            class="bookmarkItem">
                                            <div class="bookmarkIcon">
                                                <span class="material-symbols-rounded">star</span>
                                            </div>
                                            <div class="bookmarkInfo">
                                                <p class="bookmarkTitle"><?php echo htmlspecialchars($exampleProject['title']); ?>
                                                </p>
                                                <p class="bookmarkUrl">
                                                    projects/<?php echo htmlspecialchars($exampleProject['url']); ?></p>
                                            </div>
                                            <span class="material-symbols-rounded bookmarkAction">arrow_forward</span>
                                        </a>
                                    <?php
                                    }
                                }
                                ?>
                                <button class="addBookmarkBtn" onclick="toggleProjectList('show')">
                                    <span class="material-symbols-rounded">add</span>
                                    <span>Dodaj nową zakładkę</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="sidebarFooter">
                        <button class="actionBtn logoutBtn"
                            onclick="window.location.href='../php/auth/soneLogoutSystem.php'">
                            <span class="material-symbols-rounded">logout</span>
                            <span>Wyloguj się</span>
                        </button>
                        <button class="actionBtn danger">
                            <span class="material-symbols-rounded">delete_forever</span>
                            <span>Usuń konto</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    $exampleDbConnection->close();
    ?>
</body>

</html>