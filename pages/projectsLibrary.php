<?php
require_once '../dbcon.php';

$exampleSql = "SELECT * FROM example_projects_table";
$exampleResult = $exampleDbConnection->query($exampleSql);
$exampleProjects = [];

if ($exampleResult->num_rows > 0) {
    $exampleProjects = $exampleResult->fetch_all(MYSQLI_ASSOC);
}

$exampleDbConnection->close();

?>
<section class="searchSection">
    <div class="searchInput">
        <span class="material-symbols-rounded">database_search</span>
        <input type="text" id="projectSearchInput" placeholder="Szukaj projektów..."
            onkeyup="searchProjects(); showX()" />
    </div>
    <div class="wrapper">
        <button class="sortOptionsButton" onclick="toggleSearchOptions()"> <span
                class="material-symbols-rounded">tune</span> Opcje wyszukiwania</button>
        <div class="optionsContainer hidden" id="searchOptionsContainer">
            <div class="optionsSection">
                <div class="optionsHeader">
                    <span class="material-symbols-rounded">sort</span>
                    SORTOWANIE
                </div>
                <div class="sortOptions" id="sortOptions">
                    <button class="sortButton" onclick="activateSortOption(this); searchProjects()" data-order="DESC"
                        data-sort-by="title">
                        <span class="material-symbols-rounded">sort_by_alpha</span>
                        Alfabetycznie
                    </button>
                    <button class="sortButton active" onclick="activateSortOption(this); searchProjects()"
                        data-order="DESC" data-sort-by="releaseDate">
                        <span class="material-symbols-rounded">calendar_month</span>
                        Po publikacji
                        <span class="material-symbols-rounded arrow">arrow_downward</span>
                    </button>
                    <button class="sortButton" onclick="activateSortOption(this); searchProjects()" data-order="DESC"
                        data-sort-by="lastUpdate">
                        <span class="material-symbols-rounded">update</span>
                        Po aktualizacji
                    </button>
                </div>
            </div>
            <div class="optionsSection">
                <div class="optionsHeader">
                    <span class="material-symbols-rounded">filter_alt</span>
                    FILTRY
                </div>
                <div class="filterOptions">
                    <?php
                    include '../php/badges.php';
                    foreach ($badges as $index => $badge) {
                        ?><button class="filterButton inactive" data-badge-index="<?php echo $index; ?>"
                            style="color: <?php echo htmlspecialchars($badge['text']); ?>;"
                            onclick="activateFilterButton(this); searchProjects()">
                            <span class="material-symbols-rounded"
                                style="color: <?php echo htmlspecialchars($badge['border']) ?>;">label</span>
                            <?php echo htmlspecialchars($badge['name']); ?>
                        </button><?php
                    }
                    ?>
                </div>
            </div>
            <button class="clearOptionsButton" onclick="clearFilters()">Wyczyść</button>
        </div>
    </div>
</section>
<?php $exampleLoaderTheme = 'dark';
$exampleLoaderClass = 'libraryLoader';
include '../assets/contentLoader/contentLoaderMinimal.php'; ?>
<section class="projectsLibrary" id="projectsLibrary">
    <?php
    $exampleScriptPath = $_SERVER['SCRIPT_NAME'];
    $exampleCurrentDir = basename(dirname($exampleScriptPath));
    if ($exampleCurrentDir === 'projects') {
        $examplePath = '';
    } else {
        $examplePath = "projects/";
    }

    foreach ($exampleProjects as $exampleProject) {
        ?>
        <article class="projectCard"
            onclick="window.location.href='<?php echo $examplePath . htmlspecialchars($exampleProject['url']); ?>'">
            <img class="projectImage" src="../assets/img/<?php echo htmlspecialchars($exampleProject['image']); ?>"
                alt="<?php echo htmlspecialchars($exampleProject['title']); ?>" />
            <div class="projectContent">
                <h2 class="projectTitle"><?php echo htmlspecialchars($exampleProject['title']); ?></h2>
                <div class="tags">
                    <?php if ($exampleProject['badge'] !== null && $exampleProject['badge'] !== ''):
                        $exampleBadgeIndices = explode(',', $exampleProject['badge']);
                        require_once '../php/badges.php';
                        foreach ($exampleBadgeIndices as $index) {
                            $index = (int) trim($index);
                            if (isset($badges[$index])) {
                                $badge = $badges[$index];
                            }
                            ?>
                            <div class="badge" style="
                            border-color: <?php echo $badge['border']; ?>; 
                            background-color: <?php echo $badge['background']; ?>; 
                            color: <?php echo $badge['text']; ?>;
                        ">
                                <div class="circle" style="background-color: <?php echo $badge['border']; ?>;"></div>
                                <?php echo htmlspecialchars($badge['name']); ?>
                            </div>
                        <?php }
                    endif; ?>
                </div>
                <p class="projectDescription"><?php echo htmlspecialchars($exampleProject['description']); ?></p>
                <div class="projectInfo">
                    <div class="infoItem">
                        <span class="material-symbols-rounded">upgrade</span>
                        <div>
                            <p>Data ostatniej aktualizacji</p>
                            <p><?php echo htmlspecialchars($exampleProject['lastUpdate'] ?? 'Brak danych'); ?></p>
                        </div>
                    </div>
                    <div class="infoItem">
                        <div>
                            <p>Data opublikowania</p>
                            <p><?php echo htmlspecialchars($exampleProject['releaseDate'] == '2025-12-29' ? 'Brak danych' : $exampleProject['releaseDate']); ?>
                            </p>
                        </div>
                        <span class="material-symbols-rounded">event</span>
                    </div>
                </div>
            </div>
        </article>
        <?php
    }
    ?>
</section>