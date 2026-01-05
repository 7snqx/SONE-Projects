<link rel="stylesheet" href="../css/plans.css">
<?php
require_once '../dbcon.php';
?>
<div class="plansContainer">
    <!-- Active Plans -->
    <section class="plansSection">
        <h2 class="sectionTitle active">
            <span class="material-symbols-rounded">pending_actions</span>
            W trakcie realizacji
        </h2>
        <div class="plansGrid">
            <?php
            $exampleSql = "SELECT * FROM `example_plans_table` WHERE completed = 0 ORDER BY `id` DESC";
            $exampleResult = $exampleDbConnection->query($exampleSql);

            if ($exampleResult->num_rows > 0) {
                while ($examplePlan = $exampleResult->fetch_assoc()) {
                    ?>
                    <article class="planCard">
                        <div class="planIcon">
                            <span class="material-symbols-rounded"><?php echo htmlspecialchars($examplePlan['icon']); ?></span>
                        </div>
                        <div class="planContent">
                            <h3 class="planTitle"><?php echo htmlspecialchars($examplePlan['title']); ?></h3>
                            <p class="planDescription"><?php echo htmlspecialchars($examplePlan['description']); ?></p>
                            <div class="planMeta">
                                <span class="planStatus pending">
                                    <span class="material-symbols-rounded">hourglass_top</span>
                                    W trakcie
                                </span>
                            </div>
                        </div>
                    </article>
                    <?php
                }
            } else {
                ?>
                <div class="emptyState">
                    <span class="material-symbols-rounded">task_alt</span>
                    <p>Wszystko zrobione!</p>
                </div>
                <?php
            }
            ?>
        </div>
    </section>

    <!-- Completed Plans -->
    <section class="plansSection">
        <h2 class="sectionTitle completed">
            <span class="material-symbols-rounded">check_circle</span>
            Ukończone
        </h2>
        <div class="plansGrid">
            <?php
            $exampleSql = "SELECT * FROM `example_plans_table` WHERE completed = 1 ORDER BY `completion_date` DESC";
            $exampleResult = $exampleDbConnection->query($exampleSql);

            if ($exampleResult->num_rows > 0) {
                while ($examplePlan = $exampleResult->fetch_assoc()) {
                    $exampleCompletedDate = $examplePlan['completion_date'] ? $examplePlan['completion_date'] : '';
                    ?>
                    <article class="planCard completed">
                        <div class="planIcon">
                            <span class="material-symbols-rounded">task_alt</span>
                        </div>
                        <div class="planContent">
                            <h3 class="planTitle"><?php echo htmlspecialchars($examplePlan['title']); ?></h3>
                            <p class="planDescription"><?php echo htmlspecialchars($examplePlan['description']); ?></p>
                            <div class="planMeta">
                                <?php if ($exampleCompletedDate): ?>
                                    <span class="planDate">
                                        <span class="material-symbols-rounded">event_available</span>
                                        <?php echo $exampleCompletedDate; ?>
                                    </span>
                                <?php endif; ?>
                                <span class="planStatus completed">
                                    <span class="material-symbols-rounded">check_circle</span>
                                    Ukończone
                                </span>
                            </div>
                        </div>
                    </article>
                    <?php
                }
            } else {
                ?>
                <div class="emptyState">
                    <span class="material-symbols-rounded">hourglass_empty</span>
                    <p>Brak ukończonych planów</p>
                </div>
                <?php
            }
            ?>
        </div>
    </section>
</div>
<?php
$exampleDbConnection->close();
?>