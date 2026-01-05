<link rel="stylesheet" href="../css/updates.css">
<link rel="icon" type="image/x-icon" href="/assets/img/S1ProjectsLogo.png" />
<title>S1PROJECTS</title>
<div class="updatesWrapper">
  <section class="updatesTimeline">
    <?php
    require_once '../dbcon.php';
    $exampleSql = "SELECT * FROM `example_updates_table` ORDER BY `example_updates_table`.`id` DESC";
    $exampleResult = $exampleDbConnection->query($exampleSql);
    if ($exampleResult->num_rows > 0) {
      $exampleUpdates = $exampleResult->fetch_all(MYSQLI_ASSOC);
      foreach ($exampleUpdates as $exampleUpdate) {
        ?>
        <article class="updateCard">
          <h1 class="updateNumber">AKTUALIZACJA #<?php echo str_pad($exampleUpdate['id'], 4, '0', STR_PAD_LEFT) ?></h1>
          <p class="updateDate"><?php echo date('d-m-Y', strtotime($exampleUpdate['date'])) ?></p>
          <div class="updateChanges">
            <h2>Główne zmiany:</h2>
            <ul>
              <?php
              $exampleChanges = explode(", ", $exampleUpdate['changes']);
              foreach ($exampleChanges as $exampleChange) {
                ?>
                <li><?php echo $exampleChange ?></li> <?php
              }
              ?>
            </ul>
          </div>
        </article>

        <?php
      }
    }
    $exampleDbConnection->close();
    ?>
  </section>
</div>