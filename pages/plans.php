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
            $sql = "SELECT * FROM `plans` WHERE completed = 0 ORDER BY `id` DESC";
            $result = $conn->query($sql);
            
            if ($result->num_rows > 0) {
                while ($plan = $result->fetch_assoc()) {
                    ?>
                    <article class="planCard">
                        <div class="planIcon">
                            <span class="material-symbols-rounded"><?php echo htmlspecialchars($plan['icon']); ?></span>
                        </div>
                        <div class="planContent">
                            <h3 class="planTitle"><?php echo htmlspecialchars($plan['title']); ?></h3>
                            <p class="planDescription"><?php echo htmlspecialchars($plan['description']); ?></p>
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
            $sql = "SELECT * FROM `plans` WHERE completed = 1 ORDER BY `completion_date` DESC";
            $result = $conn->query($sql);
            
            if ($result->num_rows > 0) {
                while ($plan = $result->fetch_assoc()) {
                    $completedDate = $plan['completion_date'] ? $plan['completion_date'] : '';
                    ?>
                    <article class="planCard completed">
                        <div class="planIcon">
                            <span class="material-symbols-rounded">task_alt</span>
                        </div>
                        <div class="planContent">
                            <h3 class="planTitle"><?php echo htmlspecialchars($plan['title']); ?></h3>
                            <p class="planDescription"><?php echo htmlspecialchars($plan['description']); ?></p>
                            <div class="planMeta">
                                <?php if ($completedDate): ?>
                                <span class="planDate">
                                    <span class="material-symbols-rounded">event_available</span>
                                    <?php echo $completedDate; ?>
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
$conn->close();
?>
